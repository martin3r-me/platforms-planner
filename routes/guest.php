<?php

use Illuminate\Support\Facades\Route;
use Platform\Planner\Livewire\PublicProject;
use Platform\Planner\Livewire\PublicTask;

Route::get('/public/{token}', PublicProject::class)->name('planner.public.show');
Route::get('/public/{token}/task/{task}', PublicTask::class)->name('planner.public.task');
