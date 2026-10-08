<?php

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\User;
use App\Services\HabitMetrics;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['queue.connections.photos.driver' => 'sync']);
    $this->travelTo(now()->setDate(2026, 10, 5)->startOfDay()); // Monday
    Http::preventStrayRequests();
});

test('users can create and change schedules without losing records', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/habits', ['name' => 'Exercise', 'is_daily' => 1, 'schedule_type' => 'weekdays', 'weekdays' => ['5', '1', '3']])->assertSessionHasNoErrors();
    $habit = $user->habits()->sole();
    expect($habit->weekdays)->toBe([1, 3, 5]);
    HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => today(), 'ai_status' => 'approved']);
    $this->patch('/habits/'.$habit->id, ['schedule_type' => 'weekly', 'weekly_target' => 3])->assertSessionHasNoErrors();
    expect($habit->fresh()->weekly_target)->toBe(3);
    expect($habit->fresh()->weekdays)->toBeNull();
    expect($habit->completions()->count())->toBe(1);
    $this->patch('/habits/'.$habit->id, ['is_daily' => 0]);
    expect($habit->fresh()->schedule_type)->toBe('weekly');
    $this->patch('/habits/'.$habit->id, ['is_daily' => 1]);
    expect($habit->fresh()->weekly_target)->toBe(3);
});

test('invalid schedules are rejected', function (array $schedule) {
    $this->actingAs(User::factory()->create())->post('/habits', ['name' => 'Test', 'is_daily' => 1, ...$schedule])->assertSessionHasErrors();
    $this->assertDatabaseCount('habits', 0);
})->with([
    [['schedule_type' => 'unknown']],
    [['schedule_type' => 'weekdays', 'weekdays' => []]],
    [['schedule_type' => 'weekdays', 'weekdays' => [0, 8]]],
    [['schedule_type' => 'weekdays', 'weekdays' => [1, 1]]],
    [['schedule_type' => 'weekly', 'weekly_target' => 0]],
    [['schedule_type' => 'weekly', 'weekly_target' => 8]],
    [['schedule_type' => 'weekly', 'weekly_target' => 2.5]],
]);

test('schedule updates check ownership and validate before changing data', function () {
    $habit = Habit::factory()->create();
    $this->actingAs(User::factory()->create())->patch('/habits/'.$habit->id, ['schedule_type' => 'weekly', 'weekly_target' => 2])->assertNotFound();
    $this->actingAs($habit->user)->patch('/habits/'.$habit->id, ['schedule_type' => 'weekdays'])->assertSessionHasErrors('weekdays');
    expect($habit->fresh()->schedule_type)->toBe('daily');
});

test('selected weekdays drive dashboard and date-specific denominators', function () {
    $user = User::factory()->create();
    $monday = Habit::factory()->create(['user_id' => $user->id, 'schedule_type' => 'weekdays', 'weekdays' => [1], 'created_at' => today()->subDays(7)]);
    $tuesday = Habit::factory()->create(['user_id' => $user->id, 'schedule_type' => 'weekdays', 'weekdays' => [2], 'created_at' => today()->subDays(7)]);
    HabitCompletion::factory()->create(['habit_id' => $monday->id, 'completed_on' => today(), 'ai_status' => 'approved']);
    // An old approval on a day no longer scheduled must not inflate percentages.
    HabitCompletion::factory()->create(['habit_id' => $tuesday->id, 'completed_on' => today(), 'ai_status' => 'approved']);
    $this->actingAs($user)->get('/')->assertViewHas('totalCount', 1)->assertViewHas('progressPercent', 100);
    $this->get('/progress')->assertViewHas('weeklyPercent', 50);
    $this->get('/history')->assertViewHas('days', fn ($days) => $days[0]['total'] === 1 && $days[0]['done'] === 1 && $days[1]['total'] === 0);
});

test('rest days preserve streaks without adding to them', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->create(['user_id' => $user->id, 'schedule_type' => 'weekdays', 'weekdays' => [1, 5], 'created_at' => today()->subDays(7)]);
    foreach ([0, 3] as $days) {
        HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => today()->subDays($days), 'ai_status' => 'approved']);
    }
    $this->actingAs($user)->get('/')->assertViewHas('streak', 2);
    $this->travelTo(today()->addDay());
    $this->get('/')->assertViewHas('totalCount', 0)->assertViewHas('streak', 2);
    $this->travelTo(today()->addDays(3)); // Friday is still in progress.
    $this->get('/')->assertViewHas('streak', 2);
    $this->travelTo(today()->addDay());
    $this->get('/')->assertViewHas('streak', 0);
});

test('weekly goals reset Monday and stay outside daily statistics', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->create(['user_id' => $user->id, 'schedule_type' => 'weekly', 'weekly_target' => 3]);
    foreach ([0, 1] as $days) {
        HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => today()->subDays($days), 'ai_status' => 'approved']);
    }
    $this->actingAs($user)->get('/')->assertViewHas('totalCount', 0)->assertViewHas('weeklyGoals', fn ($goals) => $goals->first()['done'] === 1);
    $this->get('/progress')->assertViewHas('weeklyPercent', 0)->assertViewHas('bestStreak', 0);
    $this->travelTo(today()->addWeek());
    expect(app(HabitMetrics::class)->weeklyGoals($user, today())->first()['done'])->toBe(0);
});

test('off-day and paused uploads are rejected before inference', function () {
    $habit = Habit::factory()->create(['schedule_type' => 'weekdays', 'weekdays' => [2]]);
    $this->actingAs($habit->user)->post('/habits/'.$habit->id.'/complete-with-photo')->assertSessionHasErrors('photo');
    $habit->update(['is_daily' => false, 'schedule_type' => 'weekly', 'weekly_target' => 2, 'weekdays' => null]);
    $this->post('/habits/'.$habit->id.'/complete-with-photo')->assertSessionHasErrors('photo');
    Http::assertNothingSent();
});

test('a reached weekly goal rejects a new day upload', function () {
    $this->travelTo(today()->addDay());
    $habit = Habit::factory()->create(['schedule_type' => 'weekly', 'weekly_target' => 1, 'created_at' => today()->subDay()]);
    HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => today()->subDay(), 'ai_status' => 'approved']);
    $photo = UploadedFile::fake()->createWithContent('photo.jpg', file_get_contents(public_path('img/logo.jpeg')));
    $this->actingAs($habit->user)->post('/habits/'.$habit->id.'/complete-with-photo', ['photo' => $photo])->assertSessionHasErrors('photo');
    expect($habit->completions()->count())->toBe(1);
    Http::assertNothingSent();
});

test('scheduled uploads approve once per day and retries do not double count', function (array $schedule) {
    Storage::fake('local');
    Http::fake(['*' => Http::response(['message' => ['content' => '{"decision":"approved","reason":"Visible activity","visible_evidence":"Photo evidence"}']])]);
    $habit = Habit::factory()->create($schedule);
    $this->actingAs($habit->user);
    for ($i = 0; $i < 2; $i++) {
        $photo = UploadedFile::fake()->createWithContent('photo.jpg', file_get_contents(public_path('img/logo.jpeg')));
        $this->post('/habits/'.$habit->id.'/complete-with-photo', ['photo' => $photo])->assertSessionHasNoErrors();
    }
    expect($habit->completions()->count())->toBe(1);
    expect($habit->completions()->sole()->ai_status)->toBe('approved');
})->with([
    [['schedule_type' => 'weekly', 'weekly_target' => 1]],
    [['schedule_type' => 'weekdays', 'weekdays' => [1, 5]]],
]);
