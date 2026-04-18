<?php

use App\Http\Controllers\Whatsapp\WhatsAppTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('channels/{channel}/message-templates')
    ->middleware(['api'])
    ->controller(WhatsAppTemplateController::class)
    ->name('whatsapp.templates.')
    ->group(function () {
        Route::get('/', 'getMessageTemplates')->name('index');
        Route::post('/', 'create')->name('create');
        Route::get('/{id}', 'getMessageTemplateById')->name('show');
        Route::post('/{whatsappMessageTemplate}', 'update')->name('update');
        Route::delete('/{whatsappMessageTemplate}', 'delete')->name('delete');
    });

// Additional route (not tied to a specific channel)
Route::get('/numbers', [WhatsAppTemplateController::class, 'getWhatsAppPhoneNumbers'])
    ->middleware(['api'])
    ->name('whatsapp.templates.numbers');
