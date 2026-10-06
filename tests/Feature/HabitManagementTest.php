<?php

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\User;
use App\Services\HabitMetrics;

test('owners can edit details with unique names and keep completion history', function () {
    $habit = Habit::factory()->create();
    $completion = HabitCompletion::factory()->create(['habit_id' => $habit->id]);
    $this->actingAs($habit->user)->patch(route('habits.update', $habit), ['name' => 'Read every day', 'category' => 'Learning'])->assertSessionHasNoErrors();
    expect($habit->fresh()->name)->toBe('Read every day');
    expect($completion->fresh())->not->toBeNull();
    Habit::factory()->create(['user_id' => $habit->user_id, 'name' => 'Taken']);
    $this->patch(route('habits.update', $habit), ['name' => 'Taken'])->assertSessionHasErrors('name');
    $this->patch(route('habits.update', $habit), ['name' => ' '])->assertSessionHasErrors('name');
});

test('archive and restore preserve records and previous activation state', function () {
    foreach ([true, false] as $active) {
        $habit = Habit::factory()->create(['is_daily' => $active]);
        $completion = HabitCompletion::factory()->create(['habit_id' => $habit->id]);
        $this->actingAs($habit->user)->post(route('habits.archive', $habit))->assertRedirect();
        $this->post(route('habits.archive', $habit))->assertRedirect();
        expect($habit->fresh()->archived_at)->not->toBeNull();
        expect($habit->fresh()->is_daily)->toBeFalse();
        expect($completion->fresh())->not->toBeNull();
        $this->get('/habits')->assertViewHas('habits', fn ($habits) => $habits->isEmpty());
        $this->get('/habits?status=archived')->assertSee('Restore');
        $this->patch(route('habits.update', $habit), ['is_daily' => true])->assertStatus(409);
        $this->post(route('habits.restore', $habit))->assertRedirect();
        expect($habit->fresh()->archived_at)->toBeNull();
        expect($habit->fresh()->is_daily)->toBe($active);
    }
});

test('habit ordering persists and ignores archived neighbours', function () {
    $user = User::factory()->create();
    $a = Habit::factory()->create(['user_id' => $user->id]);
    $archived = Habit::factory()->create(['user_id' => $user->id]);
    $b = Habit::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user)->post(route('habits.archive', $archived));
    $this->post(route('habits.move', $b), ['direction' => 'up'])->assertRedirect();
    $this->get('/habits')->assertViewHas('habits', fn ($habits) => $habits->pluck('id')->all() === [$b->id, $a->id]);
    expect(app(HabitMetrics::class)->dailyHabits($user)->pluck('id')->all())->toBe([$b->id, $a->id]);
    $this->post(route('habits.move', $b), ['direction' => 'up'])->assertRedirect();
    $this->post(route('habits.move', $b), ['direction' => 'invalid'])->assertSessionHasErrors('direction');
});

test('search and filters are scoped to the signed in owner', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->create(['user_id' => $user->id, 'name' => 'Read books', 'category' => 'Learning']);
    Habit::factory()->create(['name' => 'Read private books', 'category' => 'Learning']);
    Habit::factory()->create(['user_id' => $user->id, 'name' => 'Read news', 'category' => 'Other', 'is_daily' => false]);
    $this->actingAs($user)->get('/habits?q=Read&category=Learning&status=active')
        ->assertOk()->assertViewHas('habits', fn ($habits) => $habits->pluck('id')->all() === [$habit->id]);
    $this->get('/habits?status=bad')->assertSessionHasErrors('status');
});

test('habit management rejects access to another users habits', function () {
    $habit = Habit::factory()->create();
    $this->actingAs(User::factory()->create());
    foreach (['archive', 'restore', 'move'] as $action) {
        $this->post(route('habits.'.$action, $habit), ['direction' => 'up'])->assertNotFound();
    }
    $this->patch(route('habits.update', $habit), ['name' => 'Changed'])->assertNotFound();
});
