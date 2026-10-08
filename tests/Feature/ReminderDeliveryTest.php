<?php

use App\Models\Habit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

test('an interrupted SMTP attempt is not sent again even without a last sent date', function () {
    $this->travelTo(Carbon::parse('2026-10-06 19:00:00', 'UTC'));
    Notification::fake();
    $user = User::factory()->create(['reminders_enabled' => true]);
    Habit::factory()->create(['user_id' => $user->id]);
    DB::table('reminder_deliveries')->insert([
        'user_id' => $user->id, 'local_date' => '2026-10-06',
        'status' => 'sending', 'attempted_at' => now()->subMinutes(20),
    ]);
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertNothingSent();
    expect($user->fresh()->reminder_last_sent_on)->toBeNull();
});

test('a crash after SMTP acceptance and before saving the receipt cannot resend the digest', function () {
    $this->travelTo(Carbon::parse('2026-10-06 19:00:00', 'UTC'));
    $user = User::factory()->create(['reminders_enabled' => true]);
    Habit::factory()->create(['user_id' => $user->id]);
    Notification::shouldReceive('send')->once()->andReturnUsing(function () {
        throw new RuntimeException('Simulated interruption after transport acceptance');
    });
    $this->artisan('habits:send-reminders')->assertFailed();
    Notification::fake();
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertNothingSent();
    expect(DB::table('reminder_deliveries')->count())->toBe(1);
});

test('deleting an account also deletes its reminder delivery records', function () {
    $user = User::factory()->create();
    DB::table('reminder_deliveries')->insert([
        'user_id' => $user->id, 'local_date' => '2026-10-06', 'status' => 'sent', 'attempted_at' => now(),
    ]);
    $user->delete();
    expect(DB::table('reminder_deliveries')->count())->toBe(0);
});
