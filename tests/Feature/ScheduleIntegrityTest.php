<?php

use App\Models\Habit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('models reject invalid schedules before saving them', function () {
    expect(fn () => Habit::factory()->create(['schedule_type' => 'weekly', 'weekly_target' => 8]))->toThrow(ValidationException::class);
    expect(fn () => Habit::factory()->create(['schedule_type' => 'weekdays', 'weekdays' => [1, 1]]))->toThrow(ValidationException::class);
});

test('database rejects invalid schedule changes on habits and history snapshots', function (array $values) {
    $habit = Habit::factory()->create();
    foreach (['habits', 'habit_schedule_versions'] as $table) {
        expect(fn () => DB::table($table)->where($table === 'habits' ? 'id' : 'habit_id', $habit->id)->update($values))->toThrow(QueryException::class);
    }
})->with([
    'unknown type' => [['schedule_type' => 'unknown']],
    'missing weekly target' => [['schedule_type' => 'weekly']],
    'oversized target' => [['schedule_type' => 'weekly', 'weekly_target' => 8]],
    'empty weekdays' => [['schedule_type' => 'weekdays', 'weekdays' => '[]']],
    'out of range weekday' => [['schedule_type' => 'weekdays', 'weekdays' => '[8]']],
    'duplicate weekdays' => [['schedule_type' => 'weekdays', 'weekdays' => '[1,1]']],
    'string weekday' => [['schedule_type' => 'weekdays', 'weekdays' => '["1"]']],
]);

test('weekday model values are normalized for strict historical comparisons', function () {
    $habit = Habit::factory()->create(['schedule_type' => 'weekdays', 'weekdays' => ['5', '1']]);
    expect($habit->fresh()->weekdays)->toBe([1, 5]);
    expect($habit->scheduleVersions()->first()->weekdays)->toBe([1, 5]);
});
