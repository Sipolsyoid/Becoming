<?php

namespace App\Jobs;

use App\Contracts\PhotoVerifier;
use App\Models\HabitCompletion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class VerifyHabitPhoto implements ShouldQueue
{
    use Queueable;

    public int $timeout = 210;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $completionId, public string $token)
    {
        $this->onConnection('photos')->onQueue('photos');
    }

    public function handle(PhotoVerifier $verifier): void
    {
        $claimed = HabitCompletion::whereKey($this->completionId)->where('verification_token', $this->token)
            ->where('verification_status', 'queued')->update(['verification_status' => 'checking', 'verification_started_at' => now()]);
        if (! $claimed) {
            return;
        }
        $completion = HabitCompletion::with('habit')->find($this->completionId);
        if (! $completion?->habit) {
            return;
        }
        try {
            $photo = new UploadedFile(Storage::disk('local')->path($completion->pending_photo_path), 'proof.jpg', null, null, true);
            $result = $verifier->verify($photo, $completion->habit->name);
            $oldPath = DB::transaction(function () use ($completion, $result) {
                $current = HabitCompletion::whereKey($this->completionId)->lockForUpdate()->first();
                if (! $current || $current->verification_token !== $this->token || $current->verification_status !== 'checking') {
                    return null;
                }
                $old = $current->photo_path;
                $current->update([
                    'photo_path' => $completion->pending_photo_path, 'pending_photo_path' => null,
                    'ai_status' => $result['decision'], 'ai_reason' => $result['reason'], 'ai_result' => $result,
                    'analyzed_at' => now(), 'verification_status' => $result['decision'], 'verification_reason' => $result['reason'],
                ]);

                return $old;
            });
            if ($oldPath && $oldPath !== $completion->pending_photo_path) {
                self::deletePhoto($oldPath);
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed(get_class($exception) === \RuntimeException::class ? $exception->getMessage() : 'We could not check this photo. Please try again.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed('The photo check was interrupted. Your photo is saved; try the check again.');
    }

    private function markFailed(string $reason): void
    {
        HabitCompletion::whereKey($this->completionId)->where('verification_token', $this->token)
            ->whereIn('verification_status', ['queued', 'checking'])->update(['verification_status' => 'failed', 'verification_reason' => $reason]);
    }

    public static function deletePhoto(string $path): void
    {
        try {
            if (! Storage::disk('local')->delete($path)) {
                report(new \RuntimeException('A superseded habit photo could not be deleted.'));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
