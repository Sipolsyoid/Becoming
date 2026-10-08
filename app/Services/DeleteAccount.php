<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DeleteAccount
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            $current = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $current->habits()->orderBy('id')->lockForUpdate()->get();
            $current->habitCompletions()->orderBy('id')->lockForUpdate()->get();
            $id = (int) $current->id;
            $current->delete();
            DB::afterCommit(function () use ($id) {
                try {
                    if (! Storage::disk('local')->deleteDirectory('habit-proofs/'.$id)) {
                        report(new RuntimeException('Account photo deletion failed; orphan cleanup will retry.'));
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        });
    }
}
