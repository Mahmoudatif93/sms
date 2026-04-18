<?php

namespace App\Traits;

use App;
use App\Models\Channel;
use App\Models\ChannelFlowKey;
use Exception;
use Http;
use Illuminate\Http\Client\ConnectionException;

trait FlowsManager
{
    /**
     * Fetch existing or generate + persist a key pair for the given channel.
     *
     * @param Channel $channel
     * @return ChannelFlowKey
     * @throws Exception
     */
    public function generateOrFetchChannelFlowKey(Channel $channel): ChannelFlowKey
    {
        $existing = ChannelFlowKey::where('channel_id', $channel->id)->first();
        if ($existing) {
            return $existing;
        }


        $keyPair = $this->generateRsaKeyPairWithPassphrase($channel->id);

        return ChannelFlowKey::create([
            'channel_id' => $channel->id,
            'public_key' => $keyPair['public_key'],
            'private_key' => $keyPair['private_key'], // Encrypted with passphrase
        ]);
    }

    /**
     * Generate a 2048-bit RSA key pair encrypted with APP_KEY.
     *
     * @return array ['public_key' => string, 'private_key' => string]
     * @throws Exception
     */
    protected function generateRsaKeyPairWithPassphrase($channelID): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        if (!$res) {
            throw new Exception('Failed to generate RSA key: ' . openssl_error_string());
        }

        $privateKeyOut = '';
        if (!openssl_pkey_export($res, $privateKeyOut, $channelID)) {
            throw new Exception('Failed to export private key: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($res);
        if (!isset($details['key'])) {
            throw new Exception('Failed to extract RSA public key.');
        }

        return [
            'private_key' => $privateKeyOut, // Encrypted using APP_KEY
            'public_key' => $details['key'],
        ];
    }

    /**
     * Decrypt a stored private key using APP_KEY as a passphrase.
     *
     * @param string $encryptedPrivateKey
     * @return \OpenSSLAsymmetricKey OpenSSL key resource
     * @throws Exception
     */
    public function loadDecryptedPrivateKey(string $encryptedPrivateKey, $channelID): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_get_private($encryptedPrivateKey, $channelID);

        if (!$key) {
            throw new Exception('Failed to decrypt private key: ' . openssl_error_string());
        }

        return $key;
    }

    /**
     * @throws Exception
     */
    public function ensureFlowKeyRegisteredWithMeta(Channel $channel, $accessToken): ChannelFlowKey
    {
        $flowKey = $this->generateOrFetchChannelFlowKey($channel);


        $whatsappConfig = $channel->whatsappConfiguration;
        $phoneNumberId = $whatsappConfig?->primary_whatsapp_phone_number_id;

        if (!$phoneNumberId) {
            throw new Exception("Channel missing WhatsApp phone number.");
        }

        if (!$this->isBusinessPublicKeyAlreadyUploaded($phoneNumberId, $flowKey->public_key, $accessToken)) {
            $this->uploadBusinessPublicKeyToMeta($phoneNumberId, $flowKey->public_key, $accessToken);
        }

        return $flowKey;
    }

    protected function isBusinessPublicKeyAlreadyUploaded(string $phoneNumberId, string $localPublicKey, $accessToken): bool
    {
        $url = 'https://graph.facebook.com/v23.0' . "/$phoneNumberId/whatsapp_business_encryption";

        $response = Http::withToken($accessToken)->get($url);

        if (!$response->successful()) {
            return false;
        }

        $metaKey = trim($response->json('business_public_key'));
        return $metaKey === trim($localPublicKey);
    }

    protected function uploadBusinessPublicKeyToMeta(string $phoneNumberId, string $publicKey, $accessToken): void
    {
        $url =  'https://graph.facebook.com/v23.0' . "/$phoneNumberId/whatsapp_business_encryption";

        $response = Http::withToken($accessToken)
            ->asForm()
            ->post($url, ['business_public_key' => $publicKey]);

        if (!$response->successful()) {
            throw new Exception("Failed to upload business public key: " . $response->body());
        }
    }


    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function ensureFlowHasEndpointURI(Channel $channel, string $flowId, $accessToken): void
    {
        // Resolve base URI depending on environment
        $endpointBase = (env('APP_ENV') == 'local' || env('APP_ENV') == 'development')
            ? 'https://dev-api.dreams.sa/v1.0'
            : 'https://api.dreams.sa/v1.0';

        // ✅ NEW correct path
        $endpointUri = rtrim($endpointBase, '/') . "/flows/{$flowId}/webhook";

        $appID = (string) env('WHATSAPP_APP_ID');

        $url = "https://graph.facebook.com/v23.0/{$flowId}";

        $flowKey = $this->generateOrFetchChannelFlowKey($channel);

        $phoneNumberId = $channel->whatsappConfiguration?->primary_whatsapp_phone_number_id;

        if (!$this->isBusinessPublicKeyAlreadyUploaded($phoneNumberId, $flowKey->public_key, $accessToken)) {
            $this->uploadBusinessPublicKeyToMeta($phoneNumberId, $flowKey->public_key, $accessToken);
        }

        $response = Http::withToken($accessToken)
            ->post($url, [
                'endpoint_uri' => $endpointUri,
                'application_id' => $appID,
            ]);

        if (!$response->successful()) {
            throw new Exception('Failed to update flow with endpoint_uri: ' . $response->body());
        }
    }

    public function updateFlowEndpointURI(Channel $channel, string $flowId, $accessToken): bool
    {
        // Resolve base URI depending on environment
        $endpointBase = (env('APP_ENV') == 'local' || env('APP_ENV') == 'development')
            ? 'https://dev-api.dreams.sa/v1.0'
            : 'https://api.dreams.sa/v1.0';

        // ✅ NEW correct path
        $endpointUri = rtrim($endpointBase, '/') . "/flows/{$flowId}/webhook";


        $url = "https://graph.facebook.com/v23.0/{$flowId}";


        $response = Http::withToken($accessToken)
            ->post($url, [
                'endpoint_uri' => $endpointUri,
            ]);

        if (!$response->successful()) {
            throw new Exception('Failed to update flow with endpoint_uri: ' . $response->body());
        }

        return $response->successful();
    }
}
