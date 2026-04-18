<?php

namespace App\Http\Controllers;
use \Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use App\Traits\Translation;
use Http;
class TranslateController extends BaseApiController
{
    use Translation;
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }

    
    /**
     * @OA\Post(
     *     path="/api/ai/translate",
     *     tags={"ai"},
     *     summary="Translate text to a target language",
     *     description="Translates the given text to the specified target language",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"text", "target_language"},
     *             @OA\Property(property="text", type="string", example="Hello, World!"),
     *             @OA\Property(property="target_language", type="string", example="es")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Translation successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success translate text."),
     *             @OA\Property(property="data", type="string", example="Hola, Mundo!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed."),
     *             @OA\Property(property="errors", type="object", example={"text": "The text field is required."})
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to translate text",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to translate text."),
     *             @OA\Property(property="data", type="null", example=null)
     *         )
     *     ),
     *     security={{"apiAuth": {}}}
     * )
     */
    public function translate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string',
            'target_language' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Validation failed.', $validator->errors(), 422);
        }
        $text = $request->input('text');
        $targetLanguage = $request->input('target_language');
        $translatedText =  $this->translateText($text,$targetLanguage);
        //$this->translateText($text, $targetLanguage);
        if ($translatedText) {
            return $this->response(true, 'success translate text.', $translatedText['translations'][0]);
        } else {
            return $this->response(false, 'Failed to translate text.', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="api/ai/improve-writing",
     *     summary="Improve writing by fixing transcription",
     *     description="This endpoint is used to improve writing by fixing transcription errors.",
     *     tags={"ai"},
     *     @OA\RequestBody(
     *         description="Text to be fixed",
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="text", type="string", example="Hello, World!"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transcription fixed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success translate text."),
     *             @OA\Property(property="data", type="string", example="Hello, World!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed."),
     *             @OA\Property(property="errors", type="object", example={"text": "The text field is required."})
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to fix transcription",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to fix transcription."),
     *             @OA\Property(property="data", type="null", example=null)
     *         )
     *     ),
     *     security={{"apiAuth": {}}}
     * )
     */
    public function fixTranslate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Validation failed.', $validator->errors(), 422);
        }
        $text = $request->input('text');


        $apiURL = 'https://ai.arabsstock.com/langfix/fix-transcription';
        $text = $request->input('text');
        $postInput = [
            'key_token' => env('TRANSLATE_API_KEY'),
            'transcription' => $text,
        ];

        $headers = [
            'accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];

        $response = Http::withHeaders($headers)->asForm()->post($apiURL, $postInput);
        if ($response->successful()) {
            return $this->response(true, 'success translate text.', $response->json());

        } else {
            return $this->response(false, 'Failed to translate text.', null, 500);

        }
    }
   
}
