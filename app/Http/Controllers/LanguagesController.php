<?php

namespace App\Http\Controllers;

use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;

class LanguagesController extends BaseApiController
{
    protected TranslationService $translationService;

    public function __construct(TranslationService $translationService)
    {
        $this->translationService = $translationService;
    }

    /**
     * Get all supported languages for translation.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $languages = $this->translationService->getSupportedLanguages();

        // Format as array of objects for easier frontend consumption
        $formattedLanguages = [];
        foreach ($languages as $code => $name) {
            $formattedLanguages[] = [
                'code' => $code,
                'name' => $name,
            ];
        }

        return $this->response(data: $formattedLanguages);
    }
}
