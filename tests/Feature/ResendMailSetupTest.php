<?php

use App\Models\Habit;
use App\Models\User;
use App\Notifications\HabitReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;

test('private demo setup preserves unrelated environment settings and hides the key', function () {
    $original = app()->environmentPath();
    $folder = storage_path('framework/testing/mail-setup-'.bin2hex(random_bytes(6)));
    File::ensureDirectoryExists($folder);
    File::put($folder.'/.env', "APP_NAME=Becoming\nDB_DATABASE=untouched\nMAIL_HOST=old-host\nMAIL_PASSWORD=old-secret\n");
    app()->useEnvironmentPath($folder);
    try {
        $this->artisan('mail:setup-resend --demo')
            ->expectsQuestion('Email address used for your Resend account (only this inbox can receive demo mail)', 'owner@becoming.test')
            ->expectsQuestion('Resend API key (hidden; do not paste it in chat)', 're_fake_fixture_key_12345')
            ->doesntExpectOutputToContain('re_fake_fixture_key_12345')->assertSuccessful();
        $values = Dotenv\Dotenv::parse(File::get($folder.'/.env'));
        expect($values)->toMatchArray(['DB_DATABASE' => 'untouched', 'MAIL_HOST' => 'smtp.resend.com',
            'MAIL_SCHEME' => 'smtps', 'MAIL_PORT' => '465', 'MAIL_DEMO_RECIPIENT' => 'owner@becoming.test',
            'MAIL_FROM_ADDRESS' => 'onboarding@resend.dev', 'MAIL_PASSWORD' => 're_fake_fixture_key_12345']);
        expect(glob($folder.'/.env.mail-setup-*'))->toBe([]);
    } finally {
        app()->useEnvironmentPath($original);
        File::delete($folder.'/.env');
        rmdir($folder);
    }
});

test('demo reminders exclude other inboxes before claiming a delivery', function () {
    $this->travelTo(Carbon::parse('2026-10-06 19:00:00', 'UTC'));
    config(['mail.demo_recipient' => 'owner@becoming.test']);
    Notification::fake();
    $owner = User::factory()->create(['email' => 'owner@becoming.test', 'reminders_enabled' => true]);
    $other = User::factory()->create(['email' => 'other@becoming.test', 'reminders_enabled' => true]);
    Habit::factory()->create(['user_id' => $owner->id]);
    Habit::factory()->create(['user_id' => $other->id]);
    $this->artisan('habits:send-reminders')->assertSuccessful();
    Notification::assertSentToTimes($owner, HabitReminder::class, 1);
    Notification::assertNotSentTo($other, HabitReminder::class);
    $this->assertDatabaseCount('reminder_deliveries', 1);
    $this->actingAs($other)->get('/settings')->assertSee('Email is in demonstration mode');
    $this->artisan('habits:test-reminder', ['email' => $other->email, '--send' => true])->assertFailed();
});
