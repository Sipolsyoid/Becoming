<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('habits', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable();
            $table->boolean('archived_was_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('habits', fn (Blueprint $table) => $table->dropColumn(['archived_at', 'archived_was_active', 'sort_order']));
    }
};
