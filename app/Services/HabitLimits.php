<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class HabitLimits
{
    // Call while holding the owner's row lock, before creating/activating a habit.
    public function check(User $user, bool $creating, bool $activating): void
    {
        if ($creating && $user->habits()->count() >= config('habits.max_total')) {
            throw ValidationException::withMessages(['name' => 'Your account has reached its habit limit. Delete an unused habit before adding another.']);
        }
        if ($activating && $user->habits()->whereNull('archived_at')->where('is_daily', true)->count() >= config('habits.max_active')) {
            throw ValidationException::withMessages(['is_daily' => 'Your active habit limit has been reached. Pause or archive a habit before activating another.']);
        }
    }
}
