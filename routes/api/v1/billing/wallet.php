<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WalletController;

Route::prefix('organizations/{organization}/wallets')
    ->middleware(['api'])
    ->controller(WalletController::class)
    ->name('wallet.')
    ->group(function () {
        Route::get('/transactions', 'organizationWalletTransaction')->name('transaction-all');
        Route::get('/transactions/statistics', 'organizationWalletTransactionStatistics')->name('transaction-statistics');
        Route::post('/transactions/export', 'exportWalletTransactions')->name('transaction-export');
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('/{wallet}', 'update')->name('update');
        Route::get('/{wallet}', 'show')->name('show');

        // Transactions
        Route::get('/{wallet}/transactions', 'transaction')->name('transaction');

        // Assignments
        Route::post('/{wallet}/assignments', 'assignment')->name('assignments.store');
        Route::get('/{wallet}/assignments', 'getAssignments')->name('assignments');
        Route::delete('/{wallet}/assignments', 'removeAssignment')->name('assignments.remove');

        // Transfers
        Route::post('/transfer', 'transfer')->name('transfer');
    });

