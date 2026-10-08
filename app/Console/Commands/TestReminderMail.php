<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\HabitReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TestReminderMail extends Command
{
    protected $signature = 'habits:test-reminder {email} {--send : Send one real test email; default is preview only} {--timezone=UTC}';

    protected $description = 'Test the real reminder email without creating an account or changing reminder state';

    public function handle(): int
    {
        $values = ['email' => $this->argument('email'), 'timezone' => $this->option('timezone')];
        $validator = Validator::make($values, ['email' => ['required', 'email'], 'timezone' => ['required', 'timezone']]);
        if ($validator->fails()) {
            $this->error('Provide a valid recipient email and timezone.');

            return self::FAILURE;
        }
        $user = new User(['name' => 'Becoming delivery test', ...$values]);
        $reminder = new HabitReminder(['This is a test reminder; no habit progress was changed.'], $user->localToday()->toDateString());
        if (! $this->option('send')) {
            $this->info('Preview only. Add --send to send one reminder email through the configured mailer.');

            return self::SUCCESS;
        }
        if (in_array(config('mail.default'), ['log', 'array', 'failover', 'roundrobin'], true)) {
            $this->error('Select a single real mail transport; preview or fallback mailers cannot establish delivery.');

            return self::FAILURE;
        }
        $sender = config('mail.from.address', '');
        if (! filter_var($sender, FILTER_VALIDATE_EMAIL) || preg_match('/@(example\.(com|org|net)|localhost)$/i', $sender)) {
            $this->error('Configure a valid verified MAIL_FROM_ADDRESS; a placeholder sender cannot establish inbox delivery.');

            return self::FAILURE;
        }
        try {
            $user->notify($reminder);
            $this->info('Test reminder accepted by the configured mail transport. Confirm receipt in the inbox (and spam folder).');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Do not print transport exceptions, which may contain private server/recipient details.
            $this->error('Mail transport failed. Check private configuration and provider logs; do not blindly resend an uncertain attempt.');

            return self::FAILURE;
        }
    }
}
