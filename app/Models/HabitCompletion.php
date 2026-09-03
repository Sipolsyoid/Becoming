<?php

namespace App\Models;

use Database\Factories\HabitCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['habit_id', 'user_id', 'completed_on'])]
class HabitCompletion extends Model
{
    /** @use HasFactory<HabitCompletionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Habit, HabitCompletion>
     */
    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }

    /**
     * @return BelongsTo<User, HabitCompletion>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

