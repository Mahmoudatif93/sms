<?php


use App\Http\Controllers\OrganizationWhatsappRateController;

Route::middleware(['api', 'auth:api'])
    ->prefix('organizations/{organization}/whatsapp-rates')
    ->controller(OrganizationWhatsappRateController::class)
    ->group(function () {
        Route::get('/', 'index'); // Get all rates
    });


Route::middleware(['api', 'auth:api'])
    ->prefix('organizations/{organization}/whatsapp-rates')
    ->controller(OrganizationWhatsappRateController::class)
    ->group(function () {
        Route::get('/', 'index');    // Get all rates
        Route::get('/{id}', 'show'); // Get a specific rate
    });
