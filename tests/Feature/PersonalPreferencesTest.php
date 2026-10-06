<?php

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\User;
use App\Notifications\HabitReminder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

test('preferences require authentication and only update the signed in user', function () {
    $this->get('/settings')->assertRedirect('/login');
    $this->patch('/settings')->assertRedirect('/login');
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user)->get('/settings')->assertOk()->assertSee('Email reminders');
    $this->patch('/settings', ['timezone' => 'Europe/Riga', 'reminders_enabled' => 1, 'reminder_time' => '19:30', 'id' => $other->id])
        ->assertRedirect('/settings')->assertSessionHasNoErrors();
    expect($user->fresh())->timezone->toBe('Europe/Riga')->reminders_enabled->toBeTrue()->reminder_time->toBe('19:30');
    expect($other->fresh())->timezone->toBe('UTC')->reminders_enabled->toBeFalse();
    $this->patch('/settings', ['timezone' => 'Europe/Riga', 'reminders_enabled' => 0])->assertSessionHasNoErrors();
    expect($user->fresh())->reminders_enabled->toBeFalse()->reminder_time->toBe('19:30');
});

test('preferences reject invalid timezone and reminder values', function (array $invalid, string $field) {
    $user = User::factory()->create();
    $this->actingAs($user)->patch('/settings', array_replace([
        'timezone' => 'Europe/Riga', 'reminders_enabled' => 1, 'reminder_time' => '18:00',
    ], $invalid))->assertSessionHasErrors($field);
    expect($user->fresh()->timezone)->toBe('UTC');
})->with([
    [['timezone' => 'Mars/Olympus'], 'timezone'],
    [['reminder_time' => '25:00'], 'reminder_time'],
    [['reminder_time' => null], 'reminder_time'],
    [['reminders_enabled' => 'yes'], 'reminders_enabled'],
]);

test('dashboard progress and history use each users local date across UTC midnight', function () {
    $this->travelTo(Carbon::parse('2026-10-04 22:30:00', 'UTC'));
    $east = User::factory()->create(['timezone' => 'Europe/Riga']); // Monday
    $west = User::factory()->create(['timezone' => 'America/Los_Angeles']); // Sunday
    $habit = Habit::factory()->create(['user_id' => $east->id, 'schedule_type' => 'weekdays', 'weekdays' => [1]]);
    HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => '2026-10-05', 'ai_status' => 'approved']);
    $weekly = Habit::factory()->create(['user_id' => $east->id, 'schedule_type' => 'weekly', 'weekly_target' => 2]);
    HabitCompletion::factory()->create(['habit_id' => $weekly->id, 'completed_on' => '2026-10-04', 'ai_status' => 'approved']);
    $this->actingAs($east)->get('/')->assertViewHas('today', fn ($date) => $date->toDateString() === '2026-10-05')
        ->assertViewHas('progressPercent', 100)->assertViewHas('weeklyGoals', fn ($goals) => $goals->first()['done'] === 0);
    $this->get('/progress')->assertViewHas('today', fn ($date) => $date->toDateString() === '2026-10-05');
    $this->get('/history')->assertViewHas('days', fn ($days) => $days[0]['date']->toDateString() === '2026-10-05');
    $this->actingAs($west)->get('/')->assertViewHas('today', fn ($date) => $date->toDateString() === '2026-10-04');
});

test('photo approval uses the local weekday and completion date', function () {
    config(['queue.connections.photos.driver' => 'sync']);
    $this->travelTo(Carbon::parse('2026-10-04 22:30:00', 'UTC'));
    Storage::fake('local');
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['message' => ['content' => '{"decision":"approved","reason":"Visible activity","visible_evidence":"Photo evidence"}']])]);
    $user = User::factory()->create(['timezone' => 'Europe/Riga']);
    $habit = Habit::factory()->create(['user_id' => $user->id, 'schedule_type' => 'weekdays', 'weekdays' => [1]]);
    $photo = UploadedFile::fake()->createWithContent('photo.jpg', file_get_contents(public_path('img/logo.jpeg')));
    $this->actingAs($user)->post('/habits/'.$habit->id.'/complete-with-photo', ['photo' => $photo])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('habit_completions', ['habit_id' => $habit->id, 'completed_on' => '2026-10-05', 'ai_status' => 'approved']);
    $this->patch('/settings', ['timezone' => 'America/Los_Angeles', 'reminders_enabled' => 0]);
    $this->assertDatabaseHas('habit_completions', ['habit_id' => $habit->id, 'completed_on' => '2026-10-05', 'ai_status' => 'approved']);
});

