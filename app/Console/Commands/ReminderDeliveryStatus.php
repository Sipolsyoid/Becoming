<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReminderDeliveryStatus extends Command
{
    protected $signature = 'habits:reminder-status';

    protected $description = 'List uncertain or interrupted reminder attempts without exposing email addresses';

    public function handle(): int
    {
        $rows = DB::table('reminder_deliveries')->where('status', 'uncertain')
            ->orWhere(fn ($query) => $query->where('status', 'sending')->where('attempted_at', '<', now()->subMinutes(5)))
            ->orderBy('id')->limit(100)->get(['id', 'user_id', 'local_date', 'status', 'attempted_at']);
        $this->table(['Delivery ID', 'User ID', 'Local date', 'Status', 'Attempted UTC'], $rows->map(fn ($row) => (array) $row)->all());
        $this->info('Check provider logs or the inbox before taking action. These attempts are not automatically resent. Showing at most 100 records.');

        return self::SUCCESS;
    }
}
