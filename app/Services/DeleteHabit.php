<?php

namespace App\Services;

use App\Jobs\VerifyHabitPhoto;
use App\Models\Habit;
use App\Models\HabitCompletion;
use Illuminate\Support\Facades\DB;

class DeleteHabit
{
    public function handle(Habit $habit): void
    {
        DB::transaction(function () use ($habit) {
            $current = Habit::whereKey($habit->id)->lockForUpdate()->firstOrFail();
            $paths = $current->completions()->lockForUpdate()->get(['photo_path', 'pending_photo_path'])
                ->flatMap(fn ($check) => [$check->photo_path, $check->pending_photo_path])->filter()->unique();
            $owner = $current->user_id;
            $current->delete();
            // Never remove evidence before the database deletion commits.
            DB::afterCommit(function () use ($paths, $owner) {
                foreach ($paths as $path) {
                    if (str_starts_with($path, 'habit-proofs/'.$owner.'/')
                        && ! str_contains($path, '..') && ! str_contains($path, '\\')
                        && ! HabitCompletion::where('photo_path', $path)->orWhere('pending_photo_path', $path)->exists()) {
                        VerifyHabitPhoto::deletePhoto($path);
                    }
                }
            });
        });
    }
}
