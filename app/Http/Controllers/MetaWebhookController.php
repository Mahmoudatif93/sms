<?php

namespace App\Http\Controllers;

use App\Domain\Messenger\Handlers\MessengerWebhookHandler;
use App\Domain\WhatsApp\Handlers\WhatsAppWebhookHandler;
use App\Http\Responses\Conversation;
use App\Http\Slack;
use App\Models\AttributeDefinition;
use App\Models\ContactAttribute;
use App\Models\ContactEntity;
use App\Models\MessengerConsumer;
use App\Models\MessengerMessage;
use App\Models\MetaWebhookLog;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Str;

class MetaWebhookController extends Controller
{
    public function verifyWebhook(Request $request): Application|Response|\Illuminate\Contracts\Foundation\Application|ResponseFactory
    {

        Slack::Log(json_encode($request->all()), __FILE__, __LINE__);

        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        // Verify the token
        if ($mode === 'subscribe' && $token === config('services.whatsapp.webhook_verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function handleWebhook(Request $request): Application|Response|\Illuminate\Contracts\Foundation\Application|ResponseFactory
    {
        $rawPayload = json_encode($request->all(), JSON_UNESCAPED_UNICODE);
        MetaWebhookLog::firstOrCreate([
            'payload' => $rawPayload
        ]);

        $object = $request->get("object");
        $platform = match ($object) {
            'whatsapp_business_account' => "whatsapp",
            'page' => 'messenger',
            default => "Unknown Object"
        };


        $notification = $request->all();
        // Delegate to the correct handler based on platform
        $eventHandler = match ($object) {
            'whatsapp_business_account' => new WhatsAppWebhookHandler($notification),
            'page' => new MessengerWebhookHandler($notification),
            default => null
        };

        if (empty($eventHandler)) {
            // Optionally log unknown webhook
            $encodedNotification = json_encode($notification);
            return response('Event Received', 200);
        }

        // Handle the events in the notification
        $eventHandler->handle();
        return response('Event Received', 200);
    }

    private function isValidSignature($payload, $signature): bool
    {
        $appSecret = config('services.whatsapp.app_secret');
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        return hash_equals($expectedSignature, $signature);
    }
}
