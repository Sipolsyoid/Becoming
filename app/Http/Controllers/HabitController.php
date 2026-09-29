<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HabitController extends Controller
{
    public function index(Request $request): View
    {
        $habits = $request->user()
            ->habits()
            ->orderByDesc('is_daily')
            ->orderBy('created_at')
            ->get();

        return view('habits.index', [
            'habits' => $habits,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('habits', 'name')->where(fn ($query) => $query->where('user_id', $user->id)),
            ],
            'category' => ['nullable', 'string', 'max:40'],
            'is_daily' => ['nullable', 'boolean'],
        ]);

        $user->habits()->create([
            'name' => $validated['name'],
            'category' => $validated['category'] ?? null,
            'is_daily' => $request->boolean('is_daily'),
        ]);

        return redirect()->route('habits.index')->with('status', 'Habit added. Your next small step is ready.');
    }

    public function update(Request $request, Habit $habit): RedirectResponse
    {
        abort_unless($habit->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'is_daily' => ['required', 'boolean'],
        ]);

        $habit->update([
            'is_daily' => (bool) $validated['is_daily'],
        ]);

        return back()->with('status', $habit->is_daily ? 'Habit added to your daily list.' : 'Habit removed from your daily list. It is still in your collection.');
    }

    public function destroy(Request $request, Habit $habit): RedirectResponse
    {
        abort_unless($habit->user_id === $request->user()->id, 404);

        $habit->delete();

        return back()->with('status', 'Habit and completion history deleted.');
    }
}

