<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('whatsapp-chat-channel', function ($user) {
    // You can add authentication logic here if needed. For now, we'll allow all users.
    return true;
});

Broadcast::channel('live-chat-channel', function ($user) {
    // You can add authentication logic here if needed. For now, we'll allow all users.
    return true;
});

Broadcast::channel('organization.{organizationId}.workspace.{workspaceId}', function ($user, $organizationId, $workspaceId) {
    return true; // Add authorization logic as needed
});
