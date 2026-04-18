<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::middleware(['api'])
    ->controller(PaymentController::class)
    ->name('payment.')
    ->group(function () {
        // Workspace-level payment info
        Route::get('workspaces/{workspace}/payment/total-amount', 'totalAmount')->name('totalAmount');

        // Wallet checkout flow
        Route::post('organizations/{organization}/wallets/{wallet}/payment/checkout', 'processCheckoutRequest')->name('checkout');

        // Callback from payment provider
        Route::get('payment/callback', 'urwayCallback')->name('callback');

        // Organization-wide payments list
        Route::get('organizations/{organization}/payments', 'organizationPayments')->name('organization.list');
    });
