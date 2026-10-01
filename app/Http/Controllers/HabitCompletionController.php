<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Services\HabitPhotoVerifier;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HabitCompletionController extends Controller
{
    public function submitPhoto(
        Request $request,
        Habit $habit,
        HabitPhotoVerifier $verifier,
    ): RedirectResponse {
        abort_unless($habit->user_id === $request->user()->id, 404);

        if (! $habit->is_daily || ($habit->schedule_type !== 'weekly' && ! $habit->isDueOn(today()))) {
            return back()->withErrors(['photo' => 'This habit is not scheduled for today.']);
        }

        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $completedOn = today()->toDateString();

        $existing = $user
            ->habitCompletions()
            ->where('habit_id', $habit->id)
            ->where('completed_on', $completedOn)
            ->first();

        $previousPhotoPath = $existing?->photo_path;
        if ($habit->schedule_type === 'weekly' && $existing?->ai_status !== 'approved') {
            $weekCount = $user->habitCompletions()->where('habit_id', $habit->id)->where('ai_status', 'approved')
                ->whereBetween('completed_on', [today()->startOfWeek(Carbon::MONDAY)->toDateString(), $completedOn])->count();
            if ($weekCount >= $habit->weekly_target) {
                return back()->withErrors(['photo' => 'You have already reached this week’s goal.']);
            }
        }
        $photo = $validated['photo'];
        try {
            $path = $photo->store("habit-proofs/{$user->id}", 'local');
        } catch (\Throwable $exception) {
            report($exception);
            $path = false;
        }

        if (! is_string($path) || $path === '') {
            return back()->withErrors([
                'photo' => 'The photo could not be saved. Please try again.',
            ]);
        }

        try {
            $result = $verifier->verify($photo, $habit->name);

            $user->habitCompletions()->updateOrCreate(
                [
                    'habit_id' => $habit->id,
                    'completed_on' => $completedOn,
                ],
                [
                    'photo_path' => $path,
                    'ai_status' => $result['decision'],
                    'ai_reason' => $result['reason'],
                    'ai_result' => $result,
                    'analyzed_at' => now(),
                ],
            );

        } catch (\Throwable $exception) {
            $this->deletePhoto($path);
            report($exception);

            return back()->withErrors([
                'photo' => $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : 'The local AI could not check this photo. Please try again.',
            ]);
        }

        // Cleanup failure must not remove the newly saved proof or undo a valid verdict.
        if ($previousPhotoPath && $previousPhotoPath !== $path) {
            $this->deletePhoto($previousPhotoPath);
        }

        return back()->with(
            'status',
            $result['decision'] === 'approved'
                ? 'Photo approved — habit completed.'
                : 'Photo needs another check: '.$result['reason'],
        );
    }

    private function deletePhoto(string $path): void
    {
        try {
            if (! Storage::disk('local')->delete($path)) {
                report(new \RuntimeException('A habit proof could not be deleted from storage.'));
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
