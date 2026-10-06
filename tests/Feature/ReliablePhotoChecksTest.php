<?php

use App\Jobs\VerifyHabitPhoto;
use App\Models\HabitCompletion;
use App\Models\User;
use App\Services\HabitPhotoVerifier;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    Cache::forget('photos.worker_seen_at');
});

test('worker warning reflects missing heartbeat and clears when the photo worker runs', function () {
    $check = HabitCompletion::factory()->create(['verification_status' => 'queued', 'verification_requested_at' => now()->subMinutes(3)]);
    expect($check->checkState()['worker_note'])->not->toBeNull();
    Event::dispatch(new Looping('database', 'default'));
    expect($check->checkState()['worker_note'])->not->toBeNull();
    Event::dispatch(new Looping('photos', 'photos'));
    expect($check->checkState()['worker_note'])->toBeNull();
    $this->travel(6)->minutes();
    expect($check->checkState()['worker_note'])->not->toBeNull();
});

test('queued cancellation preserves saved approval and invalidates old jobs', function () {
    $check = HabitCompletion::factory()->create(['verification_status' => 'queued', 'verification_token' => 'old',
        'pending_photo_path' => 'habit-proofs/1/pending.jpg', 'photo_path' => 'habit-proofs/1/saved.jpg', 'ai_status' => 'approved']);
    Storage::disk('local')->put($check->pending_photo_path, 'pending');
    Storage::disk('local')->put($check->photo_path, 'saved');
    $this->actingAs($check->user)->postJson(route('checks.cancel', $check))->assertOk()->assertJsonPath('status', 'cancelled')->assertJsonPath('approved', true);
    expect($check->fresh()->verification_token)->not->toBe('old');
    Storage::disk('local')->assertMissing('habit-proofs/1/pending.jpg');
    Storage::disk('local')->assertExists('habit-proofs/1/saved.jpg');
    $verifier = Mockery::mock(HabitPhotoVerifier::class);
    $verifier->shouldNotReceive('verify');
    (new VerifyHabitPhoto($check->id, 'old'))->handle($verifier);
    $this->postJson(route('checks.cancel', $check))->assertUnprocessable();
});

test('checking cancellation and another owners cancellation are rejected', function () {
    $check = HabitCompletion::factory()->create(['verification_status' => 'checking']);
    $this->actingAs($check->user)->postJson(route('checks.cancel', $check))->assertUnprocessable();
    $this->actingAs(User::factory()->create())->postJson(route('checks.cancel', $check))->assertNotFound();
});

test('stale checks recover once while recent checks and queued uploads are untouched', function () {
    $check = HabitCompletion::factory()->create(['verification_status' => 'checking', 'verification_token' => 'stale',
        'pending_photo_path' => 'photo.jpg', 'verification_started_at' => now()->subMinutes(11)]);
    $recent = HabitCompletion::factory()->create(['verification_status' => 'checking', 'verification_started_at' => now()]);
    $queued = HabitCompletion::factory()->create(['verification_status' => 'queued', 'verification_requested_at' => now()->subHour()]);
    $this->artisan('photos:recover')->assertSuccessful();
    expect($check->fresh()->verification_status)->toBe('queued');
    expect($check->fresh()->verification_token)->not->toBe('stale');
    expect($check->fresh()->verification_recoveries)->toBe(1);
    expect($recent->fresh()->verification_status)->toBe('checking');
    expect($queued->fresh()->verification_status)->toBe('queued');
    Queue::assertPushed(VerifyHabitPhoto::class, 1);
    $this->artisan('photos:recover')->assertSuccessful();
    Queue::assertPushed(VerifyHabitPhoto::class, 1);
    $check->refresh()->update(['verification_status' => 'checking', 'verification_started_at' => now()->subMinutes(11)]);
    $this->artisan('photos:recover')->assertSuccessful();
    expect($check->fresh()->verification_status)->toBe('failed');
    Queue::assertPushed(VerifyHabitPhoto::class, 1);
});

test('cleanup previews and deletes only old unreferenced habit photos', function () {
    $disk = Storage::disk('local');
    foreach (['saved', 'pending', 'orphan', 'new'] as $name) {
        $disk->put('habit-proofs/1/'.$name.'.jpg', 'photo');
        if ($name !== 'new') {
            touch($disk->path('habit-proofs/1/'.$name.'.jpg'), now()->subDays(2)->timestamp);
        }
    }
    $disk->put('other/file.jpg', 'keep');
    HabitCompletion::factory()->create(['photo_path' => 'habit-proofs/1/saved.jpg', 'pending_photo_path' => 'habit-proofs/1/pending.jpg']);
    $this->artisan('photos:cleanup')->expectsOutput('1 unreferenced photos eligible for deletion (preview only).')->assertSuccessful();
    $disk->assertExists('habit-proofs/1/orphan.jpg');
    $this->artisan('photos:cleanup --delete')->assertSuccessful();
    $disk->assertMissing('habit-proofs/1/orphan.jpg');
    foreach (['saved', 'pending', 'new'] as $name) {
        $disk->assertExists('habit-proofs/1/'.$name.'.jpg');
    }
    $disk->assertExists('other/file.jpg');
});

test('recent results include earlier dates and are scoped to their owner', function () {
    $check = HabitCompletion::factory()->create(['verification_status' => 'approved', 'analyzed_at' => now(), 'completed_on' => today()->subDay()]);
    HabitCompletion::factory()->create(['verification_status' => 'rejected', 'analyzed_at' => now()]);
    $this->actingAs($check->user)->get('/')->assertOk()
        ->assertViewHas('recentChecks', fn ($rows) => $rows->pluck('id')->all() === [$check->id]);
});
