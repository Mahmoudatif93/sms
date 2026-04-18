<?php



use App\Http\Controllers\CountriesController;

Route::middleware(['api'])
    ->prefix('world-countries')
    ->group(function () {
        Route::get('/', [CountriesController::class, 'index']);
    });

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/world-countries')
    ->group(function () {
        Route::get('/', [CountriesController::class, 'index']);
    });
