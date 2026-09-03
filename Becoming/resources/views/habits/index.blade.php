<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[#2B3E51] leading-tight">
            {{ __('Habits') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="relative overflow-hidden rounded-2xl border border-[#2B3E51]/10 bg-white/70 shadow-xl backdrop-blur motion-safe:animate-[becoming-rise-in_700ms_ease-out_both]">
                <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                    <div class="absolute -top-24 -right-24 h-64 w-64 rounded-full bg-[#76C7B7]/20 blur-3xl motion-safe:animate-[becoming-blob-2_18s_ease-in-out_infinite]"></div>
                    <div class="absolute -bottom-28 -left-28 h-72 w-72 rounded-full bg-[#5DA068]/10 blur-3xl motion-safe:animate-[becoming-blob-1_22s_ease-in-out_infinite]"></div>
                </div>

                <div class="relative p-6 sm:p-8">
                    <h3 class="text-lg font-semibold text-[#2B3E51]">{{ __('Add a habit') }}</h3>
                    <p class="mt-1 text-sm text-[#2B3E51]/70">{{ __('Keep it minimal. You can always refine later.') }}</p>

                    <form method="POST" action="{{ route('habits.store') }}" class="mt-6 grid gap-4 sm:grid-cols-6 items-end">
                        @csrf

                        <div class="sm:col-span-3">
                            <label for="name" class="text-sm font-medium text-[#2B3E51]">{{ __('Habit') }}</label>
                            <input id="name" name="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-xl border-[#2B3E51]/20 bg-white/70 text-[#2B3E51] placeholder:text-[#2B3E51]/40 focus:border-[#377991] focus:ring-[#377991]/30" placeholder="{{ __('e.g. Study 30 minutes') }}">
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="sm:col-span-2">
                            <label for="category" class="text-sm font-medium text-[#2B3E51]">{{ __('Category (optional)') }}</label>
                            <input id="category" name="category" value="{{ old('category') }}" class="mt-1 block w-full rounded-xl border-[#2B3E51]/20 bg-white/70 text-[#2B3E51] placeholder:text-[#2B3E51]/40 focus:border-[#377991] focus:ring-[#377991]/30" placeholder="{{ __('Health, Learning…') }}">
                            <x-input-error :messages="$errors->get('category')" class="mt-2" />
                        </div>

                        <div class="sm:col-span-1 flex items-center gap-2 pb-1">
                            <input id="is_daily" name="is_daily" type="checkbox" value="1" checked class="rounded border-[#2B3E51]/20 text-[#377991] shadow-sm focus:ring-[#377991]/30">
                            <label for="is_daily" class="text-sm text-[#2B3E51]/75">{{ __('Daily') }}</label>
                        </div>

                        <div class="sm:col-span-6">
                            <button type="submit" class="inline-flex items-center rounded-xl bg-gradient-to-r from-[#377991] to-[#5DA068] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:from-[#2B3E51] hover:to-[#377991] focus:outline-none focus:ring-2 focus:ring-[#377991]/40 focus:ring-offset-2 focus:ring-offset-[#FAF8F5]">
                                {{ __('Add habit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="rounded-2xl border border-[#2B3E51]/10 bg-white/70 shadow-xl backdrop-blur">
                <div class="p-6 sm:p-8">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-[#2B3E51]">{{ __('Your habits') }}</h3>
                            <p class="mt-1 text-sm text-[#2B3E51]/70">{{ __('Add, delete, and mark as daily.') }}</p>
                        </div>
                    </div>

                    <div class="mt-6 divide-y divide-[#2B3E51]/10">
                        @forelse ($habits as $habit)
                            <div class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="font-medium text-[#2B3E51]">{{ $habit->name }}</div>
                                        @if ($habit->category)
                                            <span class="inline-flex items-center rounded-full bg-[#76C7B7]/20 px-2 py-0.5 text-xs font-medium text-[#2B3E51]/80">
                                                {{ $habit->category }}
                                            </span>
                                        @endif

                                        @if ($habit->is_daily)
                                            <span class="inline-flex items-center rounded-full bg-[#5DA068]/20 px-2 py-0.5 text-xs font-medium text-[#2B3E51]/80">
                                                {{ __('Daily') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-[#2B3E51]/10 px-2 py-0.5 text-xs font-medium text-[#2B3E51]/70">
                                                {{ __('Not daily') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <form method="POST" action="{{ route('habits.update', $habit) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_daily" value="{{ $habit->is_daily ? 0 : 1 }}">
                                        <button type="submit" class="inline-flex items-center rounded-xl border border-[#2B3E51]/20 bg-white/60 px-3 py-1.5 text-sm font-medium text-[#2B3E51]/80 shadow-sm hover:bg-white/80 hover:text-[#2B3E51] focus:outline-none focus:ring-2 focus:ring-[#377991]/40 focus:ring-offset-2 focus:ring-offset-[#FAF8F5]">
                                            {{ $habit->is_daily ? __('Set not daily') : __('Set daily') }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('habits.destroy', $habit) }}" onsubmit="return confirm('{{ __('Delete this habit?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center rounded-xl border border-red-500/20 bg-red-500/10 px-3 py-1.5 text-sm font-medium text-red-700 shadow-sm hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-red-500/30 focus:ring-offset-2 focus:ring-offset-[#FAF8F5]">
                                            {{ __('Delete') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="py-10 text-center text-sm text-[#2B3E51]/70">
                                {{ __("You don't have any habits yet. Add your first one above.") }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
