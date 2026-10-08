<x-app-layout>
    <x-slot name="header"><h1>Settings</h1></x-slot>
    <div class="page-content">
        <form method="POST" action="{{ route('settings.update') }}" class="habit-card max-w-2xl space-y-8">
            @csrf
            @method('PATCH')
            <section class="space-y-3" x-data="{ detected: Intl.DateTimeFormat().resolvedOptions().timeZone }">
                <h2 class="text-xl font-semibold">Your local day</h2>
                <p class="quiet-note !px-0">Today, weekly goals, and reminders follow your timezone, including daylight saving changes.</p>
                <label for="timezone" class="block text-sm font-medium">Timezone</label>
                <select id="timezone" name="timezone" x-ref="timezone" class="block w-full rounded-xl border-slate-300" required>
                    @foreach ($timezones as $timezone)
                        <option value="{{ $timezone }}" @selected(old('timezone', $user->timezone) === $timezone)>{{ str_replace('_', ' ', $timezone) }}</option>
                    @endforeach
                </select>
                <button type="button" x-cloak x-show="Array.from($refs.timezone.options).some(option => option.value === detected)" @click="$refs.timezone.value = detected" class="text-sm font-semibold underline underline-offset-4">Use device timezone <span x-text="'(' + detected + ')'"></span></button>
                <x-input-error :messages="$errors->get('timezone')" />
                <p class="quiet-note !px-0">Changing timezone affects future check-ins. Existing completions keep their recorded dates.</p>
            </section>

            <section class="space-y-3 border-t pt-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold">Email reminders</h2>
                    <span class="status-pill">{{ $user->reminders_enabled ? 'On · '.$user->reminder_time : 'Off' }}</span>
                </div>
                <p class="quiet-note !px-0">A gentle daily email to {{ $user->email }} when you have habits left to do. Completed habits, rest days, and paused habits are skipped.</p>
                <input type="hidden" name="reminders_enabled" value="0">
                <label class="flex items-center gap-3 py-2" for="reminders_enabled">
                    <input id="reminders_enabled" type="checkbox" name="reminders_enabled" value="1" @checked(old('reminders_enabled', $user->reminders_enabled)) class="rounded text-green-800">
                    <span>Enable email reminders</span>
                </label>
                <x-input-error :messages="$errors->get('reminders_enabled')" />
                <label for="reminder_time" class="block text-sm font-medium">Reminder time</label>
                <input id="reminder_time" name="reminder_time" type="time" value="{{ old('reminder_time', $user->reminder_time) }}" class="block rounded-xl" required>
                <x-input-error :messages="$errors->get('reminder_time')" />
                <p class="quiet-note !px-0">Uses the timezone selected above. To stop reminders, uncheck “Enable email reminders” and save.</p>
                @if ($mailPreviewOnly)
                    <p class="error-message !mt-3" role="status">Email delivery is in preview mode. Reminders will not reach your inbox until outgoing email is configured.</p>
                @endif
            </section>
            <p class="quiet-note">Changing timezone can change today's date and the current week. Existing check-in dates stay as recorded; photos already submitted keep their captured date.</p>
            <button class="action-button" type="submit">Save preferences</button>
        </form>
        <section class="insight-card mt-6" aria-labelledby="delete-account-heading">
            <h2 id="delete-account-heading" class="text-lg font-semibold">Delete your account</h2>
            <p class="mt-2 text-sm">This permanently deletes your account, habits, check-in history and uploaded photos.</p>
            <form method="POST" action="{{ route('account.destroy') }}" class="mt-4 space-y-3" onsubmit="return confirm('Permanently delete your account and photos? This cannot be undone.');">
                @csrf @method('DELETE')
                <label for="delete-password" class="block text-sm font-medium">Current password</label>
                <input id="delete-password" type="password" name="password" required autocomplete="current-password" class="block rounded-xl">
                <x-input-error :messages="$errors->userDeletion->get('password')" />
                <button class="action-button !bg-red-700" type="submit">Permanently delete account</button>
            </form>
        </section>
    </div>
</x-app-layout>
