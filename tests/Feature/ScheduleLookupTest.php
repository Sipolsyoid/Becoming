<?php

use App\Models\Habit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

test('long schedule history uses the correct version at creation edits gaps and final dates without extra queries', function () {
    $habit = Habit::factory()->create(['created_at' => '2026-01-01 00:00:00']);
    for ($day = 2; $day <= 180; $day += 2) {
        $habit->scheduleVersions()->create(['effective_on' => Carbon::parse('2026-01-01')->addDays($day)->toDateString(),
            'is_active' => true, 'schedule_type' => 'weekly', 'weekly_target' => ($day % 7) + 1]);
    }
    $habit->load('scheduleVersions');
    DB::enableQueryLog();
    DB::flushQueryLog();
    expect($habit->scheduleOn(Carbon::parse('2025-12-31')))->toBeNull();
    expect($habit->scheduleOn(Carbon::parse('2026-01-01'))->schedule_type)->toBe('daily');
    expect($habit->scheduleOn(Carbon::parse('2026-01-03'))->weekly_target)->toBe(3);
    expect($habit->scheduleOn(Carbon::parse('2026-01-04'))->weekly_target)->toBe(3);
    expect($habit->scheduleOn(Carbon::parse('2026-12-31'))->weekly_target)->toBe(6);
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
});
