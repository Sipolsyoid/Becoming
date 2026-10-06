<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('reminders_enabled')->default(false);
            $table->string('reminder_time', 5)->default('18:00');
            $table->date('reminder_last_sent_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'timezone', 'reminders_enabled', 'reminder_time', 'reminder_last_sent_on',
        ]));
    }
};
