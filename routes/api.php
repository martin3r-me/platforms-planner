<?php

use Illuminate\Support\Facades\Route;
use Platform\Planner\Http\Controllers\Api\TaskDatawarehouseController;

/**
 * Planner API Routes
 * 
 * Datawarehouse-Endpunkte für Tasks
 */
Route::get('/tasks/datawarehouse', [TaskDatawarehouseController::class, 'index']);

