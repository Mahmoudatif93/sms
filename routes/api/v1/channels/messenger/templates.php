<?php

use App\Http\Controllers\Api\V1\Messenger\MessengerTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('messenger/pages/{channel}/templates')
    ->middleware(['api', 'auth:api'])
    ->controller(MessengerTemplateController::class)
    ->name('messenger.templates.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{templateId}', 'show')->name('show');
        Route::put('/{templateId}', 'update')->name('update');
        Route::post('/{templateId}', 'update')->name('update.post'); // For multipart/form-data with files
        Route::delete('/{templateId}', 'destroy')->name('destroy');
        Route::post('/{templateId}/toggle-active', 'toggleActive')->name('toggle-active');
        Route::post('/{templateId}/duplicate', 'duplicate')->name('duplicate');
        Route::get('/{templateId}/preview', 'preview')->name('preview');
    });
