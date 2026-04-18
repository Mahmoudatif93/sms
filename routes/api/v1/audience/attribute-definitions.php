<?php

//Attribute Definitions

use App\Http\Controllers\AttributeDefinitionController;


// 🔹 Global Built-in Attribute Definitions (read-only for all)
Route::middleware(['api', 'auth:api'])
    ->prefix('attribute-definitions')
    ->group(function () {
        Route::get('/', [AttributeDefinitionController::class, 'index']);
    });

// 🔹 Org-specific Attribute Definitions
Route::middleware(['api', 'auth:api'])
    ->prefix('organizations/{organization}')
    ->group(function () {
        Route::get('attribute-definitions', [AttributeDefinitionController::class, 'indexForOrganization']);
        Route::post('attribute-definitions', [AttributeDefinitionController::class, 'store']);
    });
