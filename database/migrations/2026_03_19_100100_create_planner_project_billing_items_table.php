<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_project_billing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('planner_projects')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams');
            $table->foreignId('user_id')->constrained('users');
            $table->date('service_date')->nullable();
            $table->text('description')->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('quantity', 12, 4)->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->boolean('billable')->default(true);
            $table->string('cost_center', 64)->nullable();
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('gross_amount', 12, 2)->nullable();
            $table->string('invoice_number', 64)->nullable();
            $table->date('invoiced_at')->nullable();
            $table->foreignId('task_id')->nullable()->constrained('planner_tasks')->nullOnDelete();
            $table->string('external_ref', 128)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_project_billing_items');
    }
};
