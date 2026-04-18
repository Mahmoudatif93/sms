<?php

use App\Http\Controllers\LiveChat\PostChatFormController;
use App\Http\Controllers\LiveChat\PreChatFormController;
use App\Http\Controllers\Api\V1\LiveChatWidgetController;

Route::middleware(['api', 'lang'])
    ->group(function () {
        Route::prefix('workspaces/{workspace}/livechat')
            ->group(function () {
                Route::put('pre-chat-forms/{id}', [PreChatFormController::class, 'update']);
                Route::put('post-chat-forms/{id}', [PostChatFormController::class, 'update']);
                Route::put('widget-settings/{id}', [LiveChatWidgetController::class, 'updateWidgetSettings']);
                Route::get('/previous-sessions', [LiveChatWidgetController::class, 'getPreviousConversations']);
                Route::post('/end-session', [LiveChatWidgetController::class, 'endConversation']);
            });
        Route::post('livechat/initialize', [LiveChatWidgetController::class, 'initializeChat']);
        Route::post('livechat/submit-pre-chat-form', [LiveChatWidgetController::class, 'submitPreChatForm']);
        Route::post('livechat/submit-post-chat-form', [LiveChatWidgetController::class, 'submitPostChatForm']);
        Route::get('livechat/chat-history', [LiveChatWidgetController::class, 'getChatHistory']);
        Route::post('livechat/send-message', [LiveChatWidgetController::class, 'sendMessage']);
        Route::post('livechat/send-reaction', [LiveChatWidgetController::class, 'sendReaction']);
        Route::post('livechat/mark-messages-deliverd', [LiveChatWidgetController::class, 'markMessagesAsDelivered']);
        Route::post('livechat/mark-messages-read', [LiveChatWidgetController::class, 'markMessagesAsRead']);
        Route::post('livechat/session-heartbeat', [LiveChatWidgetController::class, 'sessionHeartbeat']);
        Route::post('livechat/close', [LiveChatWidgetController::class, 'closeChat']);
        Route::get('livechat/notification.mp3', function () {
            return response()->file(public_path('notification.mp3'));
        });
    });
