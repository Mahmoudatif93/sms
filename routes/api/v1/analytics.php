<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SmsUsers\AnalyticsController;
use App\Http\Controllers\WhatsappAnalyticsController;

/////////analytics////////////////////////////////////
Route::middleware(['auth:api', 'active', 'lang'])
    ->prefix('analytics/')
    ->group(function () {
        Route::get('messages/count', [AnalyticsController::class, 'getMessageCount'])->name('analytics.messages.count');
        Route::get('messagesSent/count', [AnalyticsController::class, 'getSentMessageCount'])->name('analytics.messages.sent.count');
        Route::get('messagesNotSent/count', [AnalyticsController::class, 'getNotSentMessageCount'])->name('analytics.messages.not_sent.count');
        Route::get('points/consumed/count', [AnalyticsController::class, 'getConsumedPointsCount'])->name('analytics.points.consumed.count');
        Route::get('senders/active/count', [AnalyticsController::class, 'getActiveSendersCount'])->name('analytics.senders.active.count');
        Route::get('senders/notActive/count', [AnalyticsController::class, 'getNotActiveSendersCount'])->name('analytics.senders.not_active.count');
        Route::get('contact/groups/count', [AnalyticsController::class, 'getContactGroupsCount'])->name('analytics.contact.groups.count');
        Route::get('contacts/count', [AnalyticsController::class, 'getContactsCount'])->name('analytics.contacts.count');
        Route::get('all-counts', [AnalyticsController::class, 'getAllCounts'])->name('analytics.all.counts');
        Route::get('users/last-logged-in', [AnalyticsController::class, 'getLastLoggedInAccounts'])->name('analytics.users.last_logged_in');
        Route::get('messages/sent-over-time', [AnalyticsController::class, 'getSentMessagesOverTime'])->name('analytics.messages.sent_over_time');
        Route::get('points/consumed/over-time', [AnalyticsController::class, 'getConsumedPointsOverTime'])->name('analytics.points.consumed.over_time');
        Route::get('messages/latest', [AnalyticsController::class, 'getLatestMessages'])->name('analytics.messages.latest');
        Route::get('wallet/points-by-service', [AnalyticsController::class, 'getPointsByService'])->name('analytics.wallet.points_by_service');
    });


Route::middleware(['api'])
    ->controller(WhatsappAnalyticsController::class)
    ->prefix('{channel}/analytics')
    ->group(function () {
        Route::get('/conversations', 'getConversationAnalytics');                       // Route to get a single account by ID
        Route::get('/conversations-data-points', 'getConversationAnalyticsDataPoints'); // Route to get a single account by ID
        Route::get('/messages-data-points', 'getMessagesAnalyticsDataPoints');
        Route::get('/templates/{whatsapp_business_account_id}', 'getTemplateAnalytics');
    });
