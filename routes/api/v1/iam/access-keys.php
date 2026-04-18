<?php

//Access Keys

use App\Http\Controllers\AccessKeysController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:api'])
    ->prefix('organizations/{organizationId}/access-keys')
    ->controller(AccessKeysController::class)
    ->name('access-keys.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{accessKeyId}', 'show')->name('show');
        Route::patch('/{accessKeyId}', 'update')->name('update'); // changed POST → PATCH
        Route::delete('/{accessKeyId}', 'destroy')->name('destroy');
    });

