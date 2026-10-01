@props(['goals', 'completedToday' => [], 'upload' => false])
@if ($goals->isNotEmpty())
<section class="mt-8">
    <div class="section-heading"><h2>Weekly goals</h2><span>Monday–Sunday · UTC</span></div>
    <p class="quiet-note mb-4">Choose your days. Weekly goals count separately from daily percentages and streaks.</p>
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($goals as $goal)
            <article class="habit-card">
                <h3 class="font-semibold">{{ $goal['habit']->name }}</h3>
                <p class="text-sm mt-2">{{ $goal['done'] }} / {{ $goal['target'] }} this week {{ $goal['done'] >= $goal['target'] ? '· Goal reached ✓' : '' }}</p>
                @if ($upload)
                    @if (in_array($goal['habit']->id, $completedToday, true))
                        <p class="text-sm mt-3">Done today</p>
                    @elseif ($goal['done'] < $goal['target'])
                        <x-photo-proof :habit="$goal['habit']" />
                    @endif
                @endif
            </article>
        @endforeach
    </div>
</section>
@endif
