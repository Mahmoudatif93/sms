<?php

namespace App\Http\Controllers;

use App\Constants\Meta;
use App\Http\Responses\ValidatorErrorResponse;
use App\Models\MessengerMessage;
use App\Models\MetaPage;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTextMessage;
use App\Rules\WhatsappValidPhoneNumber;
use Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MessengerMessageController extends BaseApiController
{
    public function sendTextMessage(Request $request): JsonResponse
    {


        // @todo Validator

        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:meta_pages,id'],
            'to' => ['required', 'string', 'exists:messenger_consumers,psid'],
            'text' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        $from = $request->input('from');
        $to = $request->input('to');
        $message = $request->input('text');


        $accessToken = MetaPage::whereId($from)->first()?->accessTokens()->first()?->access_token;

        dd($accessToken);

        if (!$accessToken) {
            return response()->json(['error' => 'Failed to get a valid access token'], 401);
        }

        $url = "https://graph.facebook.com/v22.0/{$from}/messages";

        $message = [
            "recipient" => [
                "id" => $to,
            ],
            "messaging_type" => "RESPONSE",
            "message" => [
                MessengerMessage::MESSAGE_TYPE_TEXT => $message
            ]
        ];


        $response = Http::withToken($accessToken)
            ->post($url, $message);

        dd($response->json());

        if (!$response->successful()) {
            return $this->response(false, 'Something Went Wrong', new ValidatorErrorResponse($response->json()), $response->status());
        }
        $responseData = json_decode($response->body());

        dd($responseData);

        // Contact Subscriptions to Messenger and Link MessengerConsumers

        $wa_id = $responseData->contacts[0]->wa_id;



        $whatsappMessage = WhatsappMessage::create([
            'id' => $responseData->messages[0]->id,
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'recipient_id' => $recipient->id,
            'recipient_type' => get_class($recipient),
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => WhatsappMessage::MESSAGE_TYPE_TEXT,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED
        ]);

        $whatsappTextMessage = WhatsappTextMessage::create([
            'whatsapp_message_id' => $responseData->messages[0]->id,
            'body' => $body,
            'preview_url' => $previewUrl,
        ]);

        // Update the messageable relation in the WhatsappMessage
        $didUpdate = $whatsappMessage->update([
            'messageable_id' => $whatsappTextMessage->id,
            'messageable_type' => WhatsappTextMessage::class,
        ]);

        // Save New Message Status
        $this->saveMessageStatus(
            (string)$whatsappMessage->id,
            WhatsappMessage::MESSAGE_STATUS_INITIATED
        );


        // Eager load statuses and messageable relations
        $whatsappMessageWithRelations = WhatsappMessage::with('statuses', 'messageable')->find($whatsappMessage->id);
        /*
         *
         * @todo send the whole Model Message
         */

        return $this->response(true, 'Text Message Sent Successfully', $whatsappMessageWithRelations, $response->status());


    }
}
