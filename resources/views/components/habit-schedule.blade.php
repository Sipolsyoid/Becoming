@props(['habit' => null])
@php
    $prefix = $habit ? 'schedule-'.$habit->id : 'new-schedule';
    $restore = (string) old('schedule_habit_id', '') === (string) ($habit?->id ?? '');
    $type = $restore ? old('schedule_type', $habit?->schedule_type ?? 'daily') : ($habit?->schedule_type ?? 'daily');
    $days = $restore ? old('weekdays', $habit?->weekdays ?? []) : ($habit?->weekdays ?? []);
    $days = is_array($days) ? $days : [];
@endphp
<fieldset class="schedule-fields" x-data="{ type: @js($type) }">
    <input type="hidden" name="schedule_habit_id" value="{{ $habit?->id }}">
    <legend class="text-sm font-semibold mb-2">Schedule</legend>
    <label for="{{ $prefix }}" class="sr-only">Schedule type</label>
    <select id="{{ $prefix }}" name="schedule_type" x-model="type" class="w-full rounded-lg border-slate-300">
        <option value="daily" @selected($type === 'daily')>Every day</option>
        <option value="weekdays" @selected($type === 'weekdays')>Selected weekdays</option>
        <option value="weekly" @selected($type === 'weekly')>Times per week</option>
    </select>
    <div x-show="type === 'weekdays'">
    <p class="text-xs text-slate-600 mt-3 mb-2">Choose at least one weekday:</p>
    <div class="flex flex-wrap gap-3">
        @foreach ([1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $number => $label)
            <label class="inline-flex items-center gap-1 text-sm py-2"><input type="checkbox" name="weekdays[]" value="{{ $number }}" @checked(in_array($number, $days))> {{ $label }}</label>
        @endforeach
    </div>
    </div>
    <div x-show="type === 'weekly'">
    <label for="{{ $prefix }}-target" class="text-xs text-slate-600 block mt-3">For times per week: target (1–7)</label>
    <input id="{{ $prefix }}-target" type="number" name="weekly_target" min="1" max="7" :disabled="type !== 'weekly'" value="{{ $restore ? old('weekly_target', $habit?->weekly_target ?? 3) : ($habit?->weekly_target ?? 3) }}" class="mt-1 w-24">
    <p class="text-xs text-slate-500 mt-2">Weekly goals reset Monday in {{ auth()->user()->timezone }}. One approved completion per day.</p>
    </div>
</fieldset>
