<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ConnectorController;
use App\Http\Controllers\InboxAgentController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\Meta\BusinessManagerAccountController;
use App\Http\Controllers\OrganizationMembershipPlanController;
use App\Http\Controllers\SmsUsers\AnalyticsController;
use App\Http\Controllers\SmsUsers\OutboxController;
use App\Http\Controllers\SmsUsers\SmsController;
use App\Http\Controllers\TranslateController;
use App\Http\Controllers\UserInvitationController;
use App\Http\Controllers\Whatsapp\WhatsAppFlowController;
use App\Http\Controllers\Whatsapp\WhatsappMediaController;
use App\Http\Controllers\Whatsapp\WhatsappMessageController;
use App\Http\Controllers\WhatsappAnalyticsController;
use App\Http\Controllers\WhatsappBusinessProfileController;
use App\Http\Controllers\WhatsappPhoneNumbersController;
use Illuminate\Support\Facades\Route;

//require __DIR__ . '/api/v1/sms.php';
//require __DIR__ . '/api/v1/sms-plan.php';
//require __DIR__ . '/api/v1/livechat.php';


// v1.0
Route::prefix('v1.0')->group(function () {
    foreach (glob(__DIR__ . '/api/v1/**/*.php') as $routeFile) {
        require $routeFile;
    }
});
Route::prefix('v1.0')->group(function () {



    Route::middleware((['auth:api']))
        ->prefix('SmsUsers')
        ->group(function () {
            Route::post('sms/upload', [SmsController::class, 'upload_excel'])->name('sms.upload');
        });

    Route::middleware((['auth:api']))
        ->prefix('SmsUsers')
        ->group(function () {
            Route::get('outbox', [OutboxController::class, 'index'])->name('outbox.index');
        });


    /*
 * Whatsapp APIS
 */
    Route::middleware(['api'])
        ->controller(WhatsappMessageController::class)
        ->prefix('whatsapp')
        ->group(function () {
            Route::post('send-template-message', 'sendTemplateMessage')->name('send-template-message');
            Route::post('send-message', 'sendMessage')->name('whatsapp-send-message');
            Route::get('messages', 'getMessages')->name('messages');
            Route::get('/messages/{whatsappPhoneNumberId}', 'index'); // get Messages By Phone Number ID
            Route::post('/send-text-message', [WhatsappMessageController::class, 'sendTextMessage']);
            Route::post('/send-image-message', [WhatsappMessageController::class, 'sendImageMessage']);
            Route::post('/send-video-message', [WhatsappMessageController::class, 'sendVideoMessage']);
            Route::post('/send-audio-message', [WhatsappMessageController::class, 'sendAudioMessage']);
            Route::post('/send-location-message', [WhatsappMessageController::class, 'sendLocationMessage']);
        });


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

    Route::middleware(['api'])
        ->controller(TranslateController::class)
        ->prefix('ai')
        ->group(function () {
            Route::post('/translate', 'translate')->name('translate');
            Route::post('/improve-writing', 'fixTranslate')->name('translate.fix-transcription');
        });

    Route::middleware(['api'])
        ->controller(BusinessManagerAccountController::class)
        ->prefix('{channel}/business-manager-account')
        ->group(function () {
            Route::get('/', 'show');       // Route to get a single account by ID for a channel
            Route::post('/', 'store');     // Route to create a new account for a channel
            Route::put('/', 'update');     // Route to update an account by ID for a channel
            Route::delete('/', 'destroy'); // Route to delete an account by ID for a channel
        });

    Route::middleware(['api'])
        ->controller(WhatsappBusinessProfileController::class)
        ->prefix('{channel}/whatsapp-business-profile')
        ->group(function () {
            Route::get('/', 'getProfile');     // Route to get a single account by ID
            Route::post('/', 'store');         // Route to create a new account
            Route::put('/{id}', 'update');     // Route to update an existing account by ID
            Route::delete('/{id}', 'destroy'); // Route to delete an account by ID
        });

    Route::middleware(['api'])
        ->controller(WhatsappPhoneNumbersController::class)
        ->prefix('{channel}/phone-numbers')
        ->group(function () {
            Route::get('', 'getPhoneNumbers'); // Route to get a single account by ID
            Route::post('/', 'store');         // Route to create a new account
            Route::put('/{id}', 'update');     // Route to update an existing account by ID
            Route::delete('/{id}', 'destroy'); // Route to delete an account by ID
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

    ///////////////////////////////////////////////////////////////////////////////


    //});

    //Route::middleware(['api', 'auth:api'])
    //    ->controller(OnBoardingController::class)
    //    ->prefix('whatsapp')
    //    ->group(function () {
    //        Route::post('/onboard', 'onBoard')->name('whatsapp.onboard');
    //    });

    //Route::middleware(['api', 'check.active.membership'])
    //    ->controller(WhatsappChatController::class)
    //    ->prefix('{channel}')
    //    ->group(function () {
    //        Route::get('/chat-list', 'getChatList')->name('get-chat-list');
    //        Route::get('/chat-messages/{phone_number_id}', 'getChatMessages')->name('get-chat-messages');
    //        Route::post('/mark-messages-read/{phone_number_id}', 'markMessagesAsRead');
    //        Route::delete('/delete-message/{whatsappMessage}', 'deleteMessage')->name('delete-whatsapp-message');
    //    });

    //------------------------------------------------------------------------------------------------------------------------------//


    // Invite Users

    Route::middleware(['api', 'auth:api'])
        ->prefix('organizations/{organizationId}')
        ->group(function () {
            Route::post('/invite', [UserInvitationController::class, 'invite'])->name('organization.invite');
        });

    Route::middleware(['api'])
        ->prefix('organizations/invite')
        ->group(function () {
            Route::post('/{inviteToken}/accept', [UserInvitationController::class, 'acceptInvite'])->name('organization.invite.accept');
        });




    // Channels

    Route::middleware(['api', 'auth:api'])
        ->controller(ChannelController::class)
        ->group(function () {
            // Route::get('organizations/{organization}/channels', 'getChannelsByOrganization');
            // Route::get('workspaces/{workspace_id}/channels/available', 'getAvailableChannels');
            // Route::get('channels/whatsapp/setup-info', 'getWhatsappSetupInfo');
            // Route::get('channels/sms/setup-info', 'getSmsSetupInfo');
            // Route::post('workspaces/{workspace}/channels/install', 'installChannel');
            // Route::post('workspaces/{workspace}/channels/{channel}/connect', 'connectChannelToWorkspace');
            // Route::get('workspaces/{workspace}/channels', 'getChannels');
            // Route::get('workspaces/{workspace}/channels/senders', 'getSender');//*
            // Route::delete('workspaces/{workspace}/channels/{channel}', 'deleteChannel');
            // Route::get('/channels/{id}/sender', [ChannelController::class, 'getSender']);//*
            // Route::get('organizations/{organization}/channels/{channel}/pay', [ChannelController::class, 'activateChannelWithPayment']);

            // Route::get('workspaces/{workspace}/channels/{channel}', 'getChannel');

        });


    Route::middleware(['api', 'auth:api'])
        ->controller(ConnectorController::class)
        ->group(function () {
            Route::post('workspaces/{workspace_id}/connectors', 'createConnector');
        });

    Route::middleware(['auth.access'])
        ->controller(MessagesController::class)
        ->prefix('/workspaces/{workspace}/channels/{channel}')
        ->group(function () {
            Route::post('/messages', 'sendMessage');
            Route::post('/statistics', 'statisticsMessage');
        });

    /////////analytics////////////////////////////////////
    Route::middleware(['auth:api', 'active', 'lang'])
        ->prefix('analytics/')
        ->group(function () {
            Route::get('messages/count', [AnalyticsController::class, 'getMessageCount']);
            Route::get('messagesSent/count', [AnalyticsController::class, 'getSentMessageCount']);
            Route::get('messagesNotSent/count', [AnalyticsController::class, 'getNotSentMessageCount']);
            Route::get('points/consumed/count', [AnalyticsController::class, 'getConsumedPointsCount']);
            Route::get('senders/active/count', [AnalyticsController::class, 'getActiveSendersCount']);
            Route::get('senders/notActive/count', [AnalyticsController::class, 'getNotActiveSendersCount']);
            Route::get('contact/groups/count', [AnalyticsController::class, 'getContactGroupsCount']);
            Route::get('contacts/count', [AnalyticsController::class, 'getContactsCount']);
            Route::get('all-counts', [AnalyticsController::class, 'getAllCounts']);
            Route::get('users/last-logged-in', [AnalyticsController::class, 'getLastLoggedInAccounts']);
            Route::get('messages/sent-over-time', [AnalyticsController::class, 'getSentMessagesOverTime']);
            Route::get('points/consumed/over-time', [AnalyticsController::class, 'getConsumedPointsOverTime']);
            Route::get('messages/latest', [AnalyticsController::class, 'getLatestMessages']);
            Route::get('wallet/points-by-service', [AnalyticsController::class, 'getPointsByService']);
        });

    ////////////////////////////////////////////////////////
    ///
    ///
    Route::middleware(['api', 'auth:api'])
        ->prefix('{organization}/membership-plans')
        ->controller(OrganizationMembershipPlanController::class)
        ->group(function () {
            Route::get('/', 'index');    // Get all membership plans for the organization
            Route::get('/{id}', 'show'); // Get a specific membership plan
            Route::post('/{id}/activate', 'activate');
        });




    Route::middleware(['api'])
        ->prefix('workspaces/{workspace}')
        ->controller(InboxAgentController::class)
        ->group(function () {
            Route::get('/inbox-agents/me', 'getInboxAgent')
                ->name('workspace.inbox-agent.me');
            Route::post('/inbox-agents/{user}', 'update');

            Route::get('/inbox-agents', 'getInboxAgents')
                ->name('workspace.inbox-agents.all');
        });
});
