<?php

namespace App\Http\Controllers;

use App\Jobs\VerifyHabitPhoto;
use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Services\HabitMetrics;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class HabitCompletionController extends Controller
{
    public function cancel(Request $request, HabitCompletion $completion): JsonResponse|RedirectResponse
    {
        abort_unless($completion->user_id === $request->user()->id, 404);
        $path = DB::transaction(function () use ($completion) {
            $current = HabitCompletion::whereKey($completion->id)->lockForUpdate()->firstOrFail();
            if ($current->verification_status !== 'queued') {
                throw ValidationException::withMessages(['photo' => 'Only a queued check can be cancelled. This check may have started already.']);
            }
            $path = $current->pending_photo_path;
            $current->update(['verification_status' => 'cancelled', 'verification_token' => (string) Str::uuid(),
                'pending_photo_path' => null, 'verification_reason' => 'Photo check cancelled. Any previous saved result is kept.']);

            return $path;
        });
        if ($path && $path !== $completion->photo_path) {
            VerifyHabitPhoto::deletePhoto($path);
        }

        return $request->expectsJson() ? response()->json($completion->fresh()->checkState())
            : back()->with('status', 'Queued photo check cancelled.');
    }

    public function photo(Request $request, HabitCompletion $completion, string $version): BinaryFileResponse
    {
        abort_unless($completion->user_id === $request->user()->id
            && $completion->habit?->user_id === $request->user()->id, 404);
        $path = $version === 'pending' ? $completion->pending_photo_path : $completion->photo_path;
        $prefix = 'habit-proofs/'.$request->user()->id.'/';
        abort_unless(is_string($path) && str_starts_with($path, $prefix)
            && ! str_contains($path, '..') && ! str_contains($path, '\\'), 404);
        $disk = Storage::disk('local');
        $root = realpath($disk->path($prefix));
        $file = realpath($disk->path($path));
        abort_unless($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);
        $mime = @getimagesize($file)['mime'] ?? null;
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response()->file($file, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
        ])->setPrivate();
    }

    public function submitPhoto(Request $request, Habit $habit): JsonResponse|RedirectResponse
    {
        abort_unless($habit->user_id === $request->user()->id, 404);
        $user = $request->user();
        $today = $user->localToday();
        if (! $habit->is_daily || ($habit->schedule_type !== 'weekly' && ! $habit->isDueOn($today))) {
            throw ValidationException::withMessages(['photo' => 'This habit is not scheduled for today.']);
        }
        $validated = $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        try {
            $path = $validated['photo']->store("habit-proofs/{$user->id}", 'local');
        } catch (Throwable $exception) {
            report($exception);
            $path = false;
        }
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['photo' => 'The photo could not be saved. Please try again.']);
        }
        $oldPending = null;
        try {
            $completion = DB::transaction(function () use ($habit, $user, $today, $path, &$oldPending) {
                // Serialize same-habit uploads, including the first upload with no completion row yet.
                $lockedHabit = Habit::whereKey($habit->id)->lockForUpdate()->firstOrFail();
                if (! $lockedHabit->is_daily || ($lockedHabit->schedule_type !== 'weekly' && ! $lockedHabit->isDueOn($today))) {
                    throw ValidationException::withMessages(['photo' => 'This habit is no longer scheduled for today.']);
                }
                $existing = $user->habitCompletions()->where('habit_id', $habit->id)->where('completed_on', $today->toDateString())->first();
                if (in_array($existing?->verification_status, ['queued', 'checking'], true)) {
                    throw ValidationException::withMessages(['photo' => 'This photo is already being checked. Your result will appear automatically.']);
                }
                if ($lockedHabit->schedule_type === 'weekly' && $existing?->ai_status !== 'approved') {
                    $count = app(HabitMetrics::class)->weeklyGoals($user, $today)->firstWhere('habit.id', $habit->id)['done'] ?? 0;
                    if ($count >= $lockedHabit->weekly_target) {
                        throw ValidationException::withMessages(['photo' => 'You have already reached this week’s goal.']);
                    }
                }
                $oldPending = $existing?->pending_photo_path;
                $completion = $user->habitCompletions()->updateOrCreate(
                    ['habit_id' => $habit->id, 'completed_on' => $today->toDateString()],
                    ['pending_photo_path' => $path, 'verification_habit_name' => $lockedHabit->name, 'verification_token' => (string) Str::uuid(), 'verification_status' => 'queued',
                        'verification_reason' => null, 'verification_started_at' => null, 'verification_recoveries' => 0, 'verification_requested_at' => now()],
                );
                // The database queue insert participates in this transaction on the default database.
                app(Dispatcher::class)->dispatch(new VerifyHabitPhoto($completion->id, $completion->verification_token));

                return $completion;
            });
        } catch (Throwable $exception) {
            VerifyHabitPhoto::deletePhoto($path);
            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            report($exception);
            throw ValidationException::withMessages(['photo' => 'Your photo could not be queued. Please try again.']);
        }
        if ($oldPending && $oldPending !== $path) {
            VerifyHabitPhoto::deletePhoto($oldPending);
        }

        return $request->expectsJson()
            ? response()->json($completion->fresh()->checkState(), 202)
            : back()->with('status', 'Photo saved. We’ll check it in the background — you can keep going.');
    }

    public function show(Request $request, HabitCompletion $completion): JsonResponse
    {
        abort_unless($completion->user_id === $request->user()->id, 404);

        return response()->json($completion->checkState())->header('Cache-Control', 'no-store');
    }

    public function retry(Request $request, HabitCompletion $completion): JsonResponse|RedirectResponse
    {
        abort_unless($completion->user_id === $request->user()->id, 404);
        try {
            DB::transaction(function () use ($completion) {
                $current = HabitCompletion::whereKey($completion->id)->lockForUpdate()->firstOrFail();
                if (! $current->checkState()['can_retry']) {
                    throw ValidationException::withMessages(['photo' => 'This check is still running or no longer needs a retry.']);
                }
                $current->update(['verification_status' => 'queued', 'verification_reason' => null,
                    'verification_token' => (string) Str::uuid(), 'verification_started_at' => null, 'verification_recoveries' => 0, 'verification_requested_at' => now()]);
                app(Dispatcher::class)->dispatch(new VerifyHabitPhoto($current->id, $current->verification_token));
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['photo' => 'We could not restart the check. Please try again.']);
        }

        return $request->expectsJson()
            ? response()->json($completion->fresh()->checkState(), 202)
            : back()->with('status', 'Your saved photo is queued for another check.');
    }
}
