<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PasswordResetController;

Route::prefix('password')
    ->middleware(['api']) // Add any auth or throttling middleware if needed
    ->controller(PasswordResetController::class)
    ->name('password.')
    ->group(function () {
        Route::post('/email', 'sendResetLinkEmail')->name('email');
        Route::get('/reset', 'resetPassword')->name('reset');
        Route::get('/update', 'update_password')->name('update');
        Route::get('/change', 'changepassword')->name('change');
    });
