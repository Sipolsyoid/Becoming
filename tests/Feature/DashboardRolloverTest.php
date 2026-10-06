<?php

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\User;
use Illuminate\Support\Carbon;

test('yesterday approval does not mark a daily habit finished after local midnight', function () {
    $this->travelTo(Carbon::parse('2026-10-06 20:59:00', 'UTC'));
    $user = User::factory()->create(['timezone' => 'Europe/Riga']);
    $habit = Habit::factory()->create(['user_id' => $user->id]);
    HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => '2026-10-06', 'ai_status' => 'approved', 'verification_status' => 'approved']);
    $this->actingAs($user)->getJson('/')->assertJsonPath('completed', 1)->assertJsonPath('date', '2026-10-06');
    $this->travelTo(Carbon::parse('2026-10-06 21:01:00', 'UTC'));
    $this->getJson('/')->assertJsonPath('date', '2026-10-07')->assertJsonPath('completed', 0)->assertJsonPath('ids', []);
    $this->get('/')->assertViewHas('photoChecks', fn ($checks) => $checks->isEmpty())
        ->assertViewHas('completedHabitIds', [])->assertSee('Photo for '.$habit->name);
});

test('rollover duration respects a 25 hour local daylight saving day', function () {
    $this->travelTo(Carbon::parse('2026-10-24 21:00:00', 'UTC'));
    $user = User::factory()->create(['timezone' => 'Europe/Riga']);
    $this->actingAs($user)->getJson('/')->assertJsonPath('date', '2026-10-25')
        ->assertJsonPath('dayEndsInMs', 90000000);
});
