<?php


use App\Http\Controllers\OrganizationWhatsappExtraController;

Route::middleware(['api', 'auth:api'])
    ->prefix('organizations/{organization}/whatsapp-extras')
    ->controller(OrganizationWhatsappExtraController::class)
    ->group(function () {
        Route::get('/', 'index'); // Get all extras for a specific organization
        Route::post('/{id}/activate', 'activate');
    });
