<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChannelPolicy
{
    /**
     * Determine whether the user can view a specific channel.
     */
    public function view(User $user, Channel $channel): Response
    {
        return $user->hasChannelAccess($channel->id)
            ? Response::allow()
            : Response::deny('You do not have access to view this channel.');
    }

    /**
     * Determine whether the user can delete the channel.
     */
    public function delete(User $user, Channel $channel): Response
    {
        return $user->hasChannelAccess($channel->id)
            ? Response::allow()
            : Response::deny('You do not have permission to delete this channel.');
    }
}
