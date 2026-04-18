<?php

use App\Http\Controllers\Whatsapp\WorkflowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workflow Routes
|--------------------------------------------------------------------------
|
| Routes for managing unified workflows.
| Supports template-based and interactive message workflows.
|
| Component Types:
| - template: Triggered on template message status changes (sent, delivered, read)
| - interactive_message: Triggered when user clicks a button or list item
|
*/

Route::prefix('workspaces/{workspace}/workflows')
    ->middleware(['api', 'auth:api'])
    ->controller(WorkflowController::class)
    ->name('workflows.')
    ->group(function () {
        // Get available action types and component types
        Route::get('/action-types', 'getActionTypes')->name('action-types');

        // CRUD operations
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{flowId}', 'show')->name('show');
        Route::put('/{workflow}', 'update')->name('update');
        Route::delete('/{flowId}', 'destroy')->name('destroy');
    });

