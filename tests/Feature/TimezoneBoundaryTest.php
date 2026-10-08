<?php

use App\Contracts\PhotoVerifier;
use App\Jobs\VerifyHabitPhoto;
use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('changing timezone while checking preserves the captured check-in date', function () {
    Storage::fake('local');
    Queue::fake();
    $this->travelTo(Carbon::parse('2026-10-08 00:30:00', 'UTC'));
    $user = User::factory()->create(['timezone' => 'Europe/Riga']);
    $habit = Habit::factory()->create(['user_id' => $user->id, 'created_at' => '2026-10-01 00:00:00']);
    $photo = UploadedFile::fake()->createWithContent('photo.jpg', file_get_contents(public_path('img/logo.jpeg')));
    $this->actingAs($user)->postJson(route('habits.complete', $habit), ['photo' => $photo])->assertAccepted();
    $check = $habit->completions()->sole();
    $this->patch(route('settings.update'), ['timezone' => 'America/Los_Angeles', 'reminders_enabled' => 0])->assertSessionHasNoErrors();
    $verifier = Mockery::mock(PhotoVerifier::class);
    $verifier->shouldReceive('verify')->once()->andReturn(['decision' => 'approved', 'reason' => 'Visible', 'visible_evidence' => 'Visible']);
    (new VerifyHabitPhoto($check->id, $check->verification_token))->handle($verifier);
    expect($check->fresh()->completed_on->toDateString())->toBe('2026-10-08');
    $this->getJson('/')->assertJsonPath('date', '2026-10-07')->assertJsonPath('completed', 0);
    $this->travelTo(Carbon::parse('2026-10-08 08:00:00', 'UTC'));
    $this->getJson('/')->assertJsonPath('date', '2026-10-08')->assertJsonPath('completed', 1);
});

test('timezone changes recalculate the current week without moving recorded approvals', function () {
    $this->travelTo(Carbon::parse('2026-10-05 00:30:00', 'UTC'));
    $user = User::factory()->create(['timezone' => 'Europe/Riga']);
    $habit = Habit::factory()->create(['user_id' => $user->id, 'schedule_type' => 'weekly', 'weekly_target' => 1, 'created_at' => '2026-09-30 00:00:00']);
    $check = HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => '2026-10-04', 'ai_status' => 'approved']);
    $this->actingAs($user)->getJson('/')->assertJsonPath('weekly.'.$habit->id, 0);
    $this->patch(route('settings.update'), ['timezone' => 'America/Los_Angeles', 'reminders_enabled' => 0])->assertSessionHasNoErrors();
    $this->getJson('/')->assertJsonPath('date', '2026-10-04')->assertJsonPath('weekly.'.$habit->id, 1);
    expect($check->fresh()->completed_on->toDateString())->toBe('2026-10-04');
});
