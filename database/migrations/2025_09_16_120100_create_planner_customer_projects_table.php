<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('planner_customer_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();

            $table->string('billing_method', 30)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('budget_amount', 12, 2)->nullable();
            $table->string('cost_center', 64)->nullable();
            $table->string('invoice_account', 64)->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('billing_status', 30)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('project_id', 'pcp_project_fk')
                ->references('id')
                ->on('planner_projects')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_customer_projects');
    }
};


