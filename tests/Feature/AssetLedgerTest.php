<?php

namespace Tests\Feature;

use App\Enums\Availability;
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
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Proves the rules hold in the database itself, not only in the PHP code paths. */
class AssetLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->department = Department::factory()->create();
        $this->officer = User::factory()->create(['role' => Role::Officer, 'department_id' => $this->department->id]);
    }

    private function asset(string $tag = 'COM-68-0001'): Asset
    {
        $category = Category::query()->first() ?? Category::factory()->create();

        return AssetLedger::register(['asset_tag' => $tag, 'name' => 'Notebook', 'category_id' => $category->id], $this->department->id, $this->officer);
    }

    private function loan(Asset $asset): Loan
    {
        return Loan::factory()->for($asset)->create();
    }

    public function test_full_loan_cycle_writes_one_movement_per_state_change(): void
    {
        $asset = $this->asset();
        $loan = $this->loan($asset);

        AssetLedger::approve($loan, $this->officer);
        AssetLedger::handOver($loan, $this->officer);
        $this->assertSame(Availability::OnLoan, $asset->fresh()->availability);

        AssetLedger::receiveReturn($loan->fresh(), $this->officer, Condition::Damaged);
        $asset->refresh();

        $this->assertSame(Availability::Available, $asset->availability);
        $this->assertSame(Condition::Damaged, $asset->condition);
        $this->assertSame(LoanStatus::Returned, $loan->fresh()->status);
        $this->assertEquals(
            [MovementType::OpeningBalance, MovementType::Borrow, MovementType::Return],
            $asset->movements()->orderBy('id')->pluck('type')->all(),
        );
    }

    public function test_database_rejects_a_second_approved_loan_on_the_same_asset(): void
    {
        $asset = $this->asset();
        AssetLedger::approve($this->loan($asset), $this->officer);

        $this->expectExceptionMessage('มีคำขอยืมที่อนุมัติหรือส่งมอบแล้ว');
        AssetLedger::approve($this->loan($asset), $this->officer);
    }

    public function test_database_rejects_disposed_asset_that_is_on_loan(): void
    {
        $asset = $this->asset();
        DB::table('assets')->where('id', $asset->id)->update(['availability' => 'on_loan']);

        // Bypasses AssetLedger on purpose: the trigger must still refuse.
        $this->expectExceptionMessage('A disposed asset must be available');
        DB::table('assets')->where('id', $asset->id)->update(['condition' => 'disposed']);
    }

    public function test_asset_tag_can_be_reused_after_soft_delete_but_not_while_active(): void
    {
        $this->asset('COM-68-0001')->delete();
        $this->asset('COM-68-0001');

        $this->expectExceptionMessage('UNIQUE constraint failed: assets.asset_tag');
        $this->asset('COM-68-0001');
    }

    public function test_movements_are_append_only(): void
    {
        $movement = $this->asset()->movements()->first();

        $this->expectExceptionMessage('append-only');
        $movement->update(['note' => 'แก้ประวัติ']);
    }

    public function test_movements_cannot_be_deleted(): void
    {
        $this->asset();

        $this->expectExceptionMessage('append-only');
        DB::table('asset_movements')->delete();
    }

    public function test_stale_state_is_refused_instead_of_overwriting(): void
    {
        $asset = $this->asset();
        $staleCopy = Asset::find($asset->id);
        AssetLedger::sendToRepair($asset, $this->officer, 'ร้านซ่อม', null);

        // $staleCopy still thinks the asset is available; the conditional UPDATE matches 0 rows.
        $this->expectExceptionMessage('ถูกเปลี่ยนโดยผู้ใช้อื่นแล้ว');
        AssetLedger::dispose($staleCopy, $this->officer);
    }

    public function test_cannot_send_an_on_loan_asset_to_repair(): void
    {
        $asset = $this->asset();
        $loan = $this->loan($asset);
        AssetLedger::approve($loan, $this->officer);
        AssetLedger::handOver($loan, $this->officer);

        $this->expectException(DomainException::class);
        AssetLedger::sendToRepair($asset->fresh(), $this->officer, null, null);
    }

    public function test_an_open_loan_can_still_be_returned_after_the_asset_is_soft_deleted(): void
    {
        $asset = $this->asset();
        $loan = $this->loan($asset);
        AssetLedger::approve($loan, $this->officer);
        AssetLedger::handOver($loan, $this->officer);
        $asset->delete();

        AssetLedger::receiveReturn($loan->fresh(), $this->officer, Condition::Usable);

        $this->assertSame(Availability::Available, Asset::withTrashed()->find($asset->id)->availability);
        $this->assertSame(LoanStatus::Returned, $loan->fresh()->status);
    }

    public function test_an_asset_reserved_by_an_approved_loan_cannot_be_disposed(): void
    {
        $asset = $this->asset();
        AssetLedger::approve($this->loan($asset), $this->officer);

        $this->expectExceptionMessage('มีคำขอยืมที่อนุมัติแล้ว');
        AssetLedger::dispose($asset, $this->officer);
    }

    public function test_a_loan_cannot_be_approved_while_the_asset_is_in_repair(): void
    {
        $asset = $this->asset();
        $loan = $this->loan($asset);
        AssetLedger::sendToRepair($asset, $this->officer, null, null);

        $this->expectExceptionMessage('ต้องใช้งานได้และว่างอยู่');
        AssetLedger::approve($loan->fresh(), $this->officer);
    }

    public function test_cancel_and_hand_over_at_the_same_time_cannot_overwrite_each_other(): void
    {
        $asset = $this->asset();
        $loan = $this->loan($asset);
        AssetLedger::approve($loan, $this->officer);
        $officersCopy = Loan::find($loan->id);

        AssetLedger::cancel($loan);

        try {
            AssetLedger::handOver($officersCopy, $this->officer);
            $this->fail('Hand-over of a cancelled loan should be refused.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('ถูกเปลี่ยนสถานะโดยผู้ใช้อื่นแล้ว', $exception->getMessage());
        }
        $this->assertSame(LoanStatus::Cancelled, $loan->fresh()->status);
        $this->assertSame(Availability::Available, $asset->fresh()->availability);
    }

    public function test_business_dates_follow_bangkok_time(): void
    {
        $asset = $this->asset();
        $loan = Loan::factory()->for($asset)->create(['due_on' => '2026-10-02']);
        AssetLedger::approve($loan, $this->officer);
        AssetLedger::handOver($loan, $this->officer);

        // 06:00 in Bangkok is still 2 Oct in UTC.
        $this->travelTo(Carbon::parse('2026-10-03 06:00', 'Asia/Bangkok'));

        $this->assertSame(1, Loan::query()->overdue()->count());
        AssetLedger::receiveReturn($loan, $this->officer, Condition::Usable);
        $this->assertSame('2026-10-03', $loan->fresh()->returned_on->toDateString());
    }

    public function test_only_idle_assets_can_be_deleted_and_a_reused_tag_blocks_restore(): void
    {
        $asset = $this->asset('COM-68-0001');
        $loan = $this->loan($asset);
        $this->assertTrue($asset->canBeDeleted());

        AssetLedger::approve($loan, $this->officer);
        $this->assertFalse($asset->canBeDeleted());

        AssetLedger::cancel($loan);
        $asset->delete();
        $this->assertTrue($asset->canBeRestored());

        $this->asset('COM-68-0001');
        $this->assertFalse($asset->canBeRestored());
    }
}
