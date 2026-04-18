<?php

//IAMRoles

use App\Http\Controllers\IAMRolesController;

Route::middleware(['api', 'auth:api'])
    ->prefix('organizations/{organizationId}/iam-roles')
    ->controller(IAMRolesController::class)
    ->name('iam-roles.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{roleId}', 'show')->name('show');
        Route::patch('/{roleId}', 'update')->name('update');
        Route::delete('/{roleId}', 'destroy')->name('destroy');
    });


Route::middleware(['api', 'auth:api'])
    ->prefix('iam-roles')
    ->controller(IAMRolesController::class)
    ->name('iam-roles.')
    ->group(function () {
        Route::get('/', 'getAllRoles')->name('get-all-roles');
    });
