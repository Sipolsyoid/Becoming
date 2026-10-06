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

    protected static function booted(): void
    {
        static::creating(function (Habit $habit) {
            $habit->sort_order = ((int) static::where('user_id', $habit->user_id)->max('sort_order')) + 1;
        });

        static::saved(function (Habit $habit) {
            $isCreation = $habit->wasRecentlyCreated && $habit->getChanges() === [];
            if (! $isCreation && ! $habit->wasChanged(['is_daily', 'schedule_type', 'weekdays', 'weekly_target'])) {
                return;
            }
            $date = $isCreation
                ? $habit->created_at->copy()->setTimezone($habit->user->timezone)->toDateString()
                : $habit->user->localToday()->toDateString();
            // A local calendar day has one effective schedule; editing today never rewrites yesterday.
            $habit->scheduleVersions()->updateOrCreate(['effective_on' => $date], [
                'is_active' => $habit->is_daily, 'schedule_type' => $habit->schedule_type,
                'weekdays' => $habit->weekdays, 'weekly_target' => $habit->weekly_target,
            ]);
            $habit->unsetRelation('scheduleVersions');
        });
    }

    public function scheduleVersions(): HasMany
    {
        return $this->hasMany(HabitScheduleVersion::class)->orderBy('effective_on');
    }

    public function scheduleOn(CarbonInterface $date): ?HabitScheduleVersion
    {
        return $this->scheduleVersions->last(fn ($version) => $version->effective_on <= $date->toDateString());
    }

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'archived_was_active' => 'boolean', 'sort_order' => 'integer', 'is_daily' => 'boolean', 'weekdays' => 'array', 'weekly_target' => 'integer'];
    }

    public function isDueOn(CarbonInterface $date): bool
    {
        $schedule = $this->scheduleOn($date);

        return $schedule?->is_active && ($schedule->schedule_type === 'daily'
            || ($schedule->schedule_type === 'weekdays' && in_array($date->isoWeekday(), $schedule->weekdays ?? [], true)));
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
