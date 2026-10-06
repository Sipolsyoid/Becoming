<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HabitScheduleVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'weekdays' => 'array', 'weekly_target' => 'integer'];
    }
}