test('reminders send after the chosen local time once per date and respect opt out', function () {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-10-05 15:59:00', 'UTC'));
    $user = User::factory()->create(['timezone' => 'Europe/Riga', 'reminders_enabled' => true, 'reminder_time' => '19:00']);
    Habit::factory()->create(['user_id' => $user->id]);
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertNothingSent();
    $this->travelTo(Carbon::parse('2026-10-05 16:00:00', 'UTC'));
    $this->artisan('habits:send-reminders')->assertSuccessful();
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertSentToTimes($user, HabitReminder::class, 1);
    expect($user->fresh()->reminder_last_sent_on->toDateString())->toBe('2026-10-05');
    $this->travelTo(Carbon::parse('2026-10-06 16:00:00', 'UTC'));
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertSentToTimes($user, HabitReminder::class, 2);
    $user->update(['reminders_enabled' => false]);
    $this->travelTo(Carbon::parse('2026-10-07 16:00:00', 'UTC'));
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertSentToTimes($user, HabitReminder::class, 2);
});

test('reminder digest filters schedules completions and reached weekly goals', function () {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-10-06 18:00:00', 'UTC')); // Tuesday
    $user = User::factory()->create(['reminders_enabled' => true]);
    $pending = Habit::factory()->create(['user_id' => $user->id]);
    Habit::factory()->create(['user_id' => $user->id, 'is_daily' => false]);
    Habit::factory()->create(['user_id' => $user->id, 'schedule_type' => 'weekdays', 'weekdays' => [1]]);
    $done = Habit::factory()->create(['user_id' => $user->id]);
    HabitCompletion::factory()->create(['habit_id' => $done->id, 'completed_on' => '2026-10-06', 'ai_status' => 'approved']);
    $reached = Habit::factory()->create(['user_id' => $user->id, 'schedule_type' => 'weekly', 'weekly_target' => 1]);
    HabitCompletion::factory()->create(['habit_id' => $reached->id, 'completed_on' => '2026-10-05', 'ai_status' => 'approved']);
    $weekly = Habit::factory()->create(['user_id' => $user->id, 'schedule_type' => 'weekly', 'weekly_target' => 3]);
    HabitCompletion::factory()->create(['habit_id' => $weekly->id, 'completed_on' => '2026-10-06', 'ai_status' => 'rejected']);
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertSentTo($user, HabitReminder::class, function ($mail) use ($pending, $weekly) {
        expect($mail->habitNames)->toEqualCanonicalizing([$pending->name, $weekly->name]);

        return true;
    });
});

test('empty and rest days do not send email', function () {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-10-06 18:00:00', 'UTC'));
    User::factory()->create(['reminders_enabled' => true]);
    $user = User::factory()->create(['reminders_enabled' => true]);
    Habit::factory()->create(['user_id' => $user->id, 'schedule_type' => 'weekdays', 'weekdays' => [1]]);
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertNothingSent();
});

test('daylight saving skipped and repeated times send at most once per local day', function () {
    Notification::fake();
    $user = User::factory()->create(['timezone' => 'Europe/Riga', 'reminders_enabled' => true, 'reminder_time' => '03:30']);
    Habit::factory()->create(['user_id' => $user->id]);
    // Spring forward skips 03:30; catch up at 04:00.
    $this->travelTo(Carbon::parse('2026-03-29 01:00:00', 'UTC'));
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertSentToTimes($user, HabitReminder::class, 1);
    // Both occurrences of 03:30 in autumn share a date.
    $this->travelTo(Carbon::parse('2026-10-25 00:30:00', 'UTC'));
    $this->artisan('habits:send-reminders')->assertSuccessful();
    $this->travelTo(Carbon::parse('2026-10-25 01:30:00', 'UTC'));
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertSentToTimes($user, HabitReminder::class, 2);
});

test('overlapping reminder runs skip locked users', function () {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-10-06 18:00:00', 'UTC'));
    $user = User::factory()->create(['reminders_enabled' => true]);
    Habit::factory()->create(['user_id' => $user->id]);
    $lock = Cache::lock('habit-reminder:'.$user->id, 300);
    $lock->get();
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertNothingSent();
    $lock->release();
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertSentToTimes($user, HabitReminder::class, 1);
});

test('mail failures stay retryable and release the user lock', function () {
    $this->travelTo(Carbon::parse('2026-10-06 18:00:00', 'UTC'));
    $user = User::factory()->create(['reminders_enabled' => true]);
    Habit::factory()->create(['user_id' => $user->id]);
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Mail unavailable'));
    $this->artisan('habits:send-reminders')->assertFailed();
    expect($user->fresh()->reminder_last_sent_on)->toBeNull();
    Notification::fake();
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertSentToTimes($user, HabitReminder::class, 1);
});

test('email renders habit names and a settings link for disabling reminders', function () {
    $user = User::factory()->create(['timezone' => 'Europe/Riga']);
    $mail = (new HabitReminder(['Read a book'], '2026-10-06'))->toMail($user);
    $html = (string) $mail->render();
    expect($html)->toContain('Read a book', 'Europe/Riga', route('settings.edit'));
});
