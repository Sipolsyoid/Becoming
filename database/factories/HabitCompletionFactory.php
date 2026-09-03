<?php

namespace Database\Factories;

use App\Models\Habit;
use App\Models\HabitCompletion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HabitCompletion>
 */
class HabitCompletionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = HabitCompletion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'habit_id' => Habit::factory(),
            'user_id' => function (array $attributes) {
                return Habit::query()->findOrFail($attributes['habit_id'])->user_id;
            },
            'completed_on' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
        ];
    }
}

