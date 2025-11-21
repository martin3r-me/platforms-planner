<?php

use Illuminate\Support\Facades\Route;
use Platform\Planner\Http\Controllers\Api\TaskDatawarehouseController;
use Platform\Planner\Http\Controllers\Api\ProjectDatawarehouseController;

/**
 * Planner API Routes
 * 
 * Datawarehouse-Endpunkte für Tasks und Projects
 */
Route::get('/tasks/datawarehouse', [TaskDatawarehouseController::class, 'index']);
Route::get('/projects/datawarehouse', [ProjectDatawarehouseController::class, 'index']);

