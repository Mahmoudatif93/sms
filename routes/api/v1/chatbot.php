<?php

use App\Http\Controllers\Api\V1\ChatbotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Chatbot API Routes
|--------------------------------------------------------------------------
|
| Routes for managing chatbot settings and knowledge base per channel.
|
*/

Route::prefix('workspaces/{workspace}/channels/{channel}/chatbot')->group(function () {

    // Settings
    Route::get('settings', [ChatbotController::class, 'getSettings']);
    Route::post('settings', [ChatbotController::class, 'updateSettings']);
    Route::post('toggle', [ChatbotController::class, 'toggleBot']);

    // Knowledge Base CRUD
    Route::get('knowledge', [ChatbotController::class, 'listKnowledge']);
    Route::post('knowledge', [ChatbotController::class, 'storeKnowledge']);
    Route::get('knowledge/{item}', [ChatbotController::class, 'showKnowledge']);
    Route::put('knowledge/{item}', [ChatbotController::class, 'updateKnowledge']);
    Route::delete('knowledge/{item}', [ChatbotController::class, 'deleteKnowledge']);

    // Import/Export
    Route::post('knowledge/import', [ChatbotController::class, 'importJson']);
    Route::get('knowledge/export', [ChatbotController::class, 'exportKnowledge']);

    // Statistics
    Route::get('stats', [ChatbotController::class, 'getStats']);
});
