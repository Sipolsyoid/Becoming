<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupResendMail extends Command
{
    protected $signature = 'mail:setup-resend {--demo : Restrict the test sender to your Resend account inbox}';

    protected $description = 'Configure Resend SMTP using a hidden credential prompt; never sends email';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively in your own terminal; keys must not be supplied as arguments.');

            return self::FAILURE;
        }
        $email = $this->ask($this->option('demo') ? 'Email address used for your Resend account (only this inbox can receive demo mail)' : 'Sender address on your verified Resend domain');
        if (! is_string($email) || ! preg_match('/\A[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}\z/', $email)
            || ! filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/@example\.(com|org|net)$/i', $email)) {
            $this->error('Enter a real email address.');

            return self::FAILURE;
        }
        $key = $this->secret('Resend API key (hidden; do not paste it in chat)');
        if (! is_string($key) || ! preg_match('/\Are_[a-zA-Z0-9_\-]{10,}\z/', $key)) {
            $this->error('The key format is invalid. No configuration was changed.');

            return self::FAILURE;
        }
        $path = app()->environmentFilePath();
        if (! File::isFile($path)) {
            $this->error('Create the private .env from .env.example first.');

            return self::FAILURE;
        }
        $settings = ['MAIL_MAILER' => 'smtp', 'MAIL_HOST' => 'smtp.resend.com', 'MAIL_PORT' => '465',
            'MAIL_SCHEME' => 'smtps', 'MAIL_URL' => '', 'MAIL_USERNAME' => 'resend', 'MAIL_PASSWORD' => $key,
            'MAIL_FROM_ADDRESS' => $this->option('demo') ? 'onboarding@resend.dev' : $email,
            'MAIL_FROM_NAME' => 'Becoming', 'MAIL_DEMO_RECIPIENT' => $this->option('demo') ? $email : ''];
        $content = File::get($path);
        foreach ($settings as $name => $value) {
            $line = $name.'="'.$value.'"';
            $pattern = '/^'.preg_quote($name, '/').'=.*$/m';
            $content = preg_match($pattern, $content)
                ? preg_replace_callback($pattern, fn () => $line, $content)
                : rtrim($content).PHP_EOL.$line.PHP_EOL;
        }
        $temporary = tempnam(dirname($path), '.env.mail-setup-');
        try {
            File::put($temporary, $content);
            if (! rename($temporary, $path)) {
                $this->error('Unable to save private configuration.');

                return self::FAILURE;
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
        $this->call('config:clear');
        $this->info('Private mail settings saved. No email was sent. Restart background services, then run habits:test-reminder with --send and confirm the inbox.');
        if ($this->option('demo')) {
            $this->warn('Demo mode sends only to your Resend account inbox. Other users are excluded; verify your own domain for normal delivery.');
        }

        return self::SUCCESS;
    }
}
