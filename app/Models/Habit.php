<?php

namespace App\Models;

use Database\Factories\HabitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'category', 'is_daily'])]
class Habit extends Model
{
    /** @use HasFactory<HabitFactory> */
    use HasFactory;

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

