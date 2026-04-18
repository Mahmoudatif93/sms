<?php

namespace App\Http\Controllers;

use App\Constants\Meta;
use App\Models\BusinessIntegrationSystemUserAccessToken;
use App\Models\BusinessManagerAccount;
use App\Models\User;
use App\Models\WhatsappBusinessAccount;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappPhoneNumber;
use App\Traits\WhatsappOnboardingManager;
use Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnBoardingController extends BaseApiController
{

    use WhatsappOnboardingManager;


    /**
     * @OA\Post(
     *     path="/api/whatsapp/onboard",
     *     summary="Onboard a WhatsApp business account",
     *     description="Exchanges an authorization code for an access token and retrieves the Business Manager ID associated with the WhatsApp Business Account.",
     *     operationId="onBoard",
     *     tags={"WhatsApp"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"code", "whatsapp_business_account_id"},
     *                 @OA\Property(
     *                     property="code",
     *                     type="string",
     *                     description="The authorization code to exchange for an access token."
     *                 ),
     *                 @OA\Property(
     *                     property="whatsapp_business_account_id",
     *                     type="string",
     *                     description="The WhatsApp Business Account ID."
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Onboarding successful",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Onboarding successful"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="business_manager_id",
     *                     type="string",
     *                     example="123456789012345"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request due to missing required parameters or failed operations",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Missing required parameters"
     *             )
     *         )
     *     )
     * )
     */
    public function onBoard(Request $request): JsonResponse
    {

        $accessToken = Meta::ACCESS_TOKEN;
        $clientId = env('WHATSAPP_APP_ID');
        $clientSecret = env('WHATSAPP_APP_SECRET');

        $code = $request->input('code');
        $whatsappBusinessAccountId = $request->input('whatsapp_business_account_id');

        //Validate required parameters
        if (!$code || !$whatsappBusinessAccountId) {
            return $this->response(false, 'Missing required parameters', null, 400);
        }

        // Exchange the code for an access token
        $tokenData = $this->exchangeCodeForAccessToken($clientId, $clientSecret, $code, $accessToken);
        if (!$tokenData) {
            return $this->response(false, 'Failed to exchange code for access token', null, 400);
        }

        // Debug the access token
        $debugData = $this->debugAccessToken($tokenData['access_token'], $accessToken);
        if (!$debugData) {
            return $this->response(false, 'Failed to debug access token', null, 400);
        }


        $user = Auth::user();

        // Get Business Manager ID using the WhatsApp Business Account ID
        $businessManagerId = $this->getBusinessManagerId($whatsappBusinessAccountId, $tokenData['access_token'], $user);
        if (!$businessManagerId) {
            return $this->response(false, 'Failed to retrieve Business Manager ID', null, 400);
        }


        BusinessIntegrationSystemUserAccessToken::updateOrCreate([
            'access_token' => $tokenData['access_token'],
            'expires_in' => $tokenData['expires_in'],
            'business_manager_account_id' => $businessManagerId
        ], ['type' => $tokenData['token_type']]);


        /*
         * @todo Extend Credit Line
         *
         */


        // Do something with $businessManagerId or return a success response
        return $this->response(true, 'Onboarding successful', ['business_manager_id' => $businessManagerId], 200);
    }





}
