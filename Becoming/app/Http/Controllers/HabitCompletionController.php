<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HabitCompletionController extends Controller
{
    public function toggle(Request $request, Habit $habit): RedirectResponse
    {
        abort_unless($habit->user_id === $request->user()->id, 404);

        $today = Carbon::today()->toDateString();

        $existing = $request->user()
            ->habitCompletions()
            ->where('habit_id', $habit->id)
            ->where('completed_on', $today)
            ->first();

        if ($existing) {
            $existing->delete();

            return back();
        }

        $request->user()->habitCompletions()->create([
            'habit_id' => $habit->id,
            'user_id' => $request->user()->id,
            'completed_on' => $today,
        ]);

        return back();
    }
}

