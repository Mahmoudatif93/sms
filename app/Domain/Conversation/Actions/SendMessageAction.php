<?php

namespace App\Domain\Conversation\Actions;

use App\Domain\Conversation\Services\ChannelResolver;
use App\Models\Conversation;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SendMessageAction
{
    use ResponseManager;

    public function __construct(
        private ChannelResolver $channelResolver
    ) {}

    public function execute(Request $request, Conversation $conversation): JsonResponse
    {
        $channel = $conversation->channel;

        // Check if platform is supported
        if (!$this->channelResolver->supports($channel->platform)) {
            return response()->json(['error' => 'Unsupported Platform'], 400);
        }

        // Resolve channel and send message
        $channelImplementation = $this->channelResolver->resolve($channel->platform);
        // Each channel dispatches its own events for broadcasting
        return $channelImplementation->sendMessage($request, $conversation);
    }
}
