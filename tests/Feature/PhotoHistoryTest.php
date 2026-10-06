<?php

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('photo history is newest first and paginated including archived habits', function () {
    $habit = Habit::factory()->create();
    for ($day = 1; $day <= 13; $day++) {
        HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => sprintf('2026-09-%02d', $day)]);
    }
    $this->actingAs($habit->user)->post(route('habits.archive', $habit));
    $this->get(route('habits.show', $habit))->assertOk()->assertSee('Archived')
        ->assertViewHas('checkIns', fn ($rows) => $rows->count() === 12 && $rows->total() === 13 && $rows->first()->completed_on->toDateString() === '2026-09-13');
    $this->get(route('habits.show', $habit).'?page=2')->assertOk()
        ->assertViewHas('checkIns', fn ($rows) => $rows->count() === 1 && $rows->first()->completed_on->toDateString() === '2026-09-01');
});

test('empty history gives a useful next step', function () {
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user)->get(route('habits.show', $habit))->assertOk()->assertSee('No photo check-ins yet')->assertSee('Go to dashboard');
});

test('owners can view saved and pending photos with private headers', function () {
    Storage::fake('local');
    $habit = Habit::factory()->create();
    $path = UploadedFile::fake()->image('proof.png')->store('habit-proofs/'.$habit->user_id, 'local');
    $check = HabitCompletion::factory()->create(['habit_id' => $habit->id, 'photo_path' => $path, 'pending_photo_path' => $path]);
    foreach (['saved', 'pending'] as $version) {
        $response = $this->actingAs($habit->user)->get(route('checks.photo', [$check, $version]))->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    }
});

test('photos and detail pages reject guests and other owners', function () {
    $check = HabitCompletion::factory()->create();
    $this->get(route('habits.show', $check->habit))->assertRedirect(route('login'));
    $this->get(route('checks.photo', [$check, 'saved']))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('habits.show', $check->habit))->assertNotFound();
    foreach (['saved', 'pending'] as $version) {
        $this->get(route('checks.photo', [$check, $version]))->assertNotFound();
    }
    $check->update(['user_id' => auth()->id()]);
    $this->get(route('checks.photo', [$check, 'saved']))->assertNotFound();
});

test('missing unsafe and non-image paths never expose files', function () {
    Storage::fake('local');
    $check = HabitCompletion::factory()->create();
    $prefix = 'habit-proofs/'.$check->user_id.'/';
    Storage::disk('local')->put($prefix.'script.png', '<script>alert(1)</script>');
    $this->actingAs($check->user);
    foreach ([null, $prefix.'missing.png', $prefix.'../secret.png', 'habit-proofs/999/other.png', $prefix.'script.png'] as $path) {
        $check->update(['photo_path' => $path]);
        $this->get(route('checks.photo', [$check, 'saved']))->assertNotFound();
    }
    $this->get(route('checks.photo', [$check, 'unknown']))->assertNotFound();
});

test('feedback is escaped and saved approval is distinct from replacement status', function () {
    $check = HabitCompletion::factory()->create([
        'photo_path' => 'saved.png', 'pending_photo_path' => 'pending.png',
        'ai_status' => 'approved', 'ai_reason' => '<script>alert(1)</script>',
        'verification_status' => 'failed', 'verification_reason' => 'The replacement check was interrupted.',
    ]);
    $this->actingAs($check->user)->get(route('habits.show', $check->habit))->assertOk()
        ->assertSee('Approved')->assertSee('Replacement photo: Failed')
        ->assertSee('Your saved approval remains')->assertSee('The replacement check was interrupted.')
        ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee(route('checks.photo', [$check, 'saved']), false)
        ->assertSee(route('checks.photo', [$check, 'pending']), false);
});
