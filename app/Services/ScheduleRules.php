<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ScheduleRules
{
    public static function validate(array $values): array
    {
        $data = Validator::make($values, [
            'schedule_type' => ['required', Rule::in(['daily', 'weekdays', 'weekly'])],
            'weekdays' => ['nullable', 'required_if:schedule_type,weekdays', 'prohibited_unless:schedule_type,weekdays', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'weekly_target' => ['nullable', 'required_if:schedule_type,weekly', 'prohibited_unless:schedule_type,weekly', 'integer', 'between:1,7'],
        ])->validate();
        if ($data['schedule_type'] === 'weekdays') {
            $data['weekdays'] = array_map('intval', $data['weekdays']);
            sort($data['weekdays']);
        }

        return $data;
    }
}
