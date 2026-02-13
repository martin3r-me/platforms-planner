<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('planner_customer_project_billing_items')) {
            Schema::create('planner_customer_project_billing_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_project_id')->index();
                $table->unsignedBigInteger('team_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();

                $table->date('service_date')->nullable();
                $table->string('description', 255);
                $table->string('unit', 20)->default('hour');
                $table->decimal('quantity', 10, 2)->default(0);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->string('currency', 3)->default('EUR');
                $table->decimal('tax_rate', 5, 2)->nullable();
                $table->boolean('billable')->default(true);
                $table->string('cost_center', 64)->nullable();

                $table->decimal('net_amount', 12, 2)->nullable();
                $table->decimal('tax_amount', 12, 2)->nullable();
                $table->decimal('gross_amount', 12, 2)->nullable();

                $table->string('invoice_number', 64)->nullable()->index();
                $table->date('invoiced_at')->nullable();

                $table->unsignedBigInteger('task_id')->nullable()->index();
                $table->string('external_ref', 64)->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Verwende einen kurzen Namen für den FK, um MySQLs 64-Zeichen-Limit einzuhalten
                $table->foreign('customer_project_id', 'pcp_bi_customer_project_fk')
                    ->references('id')
                    ->on('planner_customer_projects')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_customer_project_billing_items');
    }
};


