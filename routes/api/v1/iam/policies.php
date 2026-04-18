<?php

use App\Http\Controllers\IAMPoliciesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:api'])
    ->controller(IAMPoliciesController::class)
    ->name('iam-policies.')
    ->group(function () {
        // Scoped under organization
        Route::prefix('organizations/{organization}/iam-policies')->group(function () {
            Route::post('/', 'create')->name('create');
            Route::get('/', 'getAllForOrganization')->name('getAllForOrganization');
        });

        // Global IAM policy management
        Route::prefix('iam-policies')->group(function () {
            Route::get('/', 'getAll')->name('getAll');
            Route::get('/{iamPolicy}', 'getById')->name('getById');
            Route::patch('/{iamPolicy}', 'update')->name('update');
            Route::delete('/{iamPolicy}', 'delete')->name('delete');
        });
    });

