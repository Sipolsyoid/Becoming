<?php

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('database rejects a check-in owned by someone other than its habit owner', function () {
    $habit = Habit::factory()->create();
    $other = User::factory()->create();
    expect(fn () => DB::table('habit_completions')->insert([
        'habit_id' => $habit->id, 'user_id' => $other->id, 'completed_on' => '2026-10-08',
    ]))->toThrow(QueryException::class);
    $this->assertDatabaseCount('habit_completions', 0);
});

test('database rejects changing ownership of an existing check-in', function () {
    $check = HabitCompletion::factory()->create();
    $other = User::factory()->create();
    expect(fn () => DB::table('habit_completions')->where('id', $check->id)->update(['user_id' => $other->id]))->toThrow(QueryException::class);
    expect($check->fresh()->user_id)->toBe($check->user_id);
});
