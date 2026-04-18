<?php

use App\Http\Controllers\AuthController;

// @todo lang is not necessary here, should be sent as headers
// @todo why are there 2 routes for logout ( 1 Post and 1 Get )

Route::controller(AuthController::class)
    ->prefix('auth')
    ->group(function () {
        Route::post('register', 'register');
        Route::post('login', 'login');
        Route::post('logout', 'logout');
        Route::get('me', 'me')->middleware('active');
        Route::post('verify-otp', 'verifyOTP');
        Route::post('resend-otp', 'resendOTP');
        Route::get('logout', 'logout');
    });

Route::middleware('api', 'lang')
    ->controller(AuthController::class)
    ->group(function () {
        Route::get('countries', 'countries');
    });


    Route::get('/clearfront-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    return response()->json(['message' => 'Cache cleared successfully']);
});
