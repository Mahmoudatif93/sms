<?php

use App\Http\Controllers\ConnectorController;
use App\Http\Controllers\DashboardNotificationController;
use App\Http\Controllers\InboxAgentController;
use App\Http\Controllers\LanguagesController;
use App\Http\Controllers\Meta\BusinessManagerAccountController;
use App\Http\Controllers\MetaConversationLogController;
use App\Http\Controllers\OrganizationMembershipPlanController;
use App\Http\Controllers\OrganizationTranslationSettingsController;
use App\Http\Controllers\RequiredActionController;
use App\Http\Controllers\TranslateController;
use App\Http\Controllers\UserInvitationController;
use App\Http\Controllers\Whatsapp\WhatsAppFlowController;
use App\Http\Controllers\Whatsapp\WhatsappMessageController;
use App\Http\Controllers\WhatsappBusinessProfileController;
use App\Http\Controllers\WhatsappPhoneNumbersController;
use App\Http\Controllers\KedaController;
use Illuminate\Support\Facades\Route;

//require __DIR__ . '/api/v1/sms.php';
//require __DIR__ . '/api/v1/sms-plan.php';
//require __DIR__ . '/api/v1/livechat.php';


// v1.0
//Route::prefix('v1.0')->group(function () {
//  foreach (glob(__DIR__ . '/api/v1/**/*.php') as $routeFile) {
/*    require $routeFile;
    }
});*/
 
Route::prefix('v1.0')->group(function () {
      Route::get('keda', [KedaController::class, 'index']);
       Route::get('keda/send', [KedaController::class, 'send_messanger_sms']);
    // Include single-level files in /api/v1
    foreach (glob(__DIR__ . '/api/v1/*.php') as $routeFile) {
        require $routeFile;
    }

    // Include files in all subdirectories of /api/v1
    foreach (glob(__DIR__ . '/api/v1/**/*.php') as $routeFile) {
        require $routeFile;
    }

    // Include files in all subdirectories of /api/v1
    foreach (glob(__DIR__ . '/api/v1/***/**/*.php') as $routeFile) {
        require $routeFile;
    }
});


Route::prefix('v1.0')->group(function () {
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
    Route::middleware(['api', 'auth:api','lang'])
        ->prefix('organizations/{organizationId}')
        ->group(function () {
            Route::post('/invite', [UserInvitationController::class, 'invite'])->name('organization.invite');
        });
    Route::middleware(['api'])
        ->prefix('organizations/invite')
        ->group(function () {
            Route::post('/{inviteToken}/accept', [UserInvitationController::class, 'acceptInvite'])->name('organization.invite.accept');
        });

    Route::middleware(['api', 'auth:api','lang'])
        ->controller(ConnectorController::class)
        ->group(function () {
            Route::post('workspaces/{workspace_id}/connectors', 'createConnector');
        });


    Route::middleware(['api', 'auth:api'])
        ->prefix('{organization}/membership-plans')
        ->controller(OrganizationMembershipPlanController::class)
        ->group(function () {
            Route::get('/', 'index');    // Get all membership plans for the organization
            Route::get('/{id}', 'show'); // Get a specific membership plan
            Route::post('/{id}/activate', 'activate');
        });

    Route::middleware(['api','auth:api'])
        ->prefix('workspaces/{workspace}')
        ->controller(InboxAgentController::class)
        ->group(function () {
            Route::get('/inbox-agents/me', 'getInboxAgent')
                ->name('workspace.inbox-agent.me');
            Route::post('/inbox-agents/{user}', 'update');

            Route::get('/inbox-agents', 'getInboxAgents')
                ->name('workspace.inbox-agents.all');

            Route::post('/inbox-agents/{user}/activate', 'activateInboxAgent');
        });

    Route::middleware(['api'])
        ->prefix('organizations/{organization}')
        ->controller(RequiredActionController::class)
        ->group(function () {
            Route::get('/required-actions', 'index')->name('organization.required-actions.index');
            Route::post('/required-actions/{requiredAction}/complete', 'complete')->name('organization.required-actions.complete');
            Route::post('/required-actions/{requiredAction}/dismiss', 'dismiss')->name('organization.required-actions.dismiss');
        });

    Route::prefix('organizations/{organization}/workspaces/{workspace}')
        ->name('organizations.workspaces.')
        ->middleware(['api'])
        ->group(function () {
            Route::prefix('notifications')
                ->name('notifications.')
                ->controller(DashboardNotificationController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::patch('{notification}', 'update')->name('update');
                });

        });

    Route::middleware(['api'])
        ->group(function () {
            Route::prefix('logs')
                ->name('logs')
                ->controller(MetaConversationLogController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                });
        });

    // Supported Languages for Translation
    Route::middleware(['api'])
        ->controller(LanguagesController::class)
        ->group(function () {
            Route::get('/languages', 'index')->name('languages.index');
        });

    // Organization Translation Settings
    Route::middleware(['api', 'auth:api'])
        ->prefix('organizations/{organization}/translation-settings')
        ->controller(OrganizationTranslationSettingsController::class)
        ->group(function () {
            Route::get('/', 'show')->name('organization.translation-settings.show');
            Route::post('/', 'update')->name('organization.translation-settings.update');
        });

});



Route::post('/run-migrations', function (Request $request) {
//    // optional protection (change the token in .env)
//    abort_unless($request->header('X-DEPLOY-TOKEN') === env('DEPLOY_SECRET'), 403);

    try {
        // Run migrations with force
        Artisan::call('migrate', ['--force' => true]);

        return response()->json([
            'status' => 'success',
            'output' => Artisan::output(), // full migration output
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

