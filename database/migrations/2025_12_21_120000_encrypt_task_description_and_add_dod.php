<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            // description zu TEXT ändern (falls noch nicht TEXT) und Hash-Spalte hinzufügen
            $table->text('description')->nullable()->change();
            $table->char('description_hash', 64)->nullable()->after('description');
            $table->index('description_hash', 'idx_description_hash');
            
            // Definition of Done (dod) hinzufügen
            $table->text('dod')->nullable()->after('description');
            $table->char('dod_hash', 64)->nullable()->after('dod');
            $table->index('dod_hash', 'idx_dod_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_dod_hash');
            $table->dropIndex('idx_description_hash');
            $table->dropColumn('dod_hash');
            $table->dropColumn('dod');
            $table->dropColumn('description_hash');
        });
    }
};

