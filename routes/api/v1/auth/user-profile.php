<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SmsUsers\UserProfileController;
use App\Http\Controllers\TagController;





///////////////////////// UserProfile/////////////////////////
Route::middleware(['auth:api', 'active', 'lang'])
    ->prefix('SmsUsers')
    ->group(function () {
        Route::resource('UserProfile', UserProfileController::class);
        Route::get('/refrsh-key', [UserProfileController::class, 'refrshKey'])->name('refrsh_key');
        Route::post('/notification-save', [UserProfileController::class, 'notificationSave'])->name('notification_save');
    });

///////////////////////// UserTag/////////////////////////
Route::middleware(['auth:api', 'active', 'lang'])
    ->prefix('organizations')
    ->group(function () {
        Route::get('{organization}/tags', [TagController::class, 'index']);
    });
