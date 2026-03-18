<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->string('billing_method', 30)->nullable()->after('project_type');
            $table->decimal('hourly_rate', 12, 2)->nullable()->after('billing_method');
            $table->decimal('budget_amount', 12, 2)->nullable()->after('hourly_rate');
            $table->string('currency', 3)->nullable()->default('EUR')->after('budget_amount');
        });
    }

    public function down(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->dropColumn(['billing_method', 'hourly_rate', 'budget_amount', 'currency']);
        });
    }
};
