<?php

namespace App\Http\Controllers;

use App\Http\Slack;
use App\Models\ChannelFlowKey;
use App\Models\WhatsappFlow;
use Illuminate\Http\Request;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\RSA;
use phpseclib3\Exception\NoKeyLoadedException;
use Response;
use RuntimeException;
use Throwable;

class FlowWebhookController extends BaseApiController
{
    public function handle(Request $request, $flowID)
    {

//        Slack::Log(json_encode($request->all()), __FILE__, __LINE__);

        $flow = WhatsappFlow::where('id', $flowID)->first();
        $channel = $flow->channel;
        $key = ChannelFlowKey::whereChannelId($channel->id)->first();


        try {
            $decrypted = $this->decryptWhatsappFlowRequest($request->all(), $key->private_key, $channel->id);
        } catch (Throwable $e) {

            // Meta expects a 502 or 503 to retry and possibly re-fetch the public key
            return response('Decryption failed', 502);
        }

        $decryptedBody = $decrypted['decryptedBody'];
        $aesKey = $decrypted['aesKeyBuffer'];
        $initialVector = $decrypted['initialVectorBuffer'];


        $responsePayload = match ($decryptedBody['action']) {
            'ping' => [
                'data' => ['status' => 'active']
            ],
            'INIT', 'BACK', 'data_exchange' => $this->handleFlowDataExchange($decryptedBody),
            'error_notification' => [
                'data' => ['acknowledged' => true]
            ],
            default => ['screen' => 'error', 'data' => ['error_message' => 'Unhandled action']]
        };


        $encrypted = $this->encryptWhatsappFlowResponse($responsePayload, $aesKey, $initialVector);


        return response($encrypted, 200)->header('Content-Type', 'text/plain');

    }


    /**
     * Decrypt WhatsApp Flow request with channel-encrypted private key.
     */
    protected function decryptWhatsappFlowRequest(array $payload, string $privatePem, string $channelId): array
    {
        $encryptedAesKey = base64_decode($payload['encrypted_aes_key']);
        $encryptedFlowData = base64_decode($payload['encrypted_flow_data']);
        $initialVector = base64_decode($payload['initial_vector']);

        // Load and decrypt RSA private key using the channel ID as passphrase
        try {
            $rsa = RSA::load($privatePem, $channelId)
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256');
        } catch (NoKeyLoadedException $e) {
            throw new RuntimeException('Unable to load RSA private key: ' . $e->getMessage());
        }

        // Decrypt AES key
        $decryptedAesKey = $rsa->decrypt($encryptedAesKey);
        if (!$decryptedAesKey) {
            throw new RuntimeException('Failed to decrypt AES key.');
        }

        // Separate encrypted data from tag (last 16 bytes)
        $tagLength = 16;
        $ciphertext = substr($encryptedFlowData, 0, -$tagLength);
        $tag = substr($encryptedFlowData, -$tagLength);

        // Decrypt flow payload
        $aes = new AES('gcm');
        $aes->setKey($decryptedAesKey);
        $aes->setNonce($initialVector);
        $aes->setTag($tag);

        $decrypted = $aes->decrypt($ciphertext);
        if (!$decrypted) {
            throw new RuntimeException('Failed to decrypt flow payload.');
        }

        return [
            'decryptedBody' => json_decode($decrypted, true),
            'aesKeyBuffer' => $decryptedAesKey,
            'initialVectorBuffer' => $initialVector,
        ];
    }

    protected function encryptWhatsappFlowResponse(array $data, string $aesKey, string $originalIV): string
    {
        $invertedIV = $originalIV ^ str_repeat("\xFF", strlen($originalIV)); // Flip bits for IV

        $aes = new AES('gcm');
        $aes->setKey($aesKey);
        $aes->setNonce($invertedIV);

        $plaintext = json_encode($data);

        $ciphertext = $aes->encrypt($plaintext);
        $tag = $aes->getTag();

        return base64_encode($ciphertext . $tag);
    }
    private function handleFlowDataExchange(array $body): array
    {
        $screen = $body['screen'] ?? 'start';
        $data = $body['data'] ?? [];
        $flowToken = $body['flow_token'] ?? null;

       return $this->respondWithSuccess($flowToken);
    }

    protected function respondWithSuccess(string $flowToken, array $params = []): array
    {
        return [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => array_merge(['flow_token' => $flowToken], $params)
                ]
            ]
        ];
    }

    protected function respondWithError(string $screen, string $message): array
    {
        return [
            'screen' => $screen,
            'data' => [
                'error_message' => $message
            ]
        ];
    }


    protected function respondWithNextScreen(string $screen, array $data = []): array
    {
        return [
            'screen' => $screen,
            'data' => $data
        ];
    }


}
