<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('planner_recurring_tasks', function (Blueprint $table) {
            // Wochentag-Maske: Bitfeld (Mo=1, Di=2, Mi=4, Do=8, Fr=16, Sa=32, So=64)
            // Wird bei recurrence_type = weekly genutzt; null = wie bisher gleicher Wochentag
            if (!Schema::hasColumn('planner_recurring_tasks', 'weekday_mask')) {
                $table->unsignedTinyInteger('weekday_mask')->nullable()->after('recurrence_interval');
            }

            // Monatsmuster (nur bei recurrence_type = monthly relevant)
            // 'day_of_month' → an einem konkreten Tag (z. B. 5., 31., -1 = letzter)
            // 'ordinal_weekday' → am N-ten Wochentag (z. B. 1. Montag, letzter Freitag)
            if (!Schema::hasColumn('planner_recurring_tasks', 'monthly_pattern')) {
                $table->string('monthly_pattern', 32)->nullable()->after('weekday_mask');
            }
            if (!Schema::hasColumn('planner_recurring_tasks', 'monthly_day_of_month')) {
                // 1..31, oder -1 für „letzter Tag des Monats"
                $table->smallInteger('monthly_day_of_month')->nullable()->after('monthly_pattern');
            }
            if (!Schema::hasColumn('planner_recurring_tasks', 'monthly_ordinal')) {
                // 1..4, oder -1 für „letzter"
                $table->smallInteger('monthly_ordinal')->nullable()->after('monthly_day_of_month');
            }
            if (!Schema::hasColumn('planner_recurring_tasks', 'monthly_weekday')) {
                // 0..6 (Mo..So)
                $table->unsignedTinyInteger('monthly_weekday')->nullable()->after('monthly_ordinal');
            }

            // Vorlauf in Tagen: 0 = exakt am Fälligkeitstag erstellen, >0 = X Tage vorher
            if (!Schema::hasColumn('planner_recurring_tasks', 'lead_time_days')) {
                $table->unsignedSmallInteger('lead_time_days')->default(0)->after('monthly_weekday');
            }

            // Wenn aktiviert: sobald die zuletzt erstellte Aufgabe erledigt oder gelöscht wird,
            // sofort die nächste anlegen (statt nur per Cron)
            if (!Schema::hasColumn('planner_recurring_tasks', 'chain_on_complete')) {
                $table->boolean('chain_on_complete')->default(false)->after('lead_time_days');
            }

            // Maximal-Wiederholungen (alternative oder zusätzliche End-Bedingung)
            if (!Schema::hasColumn('planner_recurring_tasks', 'max_occurrences')) {
                $table->unsignedInteger('max_occurrences')->nullable()->after('chain_on_complete');
            }
            if (!Schema::hasColumn('planner_recurring_tasks', 'occurrences_count')) {
                $table->unsignedInteger('occurrences_count')->default(0)->after('max_occurrences');
            }

            // Wenn aktiviert und das berechnete Datum fällt auf Sa/So → auf nächsten Montag verschieben
            if (!Schema::hasColumn('planner_recurring_tasks', 'skip_weekends')) {
                $table->boolean('skip_weekends')->default(false)->after('occurrences_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planner_recurring_tasks', function (Blueprint $table) {
            foreach ([
                'skip_weekends',
                'occurrences_count',
                'max_occurrences',
                'chain_on_complete',
                'lead_time_days',
                'monthly_weekday',
                'monthly_ordinal',
                'monthly_day_of_month',
                'monthly_pattern',
                'weekday_mask',
            ] as $col) {
                if (Schema::hasColumn('planner_recurring_tasks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
