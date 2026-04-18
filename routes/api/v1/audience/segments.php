<?php

use App\Http\Controllers\SegmentController;

Route::middleware(['api', 'auth:api'])
    ->prefix('organizations/{organization}/segments')
    ->controller(SegmentController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{segment}', 'show')->name('show');
        Route::put('/{segment}', 'update')->name('update');
        Route::delete('/{segment}', 'destroy')->name('destroy');
    });
