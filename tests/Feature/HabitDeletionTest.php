<?php

use App\Jobs\VerifyHabitPhoto;
use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Services\HabitPhotoVerifier;
use Illuminate\Support\Facades\Storage;

test('habit deletion removes saved and queued evidence without touching another habit', function () {
    Storage::fake('local');
    $habit = Habit::factory()->create();
    $root = 'habit-proofs/'.$habit->user_id.'/';
    $check = HabitCompletion::factory()->create(['habit_id' => $habit->id, 'photo_path' => $root.'saved.jpg', 'pending_photo_path' => $root.'pending.jpg']);
    foreach (['saved', 'pending', 'other'] as $name) {
        Storage::disk('local')->put($root.$name.'.jpg', 'photo');
    }
    HabitCompletion::factory()->create(['habit_id' => Habit::factory()->create(['user_id' => $habit->user_id])->id, 'photo_path' => $root.'other.jpg']);
    $this->actingAs($habit->user)->delete(route('habits.destroy', $habit))->assertRedirect();
    Storage::disk('local')->assertMissing([$root.'saved.jpg', $root.'pending.jpg']);
    Storage::disk('local')->assertExists($root.'other.jpg');
    expect($check->fresh())->toBeNull();
});

test('deleting a habit during inference removes its photo and rejects the late verdict', function () {
    Storage::fake('local');
    $habit = Habit::factory()->create();
    $path = 'habit-proofs/'.$habit->user_id.'/checking.jpg';
    Storage::disk('local')->put($path, file_get_contents(public_path('img/logo.jpeg')));
    $check = HabitCompletion::factory()->create(['habit_id' => $habit->id, 'verification_status' => 'queued', 'verification_token' => 'token', 'pending_photo_path' => $path]);
    $this->actingAs($habit->user);
    $verifier = Mockery::mock(HabitPhotoVerifier::class);
    $verifier->shouldReceive('verify')->once()->andReturnUsing(function () use ($habit) {
        $this->delete(route('habits.destroy', $habit))->assertRedirect();

        return ['decision' => 'approved', 'reason' => 'Visible', 'visible_evidence' => 'Visible'];
    });
    (new VerifyHabitPhoto($check->id, 'token'))->handle($verifier);
    expect($check->fresh())->toBeNull();
    Storage::disk('local')->assertMissing($path);
});
