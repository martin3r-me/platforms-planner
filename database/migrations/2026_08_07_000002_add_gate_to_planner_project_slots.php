<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slot-Gate (Phasen-Sequenz): ist blocked_until_previous_done gesetzt, sind
 * die Tasks dieses Slots erst ausführbar, wenn ALLE Tasks in Slots mit
 * kleinerer `order` (im selben Projekt) terminal sind (erledigt/verworfen).
 *
 * Opt-in pro Slot — Sammel-/Backlog-Slots bleiben ungegatet. Die grobe
 * Phasen-Sequenz kostet so keine einzige Task-Kante; feine Ausnahmen decken
 * die Task-Abhängigkeiten ab. Durchgesetzt im Claim (PlannerTask::notBlocked).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_project_slots', function (Blueprint $table) {
            $table->boolean('blocked_until_previous_done')->default(false)->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('planner_project_slots', function (Blueprint $table) {
            $table->dropColumn('blocked_until_previous_done');
        });
    }
};
