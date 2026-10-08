<?php

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\User;

test('uploads and retries share the per-user hourly limit without affecting other owners', function () {
    config(['habits.photo_requests_per_hour' => 2, 'habits.photo_requests_per_day' => 10]);
    $habit = Habit::factory()->create();
    $check = HabitCompletion::factory()->create(['habit_id' => $habit->id]);
    $this->actingAs($habit->user)->postJson(route('habits.complete', $habit))->assertUnprocessable();
    $this->postJson(route('checks.retry', $check))->assertUnprocessable();
    $this->postJson(route('habits.complete', $habit))->assertStatus(429)->assertJsonStructure(['errors' => ['photo']])->assertHeader('Retry-After');
    $this->actingAs(User::factory()->create())->postJson(route('habits.complete', $habit))->assertNotFound();
    $this->actingAs($habit->user);
    $this->travel(61)->minutes();
    $this->postJson(route('habits.complete', $habit))->assertUnprocessable();
});

test('daily quota remains after the hourly window resets and HTML errors stay readable', function () {
    config(['habits.photo_requests_per_hour' => 10, 'habits.photo_requests_per_day' => 1]);
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user)->postJson(route('habits.complete', $habit))->assertUnprocessable();
    $this->travel(61)->minutes();
    $this->post(route('habits.complete', $habit))->assertStatus(429)->assertSee('photo-check request limit');
    $this->travel(24)->hours();
    $this->postJson(route('habits.complete', $habit))->assertUnprocessable();
});
