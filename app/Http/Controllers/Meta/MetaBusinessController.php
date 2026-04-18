<?php

namespace App\Http\Controllers\Meta;

use App\Constants\Meta;
use App\Http\Controllers\Controller;
use App\Traits\RSAKeyGenerator;
use Exception;
use Http;
use Illuminate\Http\Client\ConnectionException;

class MetaBusinessController extends Controller
{

    use RSAKeyGenerator;

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function setBusinessPublicKey($phoneNumberId): void
    {
        $businessKey = $this->generateRsaKeyPair($phoneNumberId);

        if (!isset($businessKey['public_key'])) {
            throw new Exception('Public key not found in generated key pair.');
        }

        $businessPublicKey = $businessKey['public_key'];
        $accessToken = Meta::ACCESS_TOKEN;
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $apiVersion = env('FACEBOOK_GRAPH_API_VERSION');
        $url = "{$baseUrl}/{$apiVersion}/{$phoneNumberId}/whatsapp_business_encryption";


        $response = Http::withToken($accessToken)->asForm()->post($url, [
            'business_public_key' => $businessPublicKey
        ]);

        if (!$response->successful()) {
            throw new Exception('Failed to set business public key: ' . $response->body());
        }

    }

}
