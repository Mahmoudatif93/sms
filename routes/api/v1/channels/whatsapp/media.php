<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Whatsapp\WhatsappMediaController;


Route::middleware(['api'])
    ->controller(WhatsappMediaController::class)
    ->prefix('whatsapp/media')
    ->group(function () {
        Route::post('/{channel}/upload', [WhatsAppMediaController::class, 'uploadMedia'])->name('whatsapp.media.upload');
        Route::post('/resumable-upload', [WhatsAppMediaController::class, 'uploadResumableMedia'])->name('whatsapp.media.resumable-upload');
        Route::get('/{mediaId}', [WhatsappMediaController::class, 'getMediaUrl']);
        Route::get('/download/{mediaId}', [WhatsappMediaController::class, 'downloadMedia']);
        Route::delete('/{mediaId}', [WhatsappMediaController::class, 'deleteMedia']);
        Route::post('/{whatsappPhoneNumber}/upload-cloud', 'uploadToCloudApi')->name('whatsapp.media.upload-cloud');
        Route::post('/{channel}/upload-oss', 'uploadToOSS')->name('whatsapp.media.upload-oss');

    });

Route::prefix('media')
    ->controller(WhatsappMediaController::class)
    ->group(function () {
        Route::post('{channel}/upload-oss', 'uploadToOSS')->name('media.upload-oss');
    });

Route::get(
    '/organizations/{organization}/media/whatsapp',
    [WhatsappMediaController::class, 'getOrganizationMediaGallery']
)->name('organization.media.whatsapp');


