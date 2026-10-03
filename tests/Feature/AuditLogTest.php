<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Department;
use App\Models\User;
use App\Services\AssetLedger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The audit trail records real edits only, hides secrets, and cannot be rewritten. */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_changes_stay_in_the_ledger_and_only_field_edits_are_audited(): void
    {
        $officer = User::factory()->create(['role' => Role::Officer]);
        [$from, $to] = Department::factory()->count(2)->create();
        $asset = AssetLedger::register(['asset_tag' => 'COM-68-0001', 'name' => 'Notebook', 'category_id' => Category::factory()->create()->id, 'cost' => 12000], $from->id, $officer);

        $this->actingAs($officer);
        AssetLedger::transfer($asset, $officer, $to->id);
        $asset->update(['cost' => 15000, 'location_note' => 'ห้อง 301']);

        $log = AuditLog::query()->where('event', 'updated')->sole();
        $this->assertSame($officer->id, $log->actor_id);
        $this->assertSame(['cost', 'location_note'], array_keys($log->diff));
        $this->assertNull($log->diff['location_note']['from']);
    }

    public function test_logout_is_not_logged_and_a_new_password_is_masked(): void
    {
        $user = User::factory()->create();

        // Logging out rotates the remember-me token, which saves the user.
        $this->actingAs($user);
        auth()->logout();
        $this->assertFalse(AuditLog::query()->where('event', 'updated')->exists());

        $user->update(['password' => 'new-secret-pass']);
        $log = AuditLog::query()->where('event', 'updated')->sole();
        $this->assertSame(['password' => ['from' => '•••', 'to' => '•••']], $log->diff);
    }

    public function test_audit_log_is_append_only(): void
    {
        User::factory()->create();

        try {
            DB::table('audit_logs')->update(['event' => 'deleted']);
            $this->fail('UPDATE was allowed');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->expectExceptionMessage('append-only');
        DB::table('audit_logs')->delete();
    }
}
