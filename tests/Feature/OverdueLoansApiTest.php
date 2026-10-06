<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Department;
use App\Models\Loan;
use App\Models\User;
use App\Services\AssetLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** GET /api/loans/overdue, which the LINE bot reads. */
class OverdueLoansApiTest extends TestCase
{
    use RefreshDatabase;

    private Department $it;

    private Department $finance;

    private User $officer;

    private User $staff;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->it = Department::factory()->create();
        $this->finance = Department::factory()->create();
        $this->category = Category::factory()->create();
        $this->officer = User::factory()->create(['role' => Role::Officer, 'department_id' => $this->finance->id]);
        $this->staff = User::factory()->create(['role' => Role::Staff, 'department_id' => $this->it->id]);
    }

    /** A loan handed over that was due $dueInDays from today (negative: already late). */
    private function handedOver(string $tag, Department $department, int $dueInDays): Loan
    {
        $asset = AssetLedger::register(['asset_tag' => $tag, 'name' => "Item {$tag}", 'category_id' => $this->category->id], $department->id, $this->officer);
        $loan = Loan::factory()->for($asset)->create(['requester_id' => $this->staff->id, 'due_on' => today()->addDays($dueInDays)]);
        AssetLedger::approve($loan, $this->officer);
        AssetLedger::handOver($loan, $this->officer);

        return $loan;
    }

    public function test_a_missing_token_is_401(): void
    {
        $this->getJson('/api/loans/overdue')->assertUnauthorized();
    }

    public function test_officer_sees_late_loans_in_every_department_oldest_first(): void
    {
        $this->handedOver('IT-1', $this->it, -2);
        $this->handedOver('FIN-1', $this->finance, -5);
        $this->handedOver('IT-DUE-TODAY', $this->it, 0);
        $this->handedOver('IT-LATER', $this->it, 3);
        $returned = $this->handedOver('IT-RETURNED', $this->it, -9);
        AssetLedger::receiveReturn($returned, $this->officer, Condition::Usable);
        Sanctum::actingAs($this->officer);

        $this->getJson('/api/loans/overdue')
            ->assertOk()
            ->assertJsonPath('data.*.tag', ['FIN-1', 'IT-1'])
            ->assertJsonPath('data.0.days_overdue', 5)
            ->assertJsonPath('data.0.borrower', $this->staff->name)
            ->assertJsonPath('data.0.name', 'Item FIN-1');
    }

    public function test_staff_see_only_their_own_departments_late_loans(): void
    {
        $this->handedOver('IT-1', $this->it, -2);
        $this->handedOver('FIN-1', $this->finance, -5);
        Sanctum::actingAs($this->staff);

        $this->getJson('/api/loans/overdue')->assertOk()->assertJsonPath('data.*.tag', ['IT-1']);
    }
}
