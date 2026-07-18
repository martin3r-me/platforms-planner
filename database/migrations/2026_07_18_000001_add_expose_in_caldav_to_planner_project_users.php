<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_project_users', function (Blueprint $table) {
            // Pro User pro Projekt: Aufgaben dieses Projekts im CalDAV-Account
            // als eigene Liste zeigen? Default aus (kein Listen-Wildwuchs).
            $table->boolean('expose_in_caldav')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('planner_project_users', function (Blueprint $table) {
            $table->dropColumn('expose_in_caldav');
        });
    }
};
