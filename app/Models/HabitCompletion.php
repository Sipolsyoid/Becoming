<?php

namespace App\Models;

use Database\Factories\HabitCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'habit_id',
    'user_id',
    'completed_on',
    'photo_path',
    'ai_status',
    'ai_reason',
    'ai_result',
    'analyzed_at',
    'verification_status',
    'verification_token',
    'pending_photo_path',
    'verification_reason',
    'verification_requested_at',
])]
class HabitCompletion extends Model
{
    /** @use HasFactory<HabitCompletionFactory> */
    use HasFactory;

    protected function completedOn(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $this->asDateTime($value)->format('Y-m-d'),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_on' => 'date',
            'ai_result' => 'array',
            'analyzed_at' => 'datetime',
            'verification_requested_at' => 'datetime',
        ];
    }

    public function checkState(): array
    {
        return [
            'status' => $this->verification_status,
            'reason' => $this->verification_reason,
            'date' => $this->completed_on->toDateString(),
            'approved' => $this->ai_status === 'approved',
            'can_retry' => $this->pending_photo_path && ($this->verification_status === 'failed' || $this->verification_requested_at?->lt(now()->subMinutes(10))),
            'status_url' => route('checks.show', $this),
            'retry_url' => route('checks.retry', $this),
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
