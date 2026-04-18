<?php


use App\Http\Controllers\ChannelControllerV2;

Route::controller(ChannelControllerV2::class)
    ->group(function () {
        Route::get('organizations/{organization}/channels', 'organizationIndex');
        Route::get('organizations/{organization}/channels/{channel}/pay', 'processChannelPayment');

        Route::get('channels/whatsapp/setup-info', 'getWhatsappSetupInfo');
        Route::get('channels/sms/setup-info', 'getSmsSetupInfo');
        Route::get('channels/livechat/setup-info', 'getLiveChatSetupInfo');
        Route::get('channels/ticketing/setup-info', 'getTicketingSetupInfo');

        Route::get('workspaces/{workspace_id}/channels/available', 'getAvailableChannels');
        Route::post('workspaces/{workspace}/channels/install', 'installChannel');
        Route::post('workspaces/{workspace}/channels/{channel}/connect', 'connectChannelToWorkspace');

        Route::get('workspaces/{workspace}/channels', 'index');
        Route::delete('workspaces/{workspace}/channels/{channel}', 'destroy');
        Route::get('workspaces/{workspace}/channels/{channel}', 'show');
        Route::put('workspaces/{workspace}/channels/{channel}', 'update');
    });
