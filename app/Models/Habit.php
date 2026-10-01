<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\HabitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'category', 'is_daily', 'schedule_type', 'weekdays', 'weekly_target'])]
class Habit extends Model
{
    /** @use HasFactory<HabitFactory> */
    use HasFactory;

    protected $attributes = ['schedule_type' => 'daily'];

    protected function casts(): array
    {
        return ['is_daily' => 'boolean', 'weekdays' => 'array', 'weekly_target' => 'integer'];
    }

    public function isDueOn(CarbonInterface $date): bool
    {
        return $this->is_daily && ($this->schedule_type === 'daily'
            || ($this->schedule_type === 'weekdays' && in_array($date->isoWeekday(), $this->weekdays ?? [], true)));
    }

    public function scheduleLabel(): string
    {
        if (! $this->is_daily) {
            return 'Paused';
        }
        if ($this->schedule_type === 'weekly') {
            return $this->weekly_target.' times per week';
        }
        if ($this->schedule_type === 'weekdays') {
            $names = [1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

            return implode(', ', array_map(fn ($day) => $names[$day], $this->weekdays ?? []));
        }

        return 'Every day';
    }

    /**
     * @return BelongsTo<User, Habit>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<HabitCompletion>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(HabitCompletion::class);
    }
}
