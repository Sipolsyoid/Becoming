<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return; // Existing SQLite triggers already check each JSON element's integer type.
        }
        $checks = implode(' AND ', array_map(fn ($index) => "(JSON_LENGTH(weekdays) <= {$index} OR JSON_TYPE(JSON_EXTRACT(weekdays, '$[{$index}]')) = 'INTEGER')", range(0, 6)));
        foreach (['habits', 'habit_schedule_versions'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_weekday_integer_check CHECK (schedule_type != 'weekdays' OR ({$checks}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            foreach (['habits', 'habit_schedule_versions'] as $table) {
                DB::statement("ALTER TABLE {$table} DROP CHECK {$table}_weekday_integer_check");
            }
        }
    }
};
