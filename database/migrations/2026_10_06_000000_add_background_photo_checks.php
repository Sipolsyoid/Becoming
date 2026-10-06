<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('habit_completions', function (Blueprint $table) {
            $table->string('verification_status', 20)->nullable();
            $table->uuid('verification_token')->nullable();
            $table->string('pending_photo_path')->nullable();
            $table->text('verification_reason')->nullable();
            $table->timestamp('verification_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('habit_completions', fn (Blueprint $table) => $table->dropColumn([
            'verification_status', 'verification_token', 'pending_photo_path', 'verification_reason', 'verification_requested_at',
        ]));
    }
};
