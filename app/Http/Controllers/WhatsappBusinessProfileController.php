<?php

namespace App\Http\Controllers;

use App\Constants\Meta;
use App\Models\Channel;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappPhoneNumber;
use App\Traits\ChannelManager;
use Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappBusinessProfileController extends BaseApiController
{

    use ChannelManager;

    /**
     * @OA\Get(
     *     path="/api/whatsapp/whatsapp-business-profile/{phoneNumberID}",
     *     summary="Get WhatsApp business profile by phone number ID",
     *     description="Fetches the WhatsApp business profile for a specified phone number ID.",
     *     tags={"WhatsApp"},
     *     @OA\Parameter(
     *         name="phoneNumberID",
     *         in="path",
     *         description="The ID of the phone number associated with the WhatsApp business profile.",
     *         required=true,
     *         @OA\Schema(type="string", example="108427225641466")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with business profile",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Business profile fetched successfully."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/WhatsappBusinessProfile"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Failed to fetch business profile",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Failed to fetch business profile."
     *             )
     *         )
     *     )
     * )
     */
    public function getProfile(Channel $channel)
    {
        $channelDetails = $this->getChannelDetails($channel);

        // Check if the response from getChannelDetails is an error response
        if ($channelDetails instanceof JsonResponse) {
            return $channelDetails; // Return the error response
        }

        $phoneNumberID = $channelDetails['phone_number_id'];
        $accessToken = $channelDetails['access_token'];


        $profileUrl = "https://graph.facebook.com/v20.0/{$phoneNumberID}/whatsapp_business_profile";
        $profileResponse = Http::withToken($accessToken)->get($profileUrl, [
            'fields' => 'about,address,description,email,profile_picture_url,vertical',
        ]);

        $whatsapp_business_account_id = WhatsappPhoneNumber::where('id', '=', $phoneNumberID)->first()->whatsapp_business_account_id;

        if ($profileResponse->successful()) {
            $businessProfile = json_decode($profileResponse->body())->data[0];
            $whatsappBusinessProfile = WhatsappBusinessProfile::updateOrCreate(
                [
                    'whatsapp_business_account_id' => $whatsapp_business_account_id,
                    'whatsapp_phone_number_id' => $phoneNumberID
                ],
                [
                    'about' => $businessProfile->about ?? null,
                    'address' => $businessProfile->address ?? null,
                    'description' => $businessProfile->description ?? null,
                    'email' => $businessProfile->email ?? null,
                    'profile_picture_url' => $businessProfile->profile_picture_url ?? null,
                    'vertical' => $businessProfile->vertical ?? null,
                ]
            );


            return $this->response(true, 'Business profile fetched successfully.', new \App\Http\Responses\WhatsappBusinessProfile($whatsappBusinessProfile));
        }


        return $this->response(false, 'Failed to Business Profile.');
    }

//
//    /////////////////////////////////////////////////////////////////////////////////////////////////
//    ///
//$url = "https://graph.facebook.com/v20.0/109346662215398/phone_numbers";
//
//    // Fetch phone numbers
//$response = Http::withToken($accessToken)->get($url);
//dd($response->json());
//if ($response->successful()) {
//$data = $response->json();
//    // return $this->response(true, 'Phone numbers fetched successfully.', $data['data']);
//}
//
//
//$phoneNumberId = env('DREAMS_WHATSAPP_BUSINESS_ACCOUNT_ID');
//
//$url = "https://graph.facebook.com/v20.0/108427225641466/whatsapp_business_profile";
//
//// Fetch Business Profile
//$response = Http::withToken($accessToken)->get($url, [
//    'fields' => 'about,address,description,email,profile_picture_url,websites,vertical',
//]);
//
//dd($response->json());
//if ($response->successful()) {
//    $data = $response->json();
//    return $this->response(true, 'Business profile fetched successfully.', $data);
//}

}
