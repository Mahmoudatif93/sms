<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\WhatsappMessageReportController;



//Campaign
Route::middleware(['api', 'auth:api', 'check.active.membership'])
    ->prefix('workspaces/{workspace}/channels/{channel}')
    ->group(function () {
        Route::get('campaigns', [CampaignController::class, 'index']);
        Route::post('campaigns/send-test-message', [CampaignController::class, 'sendTestMessage']);
        Route::post('campaigns', [CampaignController::class, 'store']);
        Route::get('campaigns/{campaign}', [CampaignController::class, 'show']);
        Route::patch('campaigns/{campaign}', [CampaignController::class, 'update']);
        Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy']);

        Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send']);
        Route::get('campaigns/{campaign}/report', [CampaignController::class, 'getCampaignReport']);

        // New routes for pausing and canceling a campaign
        Route::patch('campaigns/{campaign}/pause', [CampaignController::class, 'pause']);
        Route::patch('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel']);
        Route::patch('campaigns/{campaign}/activate', [CampaignController::class, 'activate']);
        Route::get('campaigns/{campaign}/logs/{log}/attempts', [CampaignController::class, 'getCampaignLogAttempts']);
        Route::post('campaigns/{campaign}/retry', [CampaignController::class, 'retryFailedOrUnsent']);

    });

Route::middleware(['api', 'auth:api', 'check.active.membership'])
    ->prefix('workspaces/{workspace}')
    ->group(function () {
        Route::post('campaigns/resend-message-faild', [CampaignController::class, 'resendFailedMessages']);
    });

Route::middleware(['api'])
    ->controller(WhatsappMessageReportController::class)
    ->prefix('workspaces/{workspace}')
    ->group(function () {
        // Route to fetch WhatsApp messages for a workspace, paginated
        Route::get('messages/report', 'getMessages')->name('workspace.messages.report');
        Route::get('campaigns/report', 'getCampaigns')->name('workspace.campaigns.report');
        Route::get('campaigns/list', 'CamaignLists')->name('workspace.campaigns.list');
        Route::get('analytics/report', 'getWhatsappAnalytics')->name('workspace.whatsapp.analytics');
        Route::get('campaigns/failed-messages', 'getFailedTemplateMessages')->name('workspace.campaigns.failed-messages');

    });
