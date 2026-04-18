<?php

namespace App\Traits;

use App\Constants\Meta;
use App\Models\BusinessIntegrationSystemUserAccessToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

trait BusinessTokenManager
{
    /**
     * Get a valid access token for the specified business manager account.
     * If the token is expired, refresh it.
     *
     * @param string $businessManagerAccountId
     * @return string|null
     */
    public function getValidAccessToken(string $businessManagerAccountId): ?string
    {
        // Fetch the latest token for the business manager account
        $accessTokenRecord = BusinessIntegrationSystemUserAccessToken::whereBusinessManagerAccountId($businessManagerAccountId)
            ->latest()
            ->first();

        $debug = $this->debugAccessToken($accessTokenRecord->access_token);

        $accessTokenExpiresAt = $debug['data']['expires_at'];

        // If no token exists, or it has expired, refresh or generate a new one.
        // This grace Period is in Minutes
        $gracePeriod = 20;
        $gracePeriodInSeconds = $gracePeriod * 60;

        if (
            !$accessTokenRecord
            ||
            (
                $accessTokenExpiresAt != 0
                &&
                (
                    time() - $gracePeriodInSeconds
                ) > $accessTokenExpiresAt
            )
        ) {
            return $this->refreshAccessToken($businessManagerAccountId);
        }

        // Return the valid access token
        return $accessTokenRecord->access_token;
    }

    /**
     * Refresh the access token for the business manager account.
     *
     * @param string $businessManagerAccountId
     * @return string|null
     */
    public function refreshAccessToken($businessManagerAccountId)
    {
        // Implement your logic to request a new token
        $newTokenResponse = Http::post('https://graph.facebook.com/v20.0/oauth/access_token', [
            'client_id' => env('META_APP_ID'),
            'client_secret' => env('META_APP_SECRET'),
            'grant_type' => 'fb_exchange_token',
            'fb_exchange_token' => 'OLD_ACCESS_TOKEN', // Replace with logic to fetch old token
        ]);

        if ($newTokenResponse->successful()) {
            $newToken = $newTokenResponse->json();

            // Update or create the new access token record in the database
            $expiresAt = Carbon::now()->addSeconds($newToken['expires_in']);
            BusinessIntegrationSystemUserAccessToken::updateOrCreate(
                ['business_manager_account_id' => $businessManagerAccountId],
                [
                    'access_token' => $newToken['access_token'],
                    'expires_at' => $expiresAt,
                    'token_type' => $newToken['token_type'],
                ]
            );

            return $newToken['access_token'];
        }

        // Return null if the refresh fails
        return null;
    }

    /**
     * Generate a granular access token for specific assets and scopes.
     *
     * @param string $businessId
     * @param string $systemUserId
     * @param array $assetIds
     * @param array $scopeIds
     * @return string|null
     */
    public function generateGranularToken($businessId, $systemUserId, array $assetIds = [], array $scopeIds = [])
    {
        // Fetch the main access token
        $accessToken = $this->getValidAccessToken($businessId);
        if (!$accessToken) {
            return null;
        }

        // Generate app secret proof for security
        $appSecretProof = $this->generateAppSecretProof($accessToken);

        // Send request to generate the granular token
        $response = Http::post("https://graph.facebook.com/v20.0/{$businessId}/system_user_access_tokens", [
            'appsecret_proof' => $appSecretProof,
            'access_token' => $accessToken,
            'system_user_id' => $systemUserId,
            'asset' => implode(',', $assetIds),
            'scope' => implode(',', $scopeIds),
            'set_token_expires_in_60_days' => true,
        ]);

        if ($response->successful()) {
            return $response->json()['access_token'];
        }

        // Throw an exception or handle failure appropriately
        throw new \Exception('Failed to generate granular token');
    }

    /**
     * Generate the app secret proof for additional security.
     *
     * @param string $accessToken
     * @return string
     */
    private function generateAppSecretProof($accessToken)
    {
        return hash_hmac('sha256', $accessToken, env('META_APP_SECRET'));
    }

    private function debugAccessToken($inputToken)
    {
        $accessToken = Meta::ACCESS_TOKEN;
        $endpoint = 'https://graph.facebook.com/v22.0/debug_token';
        $params = [
            'input_token' => $inputToken,
            'access_token' => $accessToken,
        ];

        $response = Http::get($endpoint, $params);

        return $response->successful() ? $response->json() : null;
    }

    public function exchangeCodeForAccessToken($clientId, $clientSecret, $code, $accessToken)
    {
        $endpoint = 'https://graph.facebook.com/v23.0/oauth/access_token';
        $params = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
        ];

        $response = Http::withToken($accessToken)->get($endpoint, $params);

        return $response->successful() ? $response->json() : null;
    }

    public function storeBusinessIntegrationToken($tokenData, $businessManagerId): void
    {
        BusinessIntegrationSystemUserAccessToken::updateOrCreate([
            'business_manager_account_id' => $businessManagerId,
            'access_token' => $tokenData['access_token'],
            'expires_in' => $tokenData['expires_in'] ?? 0,
        ], [
            'type' => $tokenData['token_type']
        ]);
    }

}