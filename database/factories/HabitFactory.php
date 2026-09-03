<?php

namespace Database\Factories;

use App\Models\Habit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Habit>
 */
class HabitFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Habit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'category' => fake()->optional()->word(),
            'is_daily' => true,
        ];
    }

    public function notDaily(): static
    {
        return $this->state(fn () => [
            'is_daily' => false,
        ]);
    }
}

