<?php

namespace App\Traits;
use Http;
trait Translation
{
    public function translateText(array $textArray, string $targetLanguage): array
    {
        $apiURL = 'https://ai.arabsstock.com/langfix/translate';

        $postInput = [
            'key_token' => env('TRANSLATE_API_KEY'),  // Ensure this is set in .env
            'target_language' => $targetLanguage,
            'texts' => json_encode($textArray),
        ];

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $response = Http::withHeaders($headers)
            ->asForm()
            ->post($apiURL, $postInput);

        if (!$response->successful()) {
            return [
                'error' => 'Translation API request failed',
                'status' => $response->status(),
                'details' => $response->json(),
            ];
        }

        $result = $response->json();

        return [
            'translations' => json_decode($result['translations'][0][0])
        ];
    }

    protected function convertArrayToString($result): array
    {
        return array_map(function ($r) {
            return $r[0];
        }, $result);
    }
}
