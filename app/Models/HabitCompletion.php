<?php

namespace App\Models;

use Database\Factories\HabitCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

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
    'verification_started_at',
    'verification_recoveries',
    'verification_habit_name',
    'photo_habit_name',
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
            'verification_started_at' => 'datetime',
            'verification_recoveries' => 'integer',
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
            'can_cancel' => $this->verification_status === 'queued',
            'cancel_url' => route('checks.cancel', $this),
            'worker_note' => $this->verification_status === 'queued' && $this->verification_requested_at?->lt(now()->subMinutes(2))
                && ! Cache::has('photos.worker_seen_at')
                ? 'The photo worker has not been seen recently. Your photo is saved and will wait until checking resumes.' : null,
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
