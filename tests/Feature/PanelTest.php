<?php

namespace Tests\Feature;

use App\Enums\Availability;
use App\Enums\Condition;
use App\Enums\LoanStatus;
use App\Enums\MovementType;
use App\Enums\Role;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\Loans\Pages\ManageLoans;
use App\Filament\Resources\RepairOrders\Pages\ManageRepairOrders;
use App\Filament\Resources\RepairOrders\RepairOrderResource;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\AssetsByCategoryChart;
use App\Filament\Widgets\AssetStats;
use App\Filament\Widgets\LoansPerWeekChart;
use App\Filament\Widgets\RecentMovements;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Loan;
use App\Models\User;
use App\Services\AssetLedger;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** Drives the real Filament pages, so action wiring and role checks are covered, not only the service. */
class PanelTest extends TestCase
{
    use RefreshDatabase;

    private Department $finance;

    private Department $it;

    private User $officer;

    private User $staff;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finance = Department::factory()->create();
        $this->it = Department::factory()->create();
        $this->category = Category::factory()->create();
        $this->officer = User::factory()->create(['role' => Role::Officer, 'department_id' => $this->finance->id]);
        $this->staff = User::factory()->create(['role' => Role::Staff, 'department_id' => $this->it->id]);
    }

    private function asset(Department $department, string $tag = 'COM-68-0001'): Asset
    {
        return AssetLedger::register(['asset_tag' => $tag, 'name' => 'Notebook', 'category_id' => $this->category->id], $department->id, $this->officer);
    }

    public function test_loan_goes_from_staff_request_to_officer_return_through_the_ui(): void
    {
        $asset = $this->asset($this->it);

        $this->actingAs($this->staff);
        Livewire::test(ManageLoans::class)
            ->callAction(CreateAction::class, data: ['asset_id' => $asset->id, 'due_on' => today()->addDays(3)->toDateString(), 'purpose' => 'ประชุม'])
            ->assertHasNoActionErrors();
        $loan = Loan::sole();
        $this->assertSame($this->staff->id, $loan->requester_id);
        Livewire::test(ManageLoans::class)->assertTableActionHidden('approve', $loan);

        $this->actingAs($this->officer);
        Livewire::test(ManageLoans::class)
            ->callTableAction('approve', $loan)
            ->callTableAction('handOver', $loan);
        $this->assertSame(Availability::OnLoan, $asset->fresh()->availability);

        Livewire::test(ManageLoans::class)
            ->callTableAction('receiveReturn', $loan, data: ['condition' => Condition::Damaged->value, 'note' => 'จอแตก']);

        $this->assertSame(LoanStatus::Returned, $loan->fresh()->status);
        $this->assertSame(Condition::Damaged, $asset->fresh()->condition);
        $this->assertSame(Availability::Available, $asset->fresh()->availability);
    }

    public function test_broken_rule_is_shown_as_a_notification_not_an_error_page(): void
    {
        $asset = $this->asset($this->finance);
        [$first, $second] = Loan::factory()->for($asset)->count(2)->create(['requester_id' => $this->staff->id]);

        $this->actingAs($this->officer);
        Livewire::test(ManageLoans::class)
            ->callTableAction('approve', $first)
            ->callTableAction('approve', $second)
            ->assertNotified('ทรัพย์สินนี้มีคำขอยืมที่อนุมัติหรือส่งมอบแล้ว');

        $this->assertSame(LoanStatus::Pending, $second->fresh()->status);
    }

    public function test_officer_sends_an_asset_to_repair_from_the_table(): void
    {
        $asset = $this->asset($this->finance);

        $this->actingAs($this->officer);
        Livewire::test(ListAssets::class)
            ->callTableAction('sendToRepair', $asset, data: ['vendor' => 'ร้านซ่อม'])
            ->assertTableActionHidden('dispose', $asset);

        $this->assertSame(Availability::InRepair, $asset->fresh()->availability);
        $this->assertSame('ร้านซ่อม', $asset->repairOrders()->sole()->vendor);
    }

    public function test_creating_an_asset_writes_an_opening_balance_movement(): void
    {
        $this->actingAs($this->officer);
        Livewire::test(CreateAsset::class)
            ->fillForm(['asset_tag' => 'COM-68-0099', 'name' => 'iPad', 'category_id' => $this->category->id, 'department_id' => $this->it->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $asset = Asset::sole();
        $this->assertSame($this->it->id, $asset->department_id);
        $this->assertSame(MovementType::OpeningBalance, $asset->movements()->sole()->type);
    }

    public function test_staff_only_see_assets_of_their_own_department(): void
    {
        $own = $this->asset($this->it, 'COM-68-0001');
        $other = $this->asset($this->finance, 'COM-68-0002');

        $this->actingAs($this->staff);
        Livewire::test(ListAssets::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other])
            ->assertTableActionHidden('sendToRepair', $own);

        $this->get(AssetResource::getUrl('view', ['record' => $other]))->assertNotFound();
        $this->get(AssetResource::getUrl('create'))->assertForbidden();
    }

    public function test_admin_creates_a_user_and_other_roles_cannot_open_the_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin, 'department_id' => $this->it->id]));
        Livewire::test(ManageUsers::class)
            ->callAction(CreateAction::class, data: [
                'name' => 'ผู้ใช้ใหม่', 'email' => 'new@demo.test', 'department_id' => $this->finance->id,
                'role' => Role::Officer->value, 'password' => 'secret-pass', 'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $created = User::query()->where('email', 'new@demo.test')->sole();
        $this->assertSame(Role::Officer, $created->role);
        $this->assertTrue(Hash::check('secret-pass', $created->password));

        $this->actingAs($this->officer)->get(UserResource::getUrl())->assertForbidden();
    }

    public function test_admin_cannot_demote_or_deactivate_themselves(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'department_id' => $this->it->id]);

        $this->actingAs($admin);
        Livewire::test(ManageUsers::class)
            ->callTableAction(EditAction::class, $admin, data: ['role' => Role::Staff->value, 'is_active' => false])
            ->assertHasNoTableActionErrors();

        $this->assertSame(Role::Admin, $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_officer_closes_a_repair_from_the_repair_list(): void
    {
        $asset = $this->asset($this->finance);
        $order = AssetLedger::sendToRepair($asset, $this->officer, 'ร้านซ่อม', null);

        $this->actingAs($this->officer);
        Livewire::test(ManageRepairOrders::class)
            ->assertCanSeeTableRecords([$order])
            ->callTableAction('receiveFromRepair', $order, data: ['condition' => Condition::Usable->value, 'cost' => '500'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(Availability::Available, $asset->fresh()->availability);
        $this->assertTrue($order->fresh()->finished_on->isToday());
        $this->assertSame('500.00', $order->fresh()->cost);
        $this->actingAs($this->staff)->get(RepairOrderResource::getUrl())->assertForbidden();
    }

    public function test_bulk_delete_skips_assets_that_are_on_loan(): void
    {
        $idle = $this->asset($this->finance, 'COM-68-0001');
        $onLoan = $this->asset($this->finance, 'COM-68-0002');
        $loan = Loan::factory()->for($onLoan)->create();
        AssetLedger::approve($loan, $this->officer);
        AssetLedger::handOver($loan, $this->officer);

        $this->actingAs(User::factory()->create(['role' => Role::Admin, 'department_id' => $this->it->id]));
        Livewire::test(ListAssets::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$idle, $onLoan]);

        $this->assertSoftDeleted($idle);
        $this->assertNotSoftDeleted($onLoan);
    }

    public function test_restore_is_disabled_when_the_tag_was_reused(): void
    {
        $old = $this->asset($this->finance, 'COM-68-0001');
        $old->delete();
        $this->asset($this->finance, 'COM-68-0001');

        $this->actingAs(User::factory()->create(['role' => Role::Admin, 'department_id' => $this->it->id]));
        Livewire::test(ViewAsset::class, ['record' => $old->id])
            ->assertActionDisabled(RestoreAction::class);
    }

    public function test_shared_demo_accounts_cannot_be_edited_in_demo_mode(): void
    {
        config(['app.demo' => true]);
        $demoOfficer = User::factory()->create(['email' => 'officer@demo.test', 'role' => Role::Officer]);
        $realUser = User::factory()->create();

        $this->actingAs(User::factory()->create(['role' => Role::Admin, 'department_id' => $this->it->id]));
        Livewire::test(ManageUsers::class)
            ->assertTableActionHidden(EditAction::class, $demoOfficer)
            ->assertTableActionVisible(EditAction::class, $realUser);
    }

    public function test_asset_buttons_follow_the_policy_for_each_role(): void
    {
        $asset = $this->asset($this->it);

        $this->actingAs($this->staff);
        Livewire::test(ListAssets::class)
            ->assertActionHidden(CreateAction::class)
            ->assertTableActionHidden(EditAction::class, $asset)
            ->assertTableBulkActionHidden(DeleteBulkAction::class);

        $this->actingAs($this->officer);
        Livewire::test(ListAssets::class)
            ->assertTableActionVisible(EditAction::class, $asset)
            ->assertTableBulkActionHidden(DeleteBulkAction::class);
    }

    public function test_dashboard_widgets_count_overdue_loans_in_the_users_scope(): void
    {
        $asset = $this->asset($this->it);
        $loan = Loan::factory()->for($asset)->create(['due_on' => today()->subDay()]);
        AssetLedger::approve($loan, $this->officer);
        AssetLedger::handOver($loan, $this->officer);

        $this->actingAs($this->officer);
        Livewire::test(AssetStats::class)->assertSee('เกินกำหนดคืน')->assertSee('ต้องติดตามให้คืน');
        Livewire::test(RecentMovements::class)->assertCanSeeTableRecords($asset->movements);
        Livewire::test(LoansPerWeekChart::class)->assertOk();
        Livewire::test(AssetsByCategoryChart::class)->assertOk();

        $weeks = LoansPerWeekChart::weeklyCounts(MovementType::Borrow);
        $this->assertCount(8, $weeks);
        $this->assertSame(1, array_sum($weeks));
        $this->assertSame(1, end($weeks), 'This week\'s hand-over lands in the last bucket.');
    }
}
