<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HabitReminder extends Notification
{
    public function __construct(public array $habitNames, public string $localDate) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('A little time for your habits')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your habits for '.$this->localDate.' ('.$notifiable->timezone.') still have room for a small step:');

        foreach ($this->habitNames as $name) {
            $mail->line('• '.$name);
        }

        return $mail->action('Open Becoming', route('dashboard'))
            ->line('Already working on them? You can carry on at your own pace.')
            ->line('Change the time or turn reminders off in [Settings]('.route('settings.edit').').');
    }
}
