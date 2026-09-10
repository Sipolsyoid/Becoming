<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[#2B3E51] leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <div class="relative overflow-hidden rounded-2xl border border-[#2B3E51]/10 bg-white/70 shadow-xl backdrop-blur motion-safe:animate-[becoming-rise-in_700ms_ease-out_both]">
                        <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                            <div class="absolute -top-24 -right-24 h-64 w-64 rounded-full bg-[#76C7B7]/25 blur-3xl motion-safe:animate-[becoming-blob-2_18s_ease-in-out_infinite]"></div>
                            <div class="absolute -bottom-28 -left-28 h-72 w-72 rounded-full bg-[#5DA068]/20 blur-3xl motion-safe:animate-[becoming-blob-1_22s_ease-in-out_infinite]"></div>
                        </div>

                        <div class="relative p-6 sm:p-8">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <p class="text-sm text-[#2B3E51]/70">{{ __("Today's habits") }}</p>
                                    <h3 class="mt-1 text-2xl font-semibold tracking-tight text-[#2B3E51]">
                                        {{ __('Small steps, every day.') }}
                                    </h3>
                                </div>

                                <div class="text-sm text-[#2B3E51]/70">
                                    <span class="font-semibold text-[#2B3E51]">{{ $completedCount }}</span>
                                    <span>/</span>
                                    <span class="font-semibold text-[#2B3E51]">{{ $totalCount }}</span>
                                    <span>{{ __('done') }}</span>
                                </div>
                            </div>

                            <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-[#2B3E51]/10">
                                <div class="h-full rounded-full bg-gradient-to-r from-[#377991] to-[#5DA068]" style="width: {{ $progressPercent }}%;"></div>
                            </div>
                            <div class="mt-2 text-xs text-[#2B3E51]/60">{{ $progressPercent }}% {{ __('complete') }}</div>

                            @if (session('status'))
                                <div class="mt-4 rounded-xl bg-[#5DA068]/15 px-4 py-3 text-sm text-[#2B3E51]">
                                    {{ session('status') }}
                                </div>
                            @endif

                            @if ($errors->has('photo'))
                                <div class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                                    {{ $errors->first('photo') }}
                                </div>
                            @endif

                            <div class="mt-6 space-y-3">
                                @forelse ($dailyHabits as $habit)
                                    @php($isDone = in_array($habit->id, $completedHabitIds, true))
                                    <div class="flex flex-col gap-4 rounded-2xl border border-[#2B3E51]/10 bg-white/60 px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <div class="font-medium text-[#2B3E51] {{ $isDone ? 'line-through opacity-70' : '' }}">
                                                {{ $habit->name }}
                                            </div>
                                            <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-[#2B3E51]/60">
                                                <span>{{ $today->format('D, M j') }}</span>
                                                @if ($habit->category)
                                                    <span class="inline-flex items-center rounded-full bg-[#76C7B7]/20 px-2 py-0.5 font-medium text-[#2B3E51]/80">
                                                        {{ $habit->category }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        @if ($isDone)
                                            <span class="text-xs font-medium text-[#5DA068]">{{ __('Done') }}</span>
                                        @else
                                            <form method="POST" action="{{ route('habits.complete', $habit) }}" enctype="multipart/form-data" class="flex flex-col gap-2 sm:items-end">
                                                @csrf
                                                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment" required class="block w-full text-xs text-[#2B3E51] sm:w-56">
                                                <button type="submit" class="rounded-xl bg-[#377991] px-3 py-2 text-xs font-medium text-white transition hover:bg-[#2B3E51]">
                                                    {{ __('Check photo') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-[#2B3E51]/20 bg-white/50 p-8 text-center">
                                        <p class="text-sm text-[#2B3E51]/70">
                                            {{ __("No daily habits yet.") }}
                                            <a href="{{ route('habits.index') }}" class="font-medium text-[#377991] underline underline-offset-4 hover:text-[#2B3E51]">
                                                {{ __('Add your first habit') }}
                                            </a>
                                        </p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-2xl border border-[#2B3E51]/10 bg-white/70 shadow-xl backdrop-blur p-6 sm:p-8 motion-safe:animate-[becoming-rise-in_800ms_ease-out_both]">
                        <p class="text-sm text-[#2B3E51]/70">{{ __('Streak') }}</p>
                        <div class="mt-2 flex items-end gap-2">
                            <div class="text-4xl font-semibold tracking-tight text-[#2B3E51]">{{ $streak }}</div>
                            <div class="text-2xl">🔥</div>
                        </div>
                        <p class="mt-2 text-sm text-[#2B3E51]/70">{{ __('Perfect days in a row.') }}</p>
                    </div>

                    <div class="rounded-2xl border border-[#2B3E51]/10 bg-white/70 shadow-xl backdrop-blur p-6 sm:p-8 motion-safe:animate-[becoming-rise-in_900ms_ease-out_both]">
                        <p class="text-sm text-[#2B3E51]/70">{{ __('Today') }}</p>
                        <div class="mt-2 text-lg font-semibold text-[#2B3E51]">
                            {{ __(':done/:total habits done', ['done' => $completedCount, 'total' => $totalCount]) }}
                        </div>
                        <p class="mt-2 text-sm text-[#2B3E51]/70">{{ __('Aim for a clean finish — then stop.') }}</p>
                    </div>

                    <div class="rounded-2xl border border-[#2B3E51]/10 bg-white/70 shadow-xl backdrop-blur p-6 sm:p-8 motion-safe:animate-[becoming-rise-in_1000ms_ease-out_both]">
                        <p class="text-sm text-[#2B3E51]/70">{{ __('Focus') }}</p>
                        @if ($focusHabit)
                            <div class="mt-2 text-lg font-semibold text-[#2B3E51]">{{ $focusHabit->name }}</div>
                            <p class="mt-2 text-sm text-[#2B3E51]/70">
                                {{ __('This week: :count/7 completed. Try doing it earlier tomorrow.', ['count' => $focusHabitCount]) }}
                            </p>
                        @else
                            <p class="mt-2 text-sm text-[#2B3E51]/70">
                                {{ __('Add a few daily habits to get simple insights.') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
