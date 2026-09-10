<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Services\HabitPhotoVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HabitCompletionController extends Controller
{
    public function submitPhoto(
        Request $request,
        Habit $habit,
        HabitPhotoVerifier $verifier,
    ): RedirectResponse
    {
        abort_unless($habit->user_id === $request->user()->id, 404);

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
        $photo = $validated['photo'];
        $path = $photo->store("habit-proofs/{$user->id}", 'local');

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

            if ($previousPhotoPath && $previousPhotoPath !== $path) {
                Storage::disk('local')->delete($previousPhotoPath);
            }
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            report($exception);

            return back()->withErrors([
                'photo' => $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : 'The local AI could not check this photo. Please try again.',
            ]);
        }

        return back()->with(
            'status',
            $result['decision'] === 'approved'
                ? 'Photo approved — habit completed.'
                : 'Photo needs another check: '.$result['reason'],
        );
    }
}
