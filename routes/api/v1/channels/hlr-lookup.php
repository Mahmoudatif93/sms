<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhitelistIpController;
use App\Http\Controllers\SmsUsers\NumberLookupController;




///////////////////////// Whitelistip/////////////////////////
Route::middleware(['auth:api', 'active', 'lang'])
    ->prefix('organizations')
    ->group(function () {
        Route::delete('{organization}/whitelist-ip/bulk-delete', [WhitelistIpController::class, 'deleteSelected']);
        Route::resource('{organization}/whitelist-ip', WhitelistIpController::class);
    });

///////////////////////// Number Lookup/////////////////////////
Route::middleware(['auth:api', 'active', 'lang'])
    ->prefix('SmsUsers')
    ->group(function () {
        Route::get('/lookuphis', [NumberLookupController::class, 'index'])->name('/lookuphis');
        Route::get('/lookup', [NumberLookupController::class, 'lookup'])->name('/lookup');
        Route::post('/lookup-from-excel', [NumberLookupController::class, 'lookupFromExcel'])->name('/lookup-from-excel');
    });
