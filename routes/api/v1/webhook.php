<?php

use App\Http\Controllers\FlowWebhookController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MetaWebhookController;
use App\Http\Controllers\WebhookController;




Route::post('/webhook', function (\Illuminate\Http\Request $request) {
    Log::info(json_encode($request->all()));
});
///////////////////////// UserWebhook/////////////////////////
Route::middleware(['auth:api', 'active', 'lang'])
    ->prefix('organizations/{organization}/webhooks')
    ->group(function () {
        Route::get('/services', [WebhookController::class, 'services']);
        Route::get('/events', [WebhookController::class, 'events']);
        Route::resource('/', WebhookController::class);
    });


Route::get('/meta/webhook', [MetaWebhookController::class, 'verifyWebhook'])->name('meta-webhook.verify');
Route::post('/meta/webhook', [MetaWebhookController::class, 'handleWebhook'])->name('meta-webhook.handle');

Route::post('/flows/{flowID}/webhook', [FlowWebhookController::class, 'handle']);
