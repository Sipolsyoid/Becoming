<?php

namespace App\Http\Controllers;

use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings', [
            'user' => $request->user(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'mailPreviewOnly' => in_array(config('mail.default'), ['log', 'array'], true),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $values = $request->validate([
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'reminders_enabled' => ['required', 'boolean'],
            'reminder_time' => ['required_if:reminders_enabled,1', 'nullable', 'date_format:H:i'],
        ]);
        $values['reminder_time'] = $values['reminder_time'] ?? $request->user()->reminder_time;
        $request->user()->update($values);

        return to_route('settings.edit')->with('status', 'Your preferences have been saved.');
    }
}
