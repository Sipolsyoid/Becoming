<?php

use App\Models\Habit;

test('creating habits respects total and active limits', function () {
    config(['habits.max_total' => 2, 'habits.max_active' => 1]);
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user)->post('/habits', ['name' => 'More active', 'is_daily' => 1])->assertSessionHasErrors('is_daily');
    $this->post('/habits', ['name' => 'Paused', 'is_daily' => 0])->assertSessionHasNoErrors();
    $this->post('/habits', ['name' => 'Too many', 'is_daily' => 0])->assertSessionHasErrors('name');
    expect($habit->user->habits()->count())->toBe(2);
});

test('resume and restore cannot bypass the active cap and pausing frees capacity', function () {
    config(['habits.max_active' => 1]);
    $active = Habit::factory()->create();
    $paused = Habit::factory()->create(['user_id' => $active->user_id, 'is_daily' => false]);
    $archived = Habit::factory()->create(['user_id' => $active->user_id, 'is_daily' => false, 'archived_at' => now(), 'archived_was_active' => true]);
    $this->actingAs($active->user)->patch(route('habits.update', $paused), ['is_daily' => 1])->assertSessionHasErrors('is_daily');
    $this->post(route('habits.restore', $archived))->assertSessionHasErrors('is_daily');
    expect($archived->fresh()->archived_at)->not->toBeNull();
    $this->patch(route('habits.update', $active), ['is_daily' => 0])->assertSessionHasNoErrors();
    $this->post(route('habits.restore', $archived))->assertSessionHasNoErrors();
    expect($archived->fresh()->is_daily)->toBeTrue();
});
