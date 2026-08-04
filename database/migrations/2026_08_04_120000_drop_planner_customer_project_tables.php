<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Entfernt die verwaisten "Kundenprojekt"-Tabellen. Das Feature wurde
 * komplett aufgegeben und wird künftig anders gelöst. Billing-Felder leben
 * bereits direkt auf planner_projects, Entity-Verknüpfungen laufen über
 * OrganizationEntityLink.
 *
 * Reihenfolge: Child-Tabellen (FK auf planner_customer_projects) zuerst.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('planner_customer_project_parties');
        Schema::dropIfExists('planner_customer_project_billing_items');
        Schema::dropIfExists('planner_customer_projects');
    }

    public function down(): void
    {
        // Bewusst nicht wiederherstellbar — das Kundenprojekt-Feature wurde entfernt.
    }
};
