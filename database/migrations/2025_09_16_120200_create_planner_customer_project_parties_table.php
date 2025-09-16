<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('planner_customer_project_parties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_project_id')->index();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();

            $table->string('party_type', 20)->default('external');
            $table->string('role', 50)->default('contact');
            $table->boolean('is_primary')->default(false);

            $table->string('email', 190)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('notes', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_project_id', 'pcp_party_customer_project_fk')
                ->references('id')
                ->on('planner_customer_projects')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_customer_project_parties');
    }
};


