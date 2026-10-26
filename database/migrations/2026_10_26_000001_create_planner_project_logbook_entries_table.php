<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projekt-Logbuch: chronologisch, append-only. Gegenstueck zum Canvas (Zustand,
 * ueberschreibbar) — hier steht der Verlauf: Entscheidungen, Meilensteine, Risiken,
 * jeweils mit Begruendung. Korrekturen laufen ueber einen neuen Eintrag mit
 * supersedes_entry_id, nie ueber UPDATE/DELETE. Daher bewusst ohne softDeletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planner_project_logbook_entries')) {
            return;
        }

        Schema::create('planner_project_logbook_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('project_id')
                ->constrained('planner_projects', 'id', 'pple_proj_fk')
                ->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()
                ->constrained('teams', 'id', 'pple_team_fk')
                ->nullOnDelete();

            $table->string('type', 16); // decision|milestone|risk
            $table->string('title');
            $table->text('rationale')->nullable(); // das "warum"
            $table->date('occurred_on');

            // Duenne Verweise statt Wiederholung: [{type: task|commit|canvas|canvas_snapshot|url, ref, label?}]
            $table->json('refs')->nullable();

            $table->foreignId('supersedes_entry_id')->nullable()
                ->constrained('planner_project_logbook_entries', 'id', 'pple_supersedes_fk')
                ->nullOnDelete();

            $table->string('source', 16)->default('manual'); // manual|draft|agent
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users', 'id', 'pple_user_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'occurred_on'], 'pple_proj_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_project_logbook_entries');
    }
};
