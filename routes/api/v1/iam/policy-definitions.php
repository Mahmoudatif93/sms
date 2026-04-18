<?php

// IAMPolicyDefinitions
use App\Http\Controllers\IAMPolicyDefinitionController;

Route::middleware(['api', 'auth:api'])
    ->prefix('iam-policy-definitions')
    ->controller(IAMPolicyDefinitionController::class)
    ->group(function () {
        Route::get('/', 'index')->name('iam-policy-definitions.index'); // List all definitions
    });
