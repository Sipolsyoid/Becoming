<?php

use App\Models\User;
use App\Notifications\HabitReminder;
use Illuminate\Support\Facades\Notification;

test('test email previews by default and requires a single real transport', function () {
    Notification::fake();
    $this->artisan('habits:test-reminder', ['email' => 'test@example.com'])->assertSuccessful();
    config(['mail.default' => 'log']);
    $this->artisan('habits:test-reminder', ['email' => 'test@example.com', '--send' => true])->assertFailed();
    Notification::assertNothingSent();
});

test('test email sends only to the requested recipient without persisting an account', function () {
    config(['mail.default' => 'smtp']);
    config(['mail.from.address' => 'reminders@becoming.test']);
    Notification::shouldReceive('send')->once()->withArgs(function ($notifiable, $notification) {
        expect($notifiable)->toBeInstanceOf(User::class);
        expect($notifiable->email)->toBe('test@example.com');
        expect($notifiable->exists)->toBeFalse();
        expect($notification)->toBeInstanceOf(HabitReminder::class);

        return true;
    });
    $this->artisan('habits:test-reminder', ['email' => 'test@example.com', '--send' => true])->assertSuccessful();
    expect(User::count())->toBe(0);
    $this->assertDatabaseCount('reminder_deliveries', 0);
});

test('test email validates recipients and hides sensitive transport exception details', function () {
    $this->artisan('habits:test-reminder', ['email' => 'invalid', '--send' => true])->assertFailed();
    config(['mail.default' => 'smtp']);
    config(['mail.from.address' => 'reminders@becoming.test']);
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('private credentials and address'));
    $this->artisan('habits:test-reminder', ['email' => 'test@example.com', '--send' => true])
        ->doesntExpectOutputToContain('private credentials')->assertFailed();
});

test('test email refuses a placeholder sender before contacting the transport', function () {
    config(['mail.default' => 'smtp', 'mail.from.address' => 'hello@example.com']);
    Notification::fake();
    $this->artisan('habits:test-reminder', ['email' => 'test@example.com', '--send' => true])->assertFailed();
    Notification::assertNothingSent();
});
