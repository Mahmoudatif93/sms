<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LanguageDetectionService
{
    /**
     * Detect the language of a given text.
     *
     * @param string $text
     * @return string|null Language code (e.g., 'ar', 'en', 'fr') or null if detection failed
     */
    public function detect(string $text): ?string
    {
        if (empty(trim($text))) {
            return null;
        }

        // First try character-based detection for scripts with unique characters
        $scriptBasedLanguage = $this->detectByScript($text);
        if ($scriptBasedLanguage) {
            return $scriptBasedLanguage;
        }

        // For Latin-based scripts, try API detection or default to 'en'
        return $this->detectLatinLanguage($text);
    }

    /**
     * Detect language based on script/character ranges.
     *
     * @param string $text
     * @return string|null
     */
    protected function detectByScript(string $text): ?string
    {
        // Count characters in different Unicode ranges
        $arabicCount = preg_match_all('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
        $hebrewCount = preg_match_all('/[\x{0590}-\x{05FF}]/u', $text);
        $chineseCount = preg_match_all('/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]/u', $text);
        $japaneseCount = preg_match_all('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $text);
        $koreanCount = preg_match_all('/[\x{AC00}-\x{D7AF}\x{1100}-\x{11FF}]/u', $text);
        $cyrillicCount = preg_match_all('/[\x{0400}-\x{04FF}]/u', $text);
        $thaiCount = preg_match_all('/[\x{0E00}-\x{0E7F}]/u', $text);
        $hindiCount = preg_match_all('/[\x{0900}-\x{097F}]/u', $text);
        $urduCount = preg_match_all('/[\x{0600}-\x{06FF}]/u', $text); // Urdu uses Arabic script
        $turkishSpecific = preg_match_all('/[İıĞğŞşÇçÖöÜü]/u', $text);

        $textLength = mb_strlen($text);
        $threshold = 0.3; // At least 30% of characters should be from the detected script

        // Check Arabic (includes Urdu which uses Arabic script)
        if ($arabicCount > 0 && ($arabicCount / $textLength) > $threshold) {
            return 'ar';
        }

        // Check Chinese
        if ($chineseCount > 0 && ($chineseCount / $textLength) > $threshold) {
            return 'zh';
        }

        // Check Japanese (Hiragana/Katakana)
        if ($japaneseCount > 0 && ($japaneseCount / $textLength) > $threshold) {
            return 'ja';
        }

        // Check Korean
        if ($koreanCount > 0 && ($koreanCount / $textLength) > $threshold) {
            return 'ko';
        }

        // Check Cyrillic (Russian)
        if ($cyrillicCount > 0 && ($cyrillicCount / $textLength) > $threshold) {
            return 'ru';
        }

        // Check Thai
        if ($thaiCount > 0 && ($thaiCount / $textLength) > $threshold) {
            return 'th';
        }

        // Check Hindi (Devanagari)
        if ($hindiCount > 0 && ($hindiCount / $textLength) > $threshold) {
            return 'hi';
        }

        // Check Hebrew
        if ($hebrewCount > 0 && ($hebrewCount / $textLength) > $threshold) {
            return 'he';
        }

        // Check Turkish-specific characters
        if ($turkishSpecific > 0) {
            return 'tr';
        }

        return null;
    }

    /**
     * Detect language for Latin-script texts.
     *
     * @param string $text
     * @return string
     */
    protected function detectLatinLanguage(string $text): string
    {
        // Common word patterns for different languages
        $patterns = [
            'es' => '/\b(el|la|los|las|es|son|está|están|que|de|en|con|para|por|como|pero|más|muy|también|sí|no)\b/iu',
            'fr' => '/\b(le|la|les|est|sont|que|de|en|avec|pour|par|comme|mais|plus|très|aussi|oui|non|je|tu|il|nous|vous|ils)\b/iu',
            'de' => '/\b(der|die|das|ist|sind|und|oder|aber|nicht|auch|für|mit|von|zu|bei|nach|über|unter|ich|du|er|wir|ihr|sie)\b/iu',
            'pt' => '/\b(o|a|os|as|é|são|que|de|em|com|para|por|como|mas|mais|muito|também|sim|não|eu|tu|ele|nós|vós|eles)\b/iu',
            'it' => '/\b(il|la|i|le|è|sono|che|di|in|con|per|come|ma|più|molto|anche|sì|no|io|tu|lui|noi|voi|loro)\b/iu',
            'nl' => '/\b(de|het|een|is|zijn|dat|van|in|met|voor|door|als|maar|meer|zeer|ook|ja|nee|ik|jij|hij|wij|jullie|zij)\b/iu',
            'en' => '/\b(the|is|are|was|were|that|this|with|for|from|have|has|but|not|you|they|she|her|his|its|our|your|their|what|which|who|how|why|when|where)\b/iu',
        ];

        $scores = [];
        foreach ($patterns as $lang => $pattern) {
            $matches = preg_match_all($pattern, $text);
            $scores[$lang] = $matches ?: 0;
        }

        // Return language with highest score, default to 'en' if no matches
        arsort($scores);
        $topLang = array_key_first($scores);

        return ($scores[$topLang] > 0) ? $topLang : 'en';
    }

    /**
     * Detect language using external API (fallback option).
     *
     * @param string $text
     * @return string|null
     */
    public function detectWithApi(string $text): ?string
    {
        try {
            $apiUrl = 'https://ai.arabsstock.com/langfix/detect';
            $response = Http::asForm()->post($apiUrl, [
                'key_token' => env('TRANSLATE_API_KEY'),
                'text' => $text,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return $result['language'] ?? null;
            }
        } catch (\Exception $e) {
            Log::warning('Language detection API failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get the language name from code.
     *
     * @param string $code
     * @return string
     */
    public function getLanguageName(string $code): string
    {
        $languages = config('translation.supported_languages', []);
        return $languages[$code] ?? $code;
    }

    /**
     * Check if a language code is supported.
     *
     * @param string $code
     * @return bool
     */
    public function isSupported(string $code): bool
    {
        $languages = config('translation.supported_languages', []);
        return \array_key_exists($code, $languages);
    }
}
