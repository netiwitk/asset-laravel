<?php

namespace Tests\Feature;

use App\Enums\LoanAction;
use App\Enums\LoanStatus;
use App\Enums\Role;
use App\Models\Loan;
use App\Models\User;
use Tests\TestCase;

class LoanActionTest extends TestCase
{
    /**
     * Every action / status / person combination not listed here must be refused.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const ALLOWED = [
        'approve' => ['pending' => ['officer', 'admin']],
        'reject' => ['pending' => ['officer', 'admin']],
        'hand_over' => ['approved' => ['officer', 'admin']],
        'receive_return' => ['handed_over' => ['officer', 'admin']],
        'cancel' => ['pending' => ['officer', 'admin', 'requester'], 'approved' => ['officer', 'admin', 'requester']],
    ];

    public function test_each_loan_button_allows_exactly_the_listed_people_and_statuses(): void
    {
        $people = [
            'officer' => User::factory()->make(['id' => 1, 'role' => Role::Officer]),
            'admin' => User::factory()->make(['id' => 2, 'role' => Role::Admin]),
            'requester' => User::factory()->make(['id' => 3, 'role' => Role::Staff]),
            'other staff' => User::factory()->make(['id' => 4, 'role' => Role::Staff]),
        ];

        foreach (LoanAction::cases() as $action) {
            foreach (LoanStatus::cases() as $status) {
                $loan = (new Loan)->forceFill(['status' => $status, 'requester_id' => 3]);
                foreach ($people as $who => $user) {
                    $this->assertSame(
                        in_array($who, self::ALLOWED[$action->value][$status->value] ?? [], true),
                        $action->allows($user, $loan),
                        "{$action->value} / {$status->value} / {$who}",
                    );
                }
            }
        }
    }
}
