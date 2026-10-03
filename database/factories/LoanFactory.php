<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending loan request. Always pass the asset: Loan::factory()->for($asset)->create().
 * Assets themselves have no factory on purpose; they are created through AssetLedger::register()
 * so every asset starts with its opening_balance movement.
 *
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'borrower_id' => fn (array $attributes) => $attributes['requester_id'],
            'requested_at' => now(),
            'due_on' => today()->addWeek(),
        ];
    }
}
