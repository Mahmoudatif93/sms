<?php

namespace App\Traits;

use App\Models\BusinessIntegrationSystemUserAccessToken;
use App\Models\BusinessManagerAccount;
use App\Models\WhatsappBusinessAccount;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappPhoneNumber;
use Http;

trait WhatsappOnboardingManager
{

    use BusinessTokenManager, BusinessManagerAccountManager;

    public function performOnboarding($clientId, $clientSecret, $code, $accessToken, $whatsappBusinessAccountId, $phoneNumberId): array
    {
        // 1. Exchange the authorization code for an access token
        $tokenData = $this->exchangeCodeForAccessToken($clientId, $clientSecret, $code, $accessToken);
        if (!$tokenData) {
            return ['success' => false, 'message' => 'Failed to exchange code for access token.'];
        }

        // 2. Debug and validate the access token
        $debugData = $this->debugAccessToken($tokenData['access_token'], $accessToken);
        if (!$debugData) {
            return ['success' => false, 'message' => 'Failed to debug access token.'];
        }

        // 3. Fetch and store Business Manager ID details
        $businessManagerId = $this->getBusinessManagerId($whatsappBusinessAccountId, $tokenData['access_token'], $phoneNumberId);
        if (!$businessManagerId) {
            return ['success' => false, 'message' => 'Failed to retrieve Business Manager ID.'];
        }

        // 4. Store the Business Integration System User Access Token
        $this->storeBusinessIntegrationToken($tokenData, $businessManagerId);

        // 5. Check if the phone number is already registered
        $phoneNumber = WhatsappPhoneNumber::find($phoneNumberId);

        if (!$phoneNumber) {
            return [
                'success' => false,
                'message' => 'Phone number not found in the database.',
            ];
        }

        if (!$phoneNumber->is_registered) {
            // Register the phone number for Cloud API
            $registrationResponse = $this->registerPhoneNumber($phoneNumberId, $tokenData['access_token']);

            if (!$registrationResponse['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to register the phone number. ' . json_encode($registrationResponse['message']),
                    'error' => 'Unknown error occurred in Phone Number Registration.',
                ];
            }
        }

        // 6. Subscribe the app to the customer's WABA for webhook notifications
        $webhookSubscription = $this->subscribeToWABA($whatsappBusinessAccountId, $tokenData['access_token']);
        if (!$webhookSubscription['success']) {
            return [
                'success' => false,
                'message' => 'Failed to subscribe to WABA webhooks.',
                'error' => $webhookSubscription['message'] ?? 'Unknown error occurred in Webhook Subscription.',
            ];
        }


        // 5. Extend credit line or any other post-onboarding steps
        $this->extendCreditLine($businessManagerId);

        // Return successful onboarding data
        return [
            'success' => true,
            'business_manager_id' => $businessManagerId,
            'access_token' => $tokenData['access_token'],
        ];
    }

    private function getBusinessManagerId($whatsappBusinessAccountId, $accessToken, $phoneNumberId)
    {
        $businessManagerData = $this->fetchBusinessManagerData($whatsappBusinessAccountId, $accessToken);
        if (!$businessManagerData) {
            return null;
        }

        $businessManagerId = $businessManagerData->owner_business_info->id ?? null;
        if ($businessManagerId) {
            $account = $this->fetchAndStoreBusinessManagerDetails($businessManagerId, $accessToken);
            $this->fetchAndStoreWhatsAppBusinessAccounts($businessManagerId, $accessToken, $whatsappBusinessAccountId, $phoneNumberId);
        }

        return $account->id ?? null;
    }

