<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\BulkImportController;

Route::prefix('organizations/{organization}/contacts')
    ->middleware(['api', 'auth:api'])
    ->controller(ContactController::class)
    ->name('contacts.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{contact}', 'show')->name('show');
        Route::patch('/{contact}', 'update')->name('update');
        Route::delete('/{contact}', 'destroy')->name('destroy');
        Route::post('/import', 'bulkImport')->name('import');
    });

// Bulk import management routes
Route::prefix('organizations/{organization}/bulk-imports')
    ->middleware(['api', 'auth:api'])
    ->controller(BulkImportController::class)
    ->name('bulk-imports.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{importLogId}/status', 'status')->name('status');
        Route::post('/{importLogId}/cancel', 'cancel')->name('cancel');
        Route::post('/{importLogId}/retry', 'retry')->name('retry');
    });
