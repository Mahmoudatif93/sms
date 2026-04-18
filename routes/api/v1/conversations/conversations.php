<?php

use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\MetaConversationLogController;

/**
 * Conversation Routes - Clean Architecture
 *
 * Uses the new thin ConversationController at:
 * App\Http\Controllers\Api\V1\ConversationController
 *
 * Old controller backed up at:
 * App\Http\Controllers\ConversationController.backup
 */

Route::middleware(['auth.access'])
    ->controller(ConversationController::class)
    ->prefix('workspaces/{workspace}')
    ->group(function () {
        // List and view conversations
        Route::get('/conversations', 'index')->name('get-all-conversations');
        Route::get('/conversations/stats', 'stats')->name('refresh-conversation-count');
        Route::get('/conversations/{conversation}', 'show')->name('get-conversation');

        // Start a new conversation
        Route::post('/conversations/start', 'store')->name('start-new-conversation');

        // Send and manage messages
        Route::post('/conversations/{conversation}/messages', 'sendMessage')->name('send-message');
        Route::post('/conversations/{conversation}/mark-as-delivered', 'markAsDelivered')->name('mark-as-delivered');
        Route::post('/conversations/{conversation}/mark-as-read', 'markAsRead')->name('mark-as-read');

        // Manage conversation state
        Route::post('/conversations/{conversation}/close', 'close')->name('close-conversation');
        Route::post('/conversations/{conversation}/reopen', 'reopen')->name('send-conversation');

        // Notes
        Route::post('/conversations/{conversation}/note', 'addNote')->name('add-conversation-note');
        Route::get('/conversations/{conversation}/notes', 'getNotes')->name('get-conversation-notes');

        // Switch workspace
        Route::post('/conversations/{conversation}/switch-workspace', 'switchWorkspace')->name('switch-conversation-workspace');

        // AI Features
        Route::post('/conversations/{conversation}/ai/suggest-reply', 'aiSuggestReply')->name('conversation.ai.suggest-reply');
        Route::post('/conversations/{conversation}/ai/improve-writing', 'aiImproveWriting')->name('conversation.ai.improve-writing');
        Route::post('/conversations/{conversation}/ai/summarize', 'aiSummarize')->name('conversation.ai.summarize');
    });


// Agent assignment (different middleware)
Route::middleware(['auth:api', 'check.active.membership'])
    ->controller(ConversationController::class)
    ->prefix('workspaces/{workspace}')
    ->group(function () {
        Route::post('/conversations/{conversation}/assign-agent/{user}', 'assignAgent')->name('conversation.assign-agent');
        Route::delete('/conversations/{conversation}/remove-agent/{user}', 'removeAgent')->name('conversation.remove-agent');
    });


// Meta logs
Route::controller(MetaConversationLogController::class)
    ->prefix('workspaces/{workspace}')
    ->group(function () {
        Route::get('/conversations/{conversation}/meta-logs', 'index')->name('conversation.meta-logs');
    });
