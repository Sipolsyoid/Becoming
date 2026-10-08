<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['habits', 'habit_schedule_versions'] as $table) {
            if (DB::getDriverName() === 'sqlite') {
                // SQLite cannot add CHECK constraints to existing tables; triggers cover direct writes.
                $rule = $this->rule('NEW.', true);
                foreach (['INSERT', 'UPDATE'] as $event) {
                    $name = $table.'_schedule_'.strtolower($event);
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} WHEN NOT ({$rule}) BEGIN SELECT RAISE(ABORT, 'Invalid habit schedule'); END");
                }
            } else {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_schedule_check CHECK (".$this->rule('', false).')');
            }
        }
    }

    private function rule(string $prefix, bool $sqlite): string
    {
        $type = $prefix.'schedule_type';
        $days = $prefix.'weekdays';
        $target = $prefix.'weekly_target';
        if ($sqlite) {
            $validDays = "CASE WHEN json_valid({$days}) THEN json_type({$days}) = 'array' AND json_array_length({$days}) BETWEEN 1 AND 7
                AND NOT EXISTS (SELECT 1 FROM json_each({$days}) WHERE type != 'integer' OR value NOT BETWEEN 1 AND 7)
                AND json_array_length({$days}) = (SELECT COUNT(DISTINCT value) FROM json_each({$days})) ELSE 0 END";
        } else {
            $distinct = implode(' + ', array_map(fn ($day) => "JSON_CONTAINS({$days}, '{$day}')", range(1, 7)));
            $validDays = "JSON_TYPE({$days}) = 'ARRAY' AND JSON_LENGTH({$days}) BETWEEN 1 AND 7
                AND JSON_CONTAINS('[1,2,3,4,5,6,7]', {$days}) = 1 AND JSON_LENGTH({$days}) = ({$distinct})";
        }

        // COALESCE prevents SQL NULL from bypassing a CHECK or trigger condition.
        return "COALESCE(({$type} = 'daily' AND {$days} IS NULL AND {$target} IS NULL)
            OR ({$type} = 'weekly' AND {$days} IS NULL AND {$target} BETWEEN 1 AND 7)
            OR ({$type} = 'weekdays' AND {$target} IS NULL AND ({$validDays})), 0) = 1";
    }

    public function down(): void
    {
        foreach (['habits', 'habit_schedule_versions'] as $table) {
            if (DB::getDriverName() === 'sqlite') {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_schedule_insert");
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_schedule_update");
            } else {
                DB::statement("ALTER TABLE {$table} DROP CHECK {$table}_schedule_check");
            }
        }
    }
};
