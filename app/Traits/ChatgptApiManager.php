<?php

namespace App\Traits;

use App\Constants\Chatgpt;
use App\Constants\ChatgptPricing;
use Exception;
use Http;
use Illuminate\Http\Client\ConnectionException;

trait ChatgptApiManager
{
    use WhatsappWalletManager;
    /**
     * Send a request to the OpenAI Responses API
     *
     * @param array $payload
     * @return array
     * @throws ConnectionException|Exception
     */
    protected function chatgptRequest(array $payload): array
    {
        $token = Chatgpt::API_KEY;

        $response = Http::withToken($token)
            ->acceptJson()
            ->post('https://api.openai.com/v1/responses', $payload);

        if (!$response->successful()) {
            throw new Exception("ChatGPT API Error: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Normalize a ChatGPT API response into {text, usage, cost_usd}.
     *
     * @param array $response Raw response from OpenAI
     * @param array $payload  The payload you sent (to fallback model name if missing)
     * @return array
     */
    protected function parseChatgptResponse(array $response, array $payload): array
    {
        $text = $response['output'][0]['content'][0]['text'] ?? '';
        $usage = $response['usage'] ?? [];

        $model = $response['model'] ?? ($payload['model'] ?? null);
        $cost = ChatgptPricing::calculateCost($usage, $model);

        return [
            'text' => $text,
            'usage' => $usage,
            'cost_usd' => $cost,
        ];
    }

    protected function runAiAndCharge($workspace, array $payload, string $feature = 'ai_usage',$message = null): string
    {
        try {
            // 1. Call API
            $response = $this->chatgptRequest($payload);

            // 2. Parse
            $result = $this->parseChatgptResponse($response, $payload);

            $text = $result['text'] ?? '';
            if ($text === '') {
                return ''; // no text returned
            }

            // 3. Charge
            $transaction = $this->chargeAiUsage($workspace, $result, $feature, $message);

            if (!$transaction) {
                // not enough funds or no wallet
                return 'Something went wrong.';
            }

            return $text;
        } catch (\Throwable $e) {
            // swallow errors and return an empty string
            return $e->getMessage();
        }
    }

}
