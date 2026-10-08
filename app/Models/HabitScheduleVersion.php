<?php

namespace App\Models;

use App\Services\ScheduleRules;
use Illuminate\Database\Eloquent\Model;

class HabitScheduleVersion extends Model
{
    protected static function booted(): void
    {
        static::saving(function (HabitScheduleVersion $version) {
            $values = ScheduleRules::validate(['schedule_type' => $version->schedule_type, 'weekdays' => $version->weekdays, 'weekly_target' => $version->weekly_target]);
            $version->weekdays = $values['weekdays'] ?? null;
        });
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'weekdays' => 'array', 'weekly_target' => 'integer'];
    }
}
