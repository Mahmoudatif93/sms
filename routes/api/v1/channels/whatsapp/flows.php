<?php

use App\Http\Controllers\Whatsapp\WhatsAppFlowController;

Route::middleware(['api'])
    ->controller(WhatsAppFlowController::class)
    ->prefix('{channel}/flows') // Prefix includes business account
    ->group(function () {
        Route::get('', [WhatsAppFlowController::class, 'getAllFlows'])->name('whatsapp.flows');
        Route::post('send', [WhatsAppFlowController::class, 'sendFlow'])->name('whatsapp.flow.send');
        Route::get('{flowId}', [WhatsAppFlowController::class, 'getFlowDetails'])->name('whatsapp.flow.details');
        Route::post('', [WhatsAppFlowController::class, 'createFlow'])->name('whatsapp.flow.create');
        Route::post('upload-flow-json', 'uploadFlowJson')->name('whatsapp.flow.json.upload');
        Route::post('{flowId}/publish', [WhatsAppFlowController::class, 'publishFlow'])->name('whatsapp.flow.publish');
        Route::post('{flowId}/deprecate', [WhatsAppFlowController::class, 'deprecate'])->name('whatsapp.flow.deprecate'); // Delete route includes business account
        Route::post('{flowId}', [WhatsAppFlowController::class, 'updateFlow'])->name('whatsapp.flow.update');
        Route::delete('{flowId}', [WhatsAppFlowController::class, 'destroy'])->name('whatsapp.flow.delete');
    });
