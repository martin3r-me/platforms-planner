<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->unique()->after('done_at');
            $table->boolean('is_public')->default(false)->after('public_token');
            $table->timestamp('public_token_expires_at')->nullable()->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->dropColumn(['public_token', 'is_public', 'public_token_expires_at']);
        });
    }
};
