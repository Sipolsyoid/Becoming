<?php

use App\Models\HabitCompletion;
use App\Models\User;
use App\Services\DeleteAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('account deletion removes records and all owned photos while preserving other users files', function () {
    Storage::fake('local');
    $check = HabitCompletion::factory()->create();
    $other = User::factory()->create();
    $root = 'habit-proofs/'.$check->user_id;
    foreach (['saved.jpg', 'pending.jpg', 'unreferenced.jpg'] as $name) {
        Storage::disk('local')->put($root.'/'.$name, 'photo');
    }
    Storage::disk('local')->put('habit-proofs/'.$other->id.'/keep.jpg', 'photo');
    $this->actingAs($check->user)->delete(route('account.destroy'), ['password' => 'password'])->assertRedirect('/');
    $this->assertGuest();
    expect($check->fresh())->toBeNull();
    expect(User::find($check->user_id))->toBeNull();
    expect(Storage::disk('local')->allFiles($root))->toBe([]);
    Storage::disk('local')->assertExists('habit-proofs/'.$other->id.'/keep.jpg');
});

test('account deletion requires the current password', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->delete(route('account.destroy'), ['password' => 'wrong'])->assertSessionHasErrorsIn('userDeletion', 'password');
    expect($user->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('rolled back account deletion leaves photos intact', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $path = 'habit-proofs/'.$user->id.'/photo.jpg';
    Storage::disk('local')->put($path, 'photo');
    try {
        DB::transaction(function () use ($user) {
            app(DeleteAccount::class)->handle($user);
            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException) {
    }
    expect($user->fresh())->not->toBeNull();
    Storage::disk('local')->assertExists($path);
});