    public function fetchBusinessManagerData($whatsappBusinessAccountId, $accessToken)
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$whatsappBusinessAccountId}?fields=owner_business_info";
        $response = Http::withToken($accessToken)->get($endpoint);

        return $response->successful() ? json_decode($response->body()) : null;
    }

    private function fetchAndStoreWhatsAppBusinessAccounts($businessManagerId, $accessToken, $whatsappBusinessAccount, $phoneNumberId): void
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$whatsappBusinessAccount}";
        $response = Http::withToken($accessToken)->get($endpoint);

        if ($response->successful()) {
            $data = json_decode($response->body());

                $whatsappBusinessAccount = WhatsappBusinessAccount::updateOrCreate(
                    ['id' => $data->id, 'business_manager_account_id' => $businessManagerId],
                    [
                        'name' => $data->name,
                        'message_template_namespace' => $data->message_template_namespace,
                        'currency' => $data->currency ?? null,
                    ]
                );
                $this->fetchAndStorePhoneNumbers($data->id, $accessToken, $phoneNumberId);

        }
    }

    public function fetchAndStorePhoneNumbers($whatsappBusinessAccountId, $accessToken, $phoneNumberId): void
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$phoneNumberId}";
        $response = Http::withToken($accessToken)->get($endpoint);

        if ($response->successful()) {
            $phoneNumber = json_decode($response->body());

                $whatsappPhoneNumber = WhatsappPhoneNumber::updateOrCreate(
                    ['id' => $phoneNumber->id, 'whatsapp_business_account_id' => $whatsappBusinessAccountId],
                    [
                        'verified_name' => $phoneNumber->verified_name,
                        'code_verification_status' => $phoneNumber->code_verification_status ?? null,
                        'display_phone_number' => $phoneNumber->display_phone_number ?? null,
                        'quality_rating' => $phoneNumber->quality_rating ?? null,
                        'platform_type' => $phoneNumber->platform_type ?? null,
                    ]
                );
                $this->fetchAndStoreBusinessProfile($phoneNumber->id, $accessToken, $whatsappBusinessAccountId);

        }
    }

    public function fetchAndStoreBusinessProfile($phoneNumberId, $accessToken, $whatsappBusinessAccountId): void
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$phoneNumberId}/whatsapp_business_profile";
        $response = Http::withToken($accessToken)->get($endpoint, [
            'fields' => 'about,address,description,email,profile_picture_url,vertical',
        ]);

        if ($response->successful()) {
            $profile = json_decode($response->body())->data[0] ?? null;
            if ($profile) {
                WhatsappBusinessProfile::updateOrCreate(
                    [
                        'whatsapp_business_account_id' => $whatsappBusinessAccountId,
                        'whatsapp_phone_number_id' => $phoneNumberId,
                    ],
                    [
                        'about' => $profile->about ?? null,
                        'address' => $profile->address ?? null,
                        'description' => $profile->description ?? null,
                        'email' => $profile->email ?? null,
                        'profile_picture_url' => $profile->profile_picture_url ?? null,
                        'vertical' => $profile->vertical ?? null,
                    ]
                );
            }
        }
    }

    public function extendCreditLine($businessManagerId)
    {
        // Implement any additional logic for extending the credit line or other post-onboarding steps
    }

    public function registerPhoneNumber($phoneNumberId, $accessToken): array
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$phoneNumberId}/register";

        $pin = $this->generateSixDigitPin();
        // Construct the payload
        $payload = [
            'messaging_product' => 'whatsapp',
            'pin' => $pin,
        ];

        // Make the API request
        $response = Http::withToken($accessToken)->post($endpoint, $payload);

        // Handle response
        if ($response->successful()) {

            // Update database
            WhatsappPhoneNumber::where('id', $phoneNumberId)->update([
                'is_registered' => true,
                'pin' => $pin,
            ]);

            return [
                'success' => true,
                'data' => $response->json(),
            ];
        }

        // Handle errors
        $errorResponse = $response->json();

        // Check for the "Two-step verification PIN mismatch" error
        if (
            isset($errorResponse['error']['code']) &&
            $errorResponse['error']['code'] === 133005
        ) {
            // Save as already registered
            WhatsappPhoneNumber::where('id', $phoneNumberId)->update([
                'is_registered' => true,
            ]);

            return [
                'success' => true,
                'message' => 'Phone number is already registered.',
            ];
        }

        // Return error response
        return [
            'success' => false,
            'message' => $response->json() ?? 'Failed to register phone number.',
            'code' => $response->json()['error']['code'] ?? null,
        ];
    }

    private function generateSixDigitPin(): string
    {
        // Use random_int in a try-catch to ensure safety
        try {
            return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } catch (\Exception $e) {
            // Fallback to a deterministic but valid random-like PIN
            return substr(str_shuffle('0123456789'), 0, 6);
        }
    }

    public function getPhoneNumberVerificationStatus($phoneNumberId, $accessToken): array
    {
        $endpoint = "https://graph.facebook.com/v21.0/{$phoneNumberId}";
        $params = [
            'fields' => 'display_phone_number,verified_name,code_verification_status',
        ];

        $response = Http::withToken($accessToken)->get($endpoint, $params);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'verified' => strtolower($data['code_verification_status']) === 'verified',
                'data' => $data,
            ];
        }

        return [
            'success' => false,
            'message' => $response->json()['error']['message'] ?? 'Failed to fetch phone number details.',
        ];
    }

    public function subscribeToWABA($wabaId, $accessToken): array
    {
        $endpoint = "https://graph.facebook.com/v21.0/{$wabaId}/subscribed_apps";

        $response = Http::withToken($accessToken)->post($endpoint);

        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $response->json(),
            ];
        }

        return [
            'success' => false,
            'message' => $response->json()['error']['message'] ?? 'Failed to subscribe to WABA.',
        ];
    }



}
