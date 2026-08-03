<?php

use Illuminate\Support\Facades\Route;
use Platform\Planner\Http\Controllers\Api\TaskDatawarehouseController;
use Platform\Planner\Http\Controllers\Api\ProjectDatawarehouseController;
use Platform\Planner\Http\Controllers\Api\ExportController;
use Platform\Planner\Http\Controllers\Api\PlannerAgentController;

/**
 * Planner API Routes
 *
 * Datawarehouse-Endpunkte für Tasks und Projects
 */
Route::get('/tasks/datawarehouse', [TaskDatawarehouseController::class, 'index']);
Route::get('/tasks/datawarehouse/health', [TaskDatawarehouseController::class, 'health']);
Route::get('/projects/datawarehouse', [ProjectDatawarehouseController::class, 'index']);
Route::get('/projects/datawarehouse/health', [ProjectDatawarehouseController::class, 'health']);

/**
 * Export-Endpunkte für Aufgaben und Projekte
 *
 * Unterstützt JSON und PDF Export (erweiterbar für CSV, Excel etc.)
 * Query-Parameter: format=json|pdf (Standard: json)
 */
Route::get('/export/formats', [ExportController::class, 'formats'])->name('planner.api.export.formats');
Route::get('/export/tasks/{task}', [ExportController::class, 'exportTask'])->name('planner.api.export.task');
Route::get('/export/projects/{project}', [ExportController::class, 'exportProject'])->name('planner.api.export.project');

/**
 * Agent-API für Backoffice-Worker. Der Bearer-Token = der User; der Worker holt
 * seine exklusiv zugewiesenen Tasks (user_in_charge_id) und meldet sie zurück.
 * Auth erbt die api.auth-Middleware der apiGroup.
 */
Route::prefix('agent')->group(function () {
    Route::get('/pipeline', [PlannerAgentController::class, 'pipeline'])->name('planner.api.agent.pipeline');
    Route::post('/next-task', [PlannerAgentController::class, 'nextTask'])->name('planner.api.agent.next-task');
    Route::post('/next-untriaged-task', [PlannerAgentController::class, 'nextUntriagedTask'])->name('planner.api.agent.next-untriaged-task');
    Route::post('/assistant-project', [PlannerAgentController::class, 'assistantProject'])->name('planner.api.agent.assistant-project');
    Route::post('/tasks/{id}/triage', [PlannerAgentController::class, 'triageTask'])->name('planner.api.agent.triage');
    Route::post('/tasks', [PlannerAgentController::class, 'createTask'])->name('planner.api.agent.create-task');
    Route::post('/tasks/{id}/complete', [PlannerAgentController::class, 'complete'])->name('planner.api.agent.complete');
    Route::post('/tasks/{id}/log-time', [PlannerAgentController::class, 'logTime'])->name('planner.api.agent.log-time');
    Route::post('/tasks/{id}/defer', [PlannerAgentController::class, 'defer'])->name('planner.api.agent.defer');
    Route::post('/tasks/{id}/ask', [PlannerAgentController::class, 'ask'])->name('planner.api.agent.ask');
    Route::post('/tasks/{id}/fail', [PlannerAgentController::class, 'fail'])->name('planner.api.agent.fail');
    Route::post('/tasks/{id}/unlock', [PlannerAgentController::class, 'unlock'])->name('planner.api.agent.unlock');
});

