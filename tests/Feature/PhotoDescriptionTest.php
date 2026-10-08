<?php

use App\Contracts\PhotoVerifier;
use App\Jobs\VerifyHabitPhoto;
use App\Models\Habit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('queued photos retain their submitted description after a habit rename', function () {
    Storage::fake('local');
    Queue::fake();
    $habit = Habit::factory()->create(['name' => 'Read a book']);
    $photo = UploadedFile::fake()->createWithContent('photo.jpg', file_get_contents(public_path('img/logo.jpeg')));
    $this->actingAs($habit->user)->postJson(route('habits.complete', $habit), ['photo' => $photo])->assertAccepted();
    $check = $habit->completions()->sole();
    $this->patch(route('habits.update', $habit), ['name' => 'Water a plant'])->assertRedirect();
    $verifier = Mockery::mock(PhotoVerifier::class);
    $verifier->shouldReceive('verify')->once()->with(Mockery::type(UploadedFile::class), 'Read a book')
        ->andReturn(['decision' => 'rejected', 'reason' => 'Unrelated image', 'visible_evidence' => 'Logo']);
    (new VerifyHabitPhoto($check->id, $check->verification_token))->handle($verifier);
    expect($check->fresh()->photo_habit_name)->toBe('Read a book');
    $this->get(route('habits.show', $habit))->assertSee('Evaluated for: Read a book');
});
