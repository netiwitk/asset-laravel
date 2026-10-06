<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\LoanStatus;
use App\Enums\MovementType;
use App\Enums\Role;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Loan;
use App\Models\User;
use App\Services\AssetLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The JSON API behind the asset scanner app. */
class ScannerApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/assets/COM-68-0001';

    private Department $it;

    private Department $finance;

    private User $officer;

    private User $staff;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->it = Department::factory()->create();
        $this->finance = Department::factory()->create();
        $this->officer = User::factory()->create(['role' => Role::Officer, 'department_id' => $this->finance->id]);
        $this->staff = User::factory()->create(['role' => Role::Staff, 'department_id' => $this->it->id]);
        $this->asset = AssetLedger::register(
            ['asset_tag' => 'COM-68-0001', 'name' => 'Notebook', 'category_id' => Category::factory()->create()->id],
            $this->it->id,
            $this->officer,
        );
    }

    private function approvedLoan(): Loan
    {
        $loan = Loan::factory()->for($this->asset)->create(['requester_id' => $this->staff->id]);
        AssetLedger::approve($loan, $this->officer);

        return $loan;
    }

    public function test_a_missing_or_unknown_token_is_401(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->withToken('1|not-a-real-token')->getJson(self::URL)->assertUnauthorized();
    }

    public function test_login_issues_a_working_token_and_refuses_a_wrong_password(): void
    {
        $this->postJson('/api/tokens', ['email' => $this->officer->email, 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง']);

        $token = $this->postJson('/api/tokens', ['email' => $this->officer->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('role', Role::Officer->getLabel())
            ->json('token');

        $this->withToken($token)->getJson(self::URL)->assertOk();
    }

    public function test_a_deactivated_user_can_neither_log_in_nor_use_an_old_token(): void
    {
        $token = $this->officer->createToken('scanner')->plainTextToken;
        $this->officer->update(['is_active' => false]);

        $this->withToken($token)->getJson(self::URL)->assertUnauthorized();
        $this->postJson('/api/tokens', ['email' => $this->officer->email, 'password' => 'password'])->assertUnprocessable();
    }

    public function test_logout_revokes_the_token(): void
    {
        $token = $this->officer->createToken('scanner')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/tokens/current')->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson(self::URL)->assertUnauthorized();
    }

    public function test_demo_tokens_exist_only_in_demo_mode(): void
    {
        User::factory()->create(['email' => 'officer@demo.test', 'role' => Role::Officer]);

        config(['app.demo' => false]);
        $this->postJson('/api/tokens/demo/officer')->assertNotFound();

        config(['app.demo' => true]);
        $this->postJson('/api/tokens/demo/officer')->assertOk()->assertJsonPath('role', Role::Officer->getLabel());
    }

    public function test_staff_get_404_for_another_departments_asset(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => Role::Staff, 'department_id' => $this->finance->id]));
        $this->getJson(self::URL)->assertNotFound();

        Sanctum::actingAs($this->staff);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.tag', 'COM-68-0001');
    }

    public function test_the_scan_shows_the_loan_and_only_the_buttons_this_user_may_press(): void
    {
        $this->approvedLoan();

        Sanctum::actingAs($this->officer);
        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.loan.status.value', LoanStatus::Approved->value)
            ->assertJsonPath('data.loan.borrower', $this->staff->name)
            ->assertJsonPath('data.actions', ['hand_over']);

        Sanctum::actingAs($this->staff);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.actions', []);
    }

    public function test_officer_hands_over_and_takes_back_a_damaged_asset(): void
    {
        $loan = $this->approvedLoan();
        Sanctum::actingAs($this->officer);

        $this->postJson(self::URL.'/hand-over')
            ->assertOk()
            ->assertJsonPath('data.availability', ['value' => 'on_loan', 'label' => 'ถูกยืม', 'tone' => 'info'])
            ->assertJsonPath('data.actions', ['receive_return']);

        $this->postJson(self::URL.'/receive-return', ['condition' => Condition::Damaged->value, 'note' => 'จอแตก'])
            ->assertOk()
            ->assertJsonPath('data.loan', null)
            ->assertJsonPath('data.condition.value', Condition::Damaged->value);

        $this->assertSame(LoanStatus::Returned, $loan->fresh()->status);
        $this->assertSame(
            [MovementType::OpeningBalance, MovementType::Borrow, MovementType::Return],
            $this->asset->movements()->orderBy('id')->pluck('type')->all(),
        );
    }

    public function test_staff_cannot_hand_over(): void
    {
        $loan = $this->approvedLoan();
        Sanctum::actingAs($this->staff);

        $this->postJson(self::URL.'/hand-over')->assertForbidden();

        $this->assertSame(LoanStatus::Approved, $loan->fresh()->status);
    }

    public function test_a_return_must_be_usable_or_damaged(): void
    {
        $loan = $this->approvedLoan();
        AssetLedger::handOver($loan, $this->officer);
        Sanctum::actingAs($this->officer);

        $this->postJson(self::URL.'/receive-return', ['condition' => Condition::Disposed->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('condition');

        $this->assertSame(LoanStatus::HandedOver, $loan->fresh()->status);
    }

    public function test_a_broken_ledger_rule_comes_back_as_its_thai_message(): void
    {
        $loan = $this->approvedLoan();
        // Damaged on the shelf after approval: the ledger must refuse to hand it over.
        DB::table('assets')->where('id', $this->asset->id)->update(['condition' => Condition::Damaged->value]);
        Sanctum::actingAs($this->officer);

        $this->postJson(self::URL.'/hand-over')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'ทรัพย์สินต้องใช้งานได้และว่างอยู่ (ตอนนี้: ชำรุด · ว่าง)');

        $this->assertSame(LoanStatus::Approved, $loan->fresh()->status);
    }
}
