<?php

namespace App\Console\Commands;

use App\Jobs\VerifyHabitPhoto;
use App\Models\HabitCompletion;
use Illuminate\Console\Command;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecoverPhotoChecks extends Command
{
    protected $signature = 'photos:recover';

    protected $description = 'Recover photo checks interrupted for over ten minutes';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(10);
        HabitCompletion::where('verification_status', 'checking')
            ->where(function ($query) use ($cutoff) {
                $query->where('verification_started_at', '<', $cutoff)
                    ->orWhere(fn ($q) => $q->whereNull('verification_started_at')->where('verification_requested_at', '<', $cutoff));
            })->select('id')->chunkById(100, function ($rows) use ($cutoff) {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, $cutoff) {
                        $check = HabitCompletion::whereKey($row->id)->lockForUpdate()->first();
                        if (! $check || $check->verification_status !== 'checking'
                            || ($check->verification_started_at ?? $check->verification_requested_at)?->gte($cutoff)) {
                            return;
                        }
                        if (! $check->pending_photo_path || $check->verification_recoveries >= 1) {
                            $check->update(['verification_status' => 'failed', 'verification_token' => (string) Str::uuid(),
                                'verification_reason' => 'This check was interrupted again. Please retry your saved photo.']);

                            return;
                        }
                        $check->update(['verification_status' => 'queued', 'verification_token' => (string) Str::uuid(),
                            'verification_started_at' => null, 'verification_requested_at' => now(),
                            'verification_recoveries' => $check->verification_recoveries + 1,
                            'verification_reason' => 'An interrupted check was queued again automatically.']);
                        app(Dispatcher::class)->dispatch(new VerifyHabitPhoto($check->id, $check->verification_token));
                    });
                }
            });
        $this->info('Interrupted photo checks inspected.');

        return self::SUCCESS;
    }
}
