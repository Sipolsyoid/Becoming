<?php

namespace App\Http\Controllers;

use App\Http\Requests\HabitSchedule;
use App\Models\Habit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HabitController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['current', 'active', 'paused', 'archived', 'all'])],
            'category' => ['nullable', 'string', 'max:40'],
        ]);
        $status = $filters['status'] ?? 'current';
        $habits = $request->user()->habits()
            ->when($status === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->when(in_array($status, ['current', 'active', 'paused']), fn ($q) => $q->whereNull('archived_at'))
            ->when(in_array($status, ['active', 'paused']), fn ($q) => $q->where('is_daily', $status === 'active'))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->orderBy('sort_order')->orderBy('created_at')->orderBy('id')->get();

        return view('habits.index', [
            'habits' => $habits,
            'status' => $status,
            'categories' => $request->user()->habits()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $schedule = $request->has('schedule_type') ? HabitSchedule::validate($request) : [];

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

        DB::transaction(fn () => $user->habits()->create([
            ...$schedule,
            'name' => $validated['name'],
            'category' => $validated['category'] ?? null,
            'is_daily' => $request->boolean('is_daily'),
        ]));

        return redirect()->route('habits.index')->with('status', 'Habit added. Your next small step is ready.');
    }

    public function update(Request $request, Habit $habit): RedirectResponse
    {
        abort_unless($habit->user_id === $request->user()->id, 404);
        abort_if($habit->archived_at, 409, 'Restore this habit before editing it.');

        if ($request->has('name')) {
            $values = $request->validate([
                'name' => ['required', 'string', 'max:80', Rule::unique('habits', 'name')->where('user_id', $request->user()->id)->ignore($habit->id)],
                'category' => ['nullable', 'string', 'max:40'],
            ]);
            $habit->update($values);

            return back()->with('status', 'Habit details saved.');
        }

        if ($request->has('schedule_type')) {
            $values = HabitSchedule::validate($request);
            DB::transaction(function () use ($habit, $values) {
                Habit::whereKey($habit->id)->lockForUpdate()->firstOrFail()->update($values);
            });

            return back()->with('status', 'Schedule updated from today. Earlier days are unchanged.');
        }

        $validated = $request->validate([
            'is_daily' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($habit, $validated) {
            Habit::whereKey($habit->id)->lockForUpdate()->firstOrFail()->update(['is_daily' => (bool) $validated['is_daily']]);
        });
        $habit->refresh();

        return back()->with('status', $habit->is_daily ? 'Habit resumed on its schedule.' : 'Habit paused. Your records are kept.');
    }

    public function archive(Request $request, Habit $habit): RedirectResponse
    {
        abort_unless($habit->user_id === $request->user()->id, 404);
        DB::transaction(function () use ($habit) {
            $habit = Habit::whereKey($habit->id)->lockForUpdate()->firstOrFail();
            if (! $habit->archived_at) {
                $habit->forceFill(['archived_at' => now(), 'archived_was_active' => $habit->is_daily, 'is_daily' => false])->save();
            }
        });

        return back()->with('status', 'Habit archived. Its history is kept, and you can restore it anytime.');
    }

    public function restore(Request $request, Habit $habit): RedirectResponse
    {
        abort_unless($habit->user_id === $request->user()->id, 404);
        DB::transaction(function () use ($habit) {
            $habit = Habit::whereKey($habit->id)->lockForUpdate()->firstOrFail();
            if ($habit->archived_at) {
                $habit->forceFill(['archived_at' => null, 'is_daily' => $habit->archived_was_active])->save();
            }
        });

        return back()->with('status', 'Habit restored to its previous active or paused state.');
    }

    public function move(Request $request, Habit $habit): RedirectResponse
    {
        abort_unless($habit->user_id === $request->user()->id, 404);
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        DB::transaction(function () use ($request, $habit, $data) {
            // Lock one user's list in a consistent order before assigning positions.
            $habits = $request->user()->habits()->orderBy('id')->lockForUpdate()->get()
                ->filter(fn ($item) => (bool) $item->archived_at === (bool) $habit->archived_at)
                ->sortBy(fn ($item) => [$item->sort_order, $item->created_at->getTimestamp(), $item->id])->values();
            $index = $habits->search(fn ($item) => $item->id === $habit->id);
            $next = $index + ($data['direction'] === 'up' ? -1 : 1);
            if ($next >= 0 && $next < $habits->count()) {
                $other = $habits[$next];
                $habits[$next] = $habits[$index];
                $habits[$index] = $other;
                foreach ($habits as $position => $item) {
                    $item->forceFill(['sort_order' => $position])->saveQuietly();
                }
            }
        });

        return back()->with('status', 'Habit order saved.');
    }

    public function destroy(Request $request, Habit $habit): RedirectResponse
    {
        abort_unless($habit->user_id === $request->user()->id, 404);

        $habit->delete();

        return back()->with('status', 'Habit and completion history deleted.');
    }
}
