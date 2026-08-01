<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projekt-Gedächtnis für den Backoffice-Worker: beim Abschließen einer Task destilliert er
 * eine wiederverwendbare Lektion (Muster/Gotcha/„so macht man X in diesem Projekt") →
 * `agent_lessons`. Künftige Tasks desselben Projekts bekommen sie in den Prompt. Analog zu
 * Helpdesk (`resolution` je Board) und Dev (`.worker/lessons.md` je Package) — hier projekt-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->text('agent_lessons')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->dropColumn('agent_lessons');
        });
    }
};
