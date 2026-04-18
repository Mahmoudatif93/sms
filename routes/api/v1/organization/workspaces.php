<?php

use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('organizations/{organization}/workspaces')
    ->middleware(['api', 'auth:api'])
    ->controller(WorkspaceController::class)
    ->name('workspaces.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{workspace}', 'show')->name('show');
        Route::patch('/{workspace}', 'update')->name('update');
        Route::delete('/{workspace}', 'destroy')->name('destroy');

        // Additional workspace-specific route
        Route::get('/{workspace}/agents', 'assignAgent')->name('agents');
    });
