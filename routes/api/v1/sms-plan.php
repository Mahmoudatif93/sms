<?php
use App\Http\Controllers\PlanController;

Route::middleware(['auth:api', 'active', 'lang', 'auth.access'])
    ->prefix('organizations/{organization}/plans')
    ->name('organization.plans')
    ->controller(PlanController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/gatway_checkout ', 'checkoutPlanGatway')->name('gateway_checkout');
        Route::get('/payment_method', 'PaymentMethod')->name('payment_method');
        Route::post('/charge_request_bank', 'ChargeRequestBank')->name('charge_request_bank');
        Route::get('/export', 'exportPlan')->name('export');
    });

Route::middleware(['auth:api', 'active', 'lang'])
    ->prefix('organizations/{organization}/plans')
    ->controller(PlanController::class)
    ->group(function () {
        Route::get('/urway_callback', 'urwayCallback')->name('paln.urway.callback');
        Route::get('/bank-info', 'bankInfo')->name('bank_info');

    });

// Separate workspace-specific route with different prefix
Route::middleware(['auth:api', 'active', 'lang', 'auth.access'])
    ->prefix('workspaces/{workspace}/plans')
    ->name('workspaces.plans.')
    ->controller(PlanController::class)
    ->group(function () {
        Route::get('/can-use', 'checkPlanUsage')->name('can-use');
    });
