<?php

use App\Jobs\VerifyHabitPhoto;
use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\User;
use App\Services\HabitPhotoVerifier;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    app()->instance('test.real.queue', Queue::getFacadeRoot());
    Queue::fake();
    Http::preventStrayRequests();
});

function asyncPhoto(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('proof.jpg', file_get_contents(public_path('img/logo.jpeg')));
}

test('upload returns queued immediately without calling Ollama or crediting progress', function () {
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user)->postJson(route('habits.complete', $habit), ['photo' => asyncPhoto()])
        ->assertAccepted()->assertJsonPath('status', 'queued')->assertJsonPath('approved', false);
    $completion = $habit->completions()->sole();
    Storage::disk('local')->assertExists($completion->pending_photo_path);
    Queue::assertPushedOn('photos', VerifyHabitPhoto::class);
    Http::assertNothingSent();
    $this->getJson('/')->assertJsonPath('completed', 0);
    $this->get('/')->assertOk()->assertSee('queued');
});

test('a background job credits the original local date even after midnight and is idempotent', function () {
    $this->travelTo(now()->setDate(2026, 10, 6)->setTime(20, 59));
    $habit = Habit::factory()->create(['user_id' => User::factory()->create(['timezone' => 'Europe/Riga'])->id]);
    $this->actingAs($habit->user)->postJson(route('habits.complete', $habit), ['photo' => asyncPhoto()]);
    $completion = $habit->completions()->sole();
    $date = $completion->completed_on->toDateString();
    $this->travel(2)->hours();
    $verifier = Mockery::mock(HabitPhotoVerifier::class);
    $verifier->shouldReceive('verify')->once()->andReturn(['decision' => 'approved', 'reason' => 'Visible activity.', 'visible_evidence' => 'Evidence']);
    $job = new VerifyHabitPhoto($completion->id, $completion->verification_token);
    $job->handle($verifier);
    $job->handle($verifier);
    expect($completion->fresh()->completed_on->toDateString())->toBe($date);
    $this->getJson(route('checks.show', $completion))->assertJsonPath('status', 'approved')->assertJsonPath('approved', true);
    expect($completion->fresh()->pending_photo_path)->toBeNull();
});

test('duplicate uploads cannot replace an in-flight check', function () {
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user)->postJson(route('habits.complete', $habit), ['photo' => asyncPhoto()]);
    $completion = $habit->completions()->sole();
    $this->postJson(route('habits.complete', $habit), ['photo' => asyncPhoto()])->assertUnprocessable();
    expect($completion->fresh()->verification_token)->toBe($completion->verification_token);
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
    Queue::assertPushed(VerifyHabitPhoto::class, 1);
});

test('failed checks preserve prior approvals and can retry without uploading again', function () {
    $habit = Habit::factory()->create();
    $old = HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => $habit->user->localToday(), 'ai_status' => 'approved']);
    $this->actingAs($habit->user)->postJson(route('habits.complete', $habit), ['photo' => asyncPhoto()]);
    $completion = $old->fresh();
    $verifier = Mockery::mock(HabitPhotoVerifier::class);
    $verifier->shouldReceive('verify')->once()->andThrow(new RuntimeException('Ollama is not running.'));
    (new VerifyHabitPhoto($completion->id, $completion->verification_token))->handle($verifier);
    $this->getJson(route('checks.show', $completion))->assertJsonPath('status', 'failed')->assertJsonPath('can_retry', true)->assertJsonPath('approved', true);
    $this->postJson(route('checks.retry', $completion))->assertAccepted()->assertJsonPath('status', 'queued');
    expect($completion->fresh()->pending_photo_path)->toBe($completion->pending_photo_path);
    expect($completion->fresh()->verification_token)->not->toBe($completion->verification_token);
    Queue::assertPushed(VerifyHabitPhoto::class, 2);
});

test('stale checks can be recovered and an old worker cannot overwrite a newer attempt', function () {
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user)->postJson(route('habits.complete', $habit), ['photo' => asyncPhoto()]);
    $completion = $habit->completions()->sole();
    $this->postJson(route('checks.retry', $completion))->assertUnprocessable();
    $verifier = Mockery::mock(HabitPhotoVerifier::class);
    $verifier->shouldReceive('verify')->once()->andReturnUsing(function () use ($completion) {
        $this->travel(11)->minutes();
        $this->postJson(route('checks.retry', $completion))->assertAccepted();

        return ['decision' => 'approved', 'reason' => 'Old result', 'visible_evidence' => 'Old evidence'];
    });
    (new VerifyHabitPhoto($completion->id, $completion->verification_token))->handle($verifier);
    expect($completion->fresh()->verification_status)->toBe('queued');
    expect($completion->fresh()->ai_status)->toBe('pending');
});

test('check status and retry routes protect ownership and hide private file paths', function () {
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user)->postJson(route('habits.complete', $habit), ['photo' => asyncPhoto()]);
    $completion = $habit->completions()->sole();
    $this->getJson(route('checks.show', $completion))->assertOk()->assertJsonMissingPath('pending_photo_path')->assertJsonMissingPath('verification_token');
    $this->actingAs(User::factory()->create());
    $this->getJson(route('checks.show', $completion))->assertNotFound();
    $this->postJson(route('checks.retry', $completion))->assertNotFound();
});

test('deleted habits make queued jobs harmless', function () {
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user)->postJson(route('habits.complete', $habit), ['photo' => asyncPhoto()]);
    $completion = $habit->completions()->sole();
    $habit->delete();
    $verifier = Mockery::mock(HabitPhotoVerifier::class);
    $verifier->shouldNotReceive('verify');
    (new VerifyHabitPhoto($completion->id, $completion->verification_token))->handle($verifier);
    $this->assertDatabaseCount('habit_completions', 0);
});

test('queue dispatch failure rolls back the submission and removes only the new photo', function () {
    $habit = Habit::factory()->create();
    $this->mock(Dispatcher::class)->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
    $this->actingAs($habit->user)->postJson(route('habits.complete', $habit), ['photo' => asyncPhoto()])->assertUnprocessable();
    $this->assertDatabaseCount('habit_completions', 0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('worker timeout marks the matching check retryable without damaging a newer attempt', function () {
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user)->postJson(route('habits.complete', $habit), ['photo' => asyncPhoto()]);
    $completion = $habit->completions()->sole();
    $job = new VerifyHabitPhoto($completion->id, $completion->verification_token);
    $job->failed(new RuntimeException('timeout'));
    expect($completion->fresh()->verification_status)->toBe('failed');
    $this->postJson(route('checks.retry', $completion))->assertAccepted();
    $job->failed(new RuntimeException('late timeout'));
    expect($completion->fresh()->verification_status)->toBe('queued');
});

test('the database queue worker processes a real queued job', function () {
    Queue::swap(app('test.real.queue'));
    Http::fake(['*' => Http::response(['message' => ['content' => '{"decision":"approved","reason":"Visible activity","visible_evidence":"Test photo"}']])]);
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user)->postJson(route('habits.complete', $habit), ['photo' => asyncPhoto()])->assertAccepted();
    Http::assertNothingSent();
    $this->assertDatabaseCount('jobs', 1);
    $this->artisan('queue:work', ['connection' => 'photos', '--queue' => 'photos', '--once' => true, '--tries' => 1, '--timeout' => 210])->assertSuccessful();
    $this->assertDatabaseCount('jobs', 0);
    expect($habit->completions()->sole()->verification_status)->toBe('approved');
});
