<?php


use App\Http\Controllers\AuthController;

Route::get('recaptch', [AuthController::class, 'recaptch']);
Route::post('recaptchaVerify', [AuthController::class, 'recaptchaVerify']);
