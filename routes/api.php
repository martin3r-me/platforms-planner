<?php

use Illuminate\Support\Facades\Route;
use Platform\Planner\Http\Controllers\Api\TaskDatawarehouseController;
use Platform\Planner\Http\Controllers\Api\ProjectDatawarehouseController;
use Platform\Planner\Http\Controllers\Api\ExportController;

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

