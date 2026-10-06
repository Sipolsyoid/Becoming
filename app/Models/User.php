<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'email', 'password', 'timezone', 'reminders_enabled', 'reminder_time'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = ['timezone' => 'UTC', 'reminders_enabled' => false, 'reminder_time' => '18:00'];

    public function localNow(): Carbon
    {
        return Carbon::now($this->timezone);
    }

    public function localToday(): Carbon
    {
        return $this->localNow()->startOfDay();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'reminders_enabled' => 'boolean',
            'reminder_last_sent_on' => 'date',
        ];
    }

    /**
     * @return HasMany<Habit>
     */
    public function habits(): HasMany
    {
        return $this->hasMany(Habit::class);
    }

    /**
     * @return HasMany<HabitCompletion>
     */
    public function habitCompletions(): HasMany
    {
        return $this->hasMany(HabitCompletion::class);
    }
}
