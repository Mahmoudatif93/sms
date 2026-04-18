<?php

namespace App\Traits;

use Http;

trait MessengerOnboardingManager
{

    use BusinessTokenManager, BusinessManagerAccountManager, MetaPageManager;

    public function performMessengerOnboarding($clientId, $clientSecret, $code, $appAccessToken): array
    {
        // 1. Exchange the authorization code for a user access token
        $tokenData = $this->exchangeCodeForAccessToken($clientId, $clientSecret, $code, $appAccessToken);
        if (!$tokenData) {
            return ['success' => false, 'message' => 'Failed to exchange code for access token.'];
        }

        // 2. Debug and validate the access token
        $debugData = $this->debugAccessToken($tokenData['access_token'], $appAccessToken);
        if (!$debugData) {
            return ['success' => false, 'message' => 'Failed to debug access token.'];
        }

        // 3. Fetch and store Business Manager ID details
        $businessManagerId = $this->getBusinessManagerIdByBIAccessToken($tokenData['access_token']);
        if (!$businessManagerId) {
            return ['success' => false, 'message' => 'Failed to retrieve Business Manager ID.'];
        }

        $this->fetchAndStoreBusinessManagerDetails($businessManagerId, $tokenData['access_token']);

        // 4. Store the Business Integration System User Access Token
        $this->storeBusinessIntegrationToken($tokenData, $businessManagerId);

        // 5. Fetch all owned pages and subscribe to webhooks
        $allPages = $this->getAllPages($businessManagerId, $tokenData['access_token']);

        foreach ($allPages as $page) {
            $subscriptionResult = $this->subscribePageToWebhook($page['page_id'], $page['page_access_token']);

            if (!$subscriptionResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to subscribe Page webhooks.',
                    'error' => $subscriptionResult['message'] ?? 'Unknown error occurred during webhook subscription.',
                ];
            }
        }

        // 6. Onboarding complete
        return [
            'success' => true,
            'data' => [
                'business_manager_id' => $businessManagerId,
                'pages' => $allPages,
            ],
        ];
    }


    private function getBusinessManagerIdByBIAccessToken(string $accessToken): ?string
    {
        $endpoint = "https://graph.facebook.com/v22.0/me?fields=client_business_id";

        $response = Http::withToken($accessToken)->get($endpoint);

        if (!$response->successful()) {
            return null;
        }

        $body = $response->json();
        return $body['client_business_id'] ?? null;
    }


}
