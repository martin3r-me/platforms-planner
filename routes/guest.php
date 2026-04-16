<?php

use Illuminate\Support\Facades\Route;
use Platform\Planner\Livewire\PublicProject;

Route::get('/public/{token}', PublicProject::class)->name('planner.public.show');
