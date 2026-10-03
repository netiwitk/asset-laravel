<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('C-###'),
            'name' => 'หมวด '.fake()->unique()->numberBetween(1, 999),
            'useful_life_years' => fake()->numberBetween(3, 10),
        ];
    }
}
