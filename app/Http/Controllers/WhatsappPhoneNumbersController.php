<?php

namespace App\Http\Controllers;

use App\Constants\Meta;
use App\Models\Channel;
use App\Models\WhatsappPhoneNumber;
use App\Traits\ChannelManager;
use Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappPhoneNumbersController extends BaseApiController
{
    use ChannelManager;
    /**
     * @OA\Get(
     *     path="/api/whatsapp/whatsapp-phone-numbers/{whatsapp_business_account_id}",
     *     summary="Get phone numbers for a WhatsApp business account",
     *     description="Fetches phone numbers associated with a specified WhatsApp business account, and returns the stored phone numbers.",
     *     tags={"WhatsApp"},
     *     @OA\Parameter(
     *         name="whatsapp_business_account_id",
     *         in="path",
     *         description="The ID of the WhatsApp business account.",
     *         required=true,
     *         @OA\Schema(type="string", example="109346662215398")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with phone numbers",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Phone numbers fetched successfully."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/WhatsappPhoneNumber")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Failed to fetch phone numbers",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Failed to fetch phone numbers."
     *             )
     *         )
     *     )
     * )
     */
    public function getPhoneNumbers(Channel $channel)
    {
        $channelDetails = $this->getChannelDetails($channel);

        // Check if the response from getChannelDetails is an error response
        if ($channelDetails instanceof JsonResponse) {
            return $channelDetails; // Return the error response
        }

        $whatsappBusinessAccountID = $channelDetails['whatsapp_business_account_id'];
        $accessToken = $channelDetails['access_token'];

        $url = "https://graph.facebook.com/v20.0/{$whatsappBusinessAccountID}/phone_numbers";

        // Fetch phone numbers
        $response = Http::withToken($accessToken)->get($url);

        if ($response->successful()) {
            $phoneNumbers = json_decode($response->body())->data;
            // Loop through each phone number data and store it in the database
            foreach ($phoneNumbers as $phoneNumber) {
                WhatsAppPhoneNumber::updateOrCreate(
                    ['id' => $phoneNumber->id,
                        'whatsapp_business_account_id' => $whatsappBusinessAccountID,
                    ],
                    [
                        'verified_name' => $phoneNumber->verified_name,
                        'code_verification_status' => $phoneNumber->code_verification_status ?? null,
                        'display_phone_number' => $phoneNumber->display_phone_number ?? null,
                        'quality_rating' => $phoneNumber->quality_rating ?? null,
                        'platform_type' => $phoneNumber->platform_type ?? null,
                    ]
                );

            }

            $storedPhoneNumbers = WhatsAppPhoneNumber::where('whatsapp_business_account_id', $whatsappBusinessAccountID)->get();

            // Format the stored phone numbers for the response
            $formattedPhoneNumbers = $storedPhoneNumbers->map(fn(WhatsAppPhoneNumber $phoneNumber) => new \App\Http\Responses\WhatsappPhoneNumber($phoneNumber));

            return $this->response(true, 'Phone numbers fetched successfully.', $formattedPhoneNumbers);
        }


        return $this->response(false, 'Failed to fetch phone numbers.');
    }
}
