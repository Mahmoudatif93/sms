<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketEmailConfigController;
use App\Http\Controllers\TicketEmailController;
use App\Http\Controllers\TicketEntityController;
use App\Http\Controllers\TicketFormsController;
use App\Http\Controllers\TicketIframeController;


Route::middleware(['auth:api', 'active', 'lang'])
    ->prefix('organizations/')
    ->group(function () {
        Route::resource('{organization}/tickets', TicketController::class);
        Route::post('{organization}/ticket/replay/{id}', [TicketController::class, 'replay'])->name('replay');
    });

Route::middleware(['auth:api'])
    ->prefix('workspaces/{workspace}')
    ->group(function (): void {
        Route::get('/tickets', [TicketEntityController::class, 'index']);
        Route::put('/tickets/{id}', [TicketEntityController::class, 'update']);
        Route::post('/tickets/{ticket}/assign-agent/{user}', [TicketEntityController::class, 'assignAgent'])->name('tickets.assign-agent');
        Route::delete('/tickets/{tickets}/remove-agent/{user}', [TicketEntityController::class, 'removeAgent'])->name('ticket.conversation.remove-agent');
        // Ticket messages
        Route::post('/tickets/{id}/messages', [TicketEntityController::class, 'addMessage']);
        Route::get('/tickets/{id}/timeline', [TicketEntityController::class, 'getTimeline']);
        // Create ticket from conversation
        Route::post('/tickets/from-conversation/{conversationId}', [TicketEntityController::class, 'createFromConversation']);
    });
Route::post('/webhook/email/incoming', [TicketEmailController::class, 'processIncomingEmail']);
// Email configuration for tickets
Route::middleware(['auth:api'])
    ->prefix('workspaces/{workspace}/ticket-email-config')->group(function () {
        Route::get('/', [TicketEmailConfigController::class, 'index']);
        Route::post('/', [TicketEmailConfigController::class, 'store']);
        Route::get('/{id}', [TicketEmailConfigController::class, 'show']);
        Route::put('/{id}', [TicketEmailConfigController::class, 'update']);
        Route::delete('/{id}', [TicketEmailConfigController::class, 'destroy']);
    });

Route::middleware(['allowiframe'])->prefix('ticket-iframe')->group(function () {
    Route::get('/form/{token}', [TicketIframeController::class, 'showIframeForm'])->name('ticket.iframe.form');
    Route::get('/script/{token}', [TicketIframeController::class, 'serveEmbedScript'])->name('ticket.iframe.script');
    Route::post('/submit', [TicketIframeController::class, 'createTicket'])->name('ticket.iframe.submit');
});
