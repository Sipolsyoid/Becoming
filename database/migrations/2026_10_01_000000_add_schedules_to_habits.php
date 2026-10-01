<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('habits', function (Blueprint $table) {
            $table->string('schedule_type', 16)->default('daily');
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('weekly_target')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('habits', fn (Blueprint $table) => $table->dropColumn(['schedule_type', 'weekdays', 'weekly_target']));
    }
};
