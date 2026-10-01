<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HabitSchedule
{
    public static function validate(Request $request): array
    {
        $data = $request->validate([
            'schedule_type' => ['required', Rule::in(['daily', 'weekdays', 'weekly'])],
            'weekdays' => ['exclude_unless:schedule_type,weekdays', 'required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'weekly_target' => ['exclude_unless:schedule_type,weekly', 'required', 'integer', 'between:1,7'],
        ]);
        $days = array_map('intval', $data['weekdays'] ?? []);
        sort($days);

        return [
            'schedule_type' => $data['schedule_type'],
            'weekdays' => $data['schedule_type'] === 'weekdays' ? $days : null,
            'weekly_target' => $data['schedule_type'] === 'weekly' ? (int) $data['weekly_target'] : null,
        ];
    }
}
