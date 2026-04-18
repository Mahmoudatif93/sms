<?php

namespace App\Services;

use App\Models\MessageTranslation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Traits\Translation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    use Translation;

    /**
     * Check if auto translation is enabled for an organization.
     *
     * @param Organization $organization
     * @return bool
     */
    public function isEnabledForOrganization(Organization $organization): bool
    {
        return $organization->isAutoTranslationEnabled();
    }

    /**
     * Check if auto translation is enabled for a user within an organization.
     * Returns true only if both organization and user have translation enabled.
     *
     * @param User $user
     * @param Organization $organization
     * @return bool
     */
    public function isEnabledForUser(User $user, Organization $organization): bool
    {
        // First check if organization has translation enabled
        if (!$this->isEnabledForOrganization($organization)) {
            return false;
        }

        // Get the user's membership in this organization
        $membership = $this->getUserMembership($user, $organization);

        if (!$membership) {
            return false;
        }

        return $membership->isAutoTranslationEnabled();
    }

    /**
     * Get the target language for translation based on user preferences.
     * Returns null if the source language is already in the user's preferred languages.
     *
     * @param User $user
     * @param Organization $organization
     * @param string $sourceLanguage
     * @return string|null
     */
    public function getTargetLanguageForUser(User $user, Organization $organization, string $sourceLanguage): ?string
    {
        $membership = $this->getUserMembership($user, $organization);

        if (!$membership) {
            return null;
        }

        $preferredLanguages = $membership->getPreferredLanguages();

        // If no preferred languages set, no translation needed
        if (empty($preferredLanguages)) {
            return null;
        }

        // If source language is in preferred languages, no translation needed
        if ($membership->isPreferredLanguage($sourceLanguage)) {
            return null;
        }

        // Return the first preferred language as target
        return $membership->getFirstPreferredLanguage();
    }

    /**
     * Get an existing translation or create a new one.
     *
     * @param Model $message
     * @param string $targetLanguage
     * @param string|null $sourceLanguage
     * @return MessageTranslation|null
     */
    public function getOrCreateTranslation(Model $message, string $targetLanguage, ?string $sourceLanguage = null): ?MessageTranslation
    {
        // Check if translation already exists
        $existingTranslation = MessageTranslation::getTranslation($message, $targetLanguage);

        if ($existingTranslation) {
            return $existingTranslation;
        }

        // Get the text content from the message
        $textContent = $this->extractTextFromMessage($message);

        if (empty($textContent)) {
            return null;
        }

        // Translate the text
        return $this->translateAndStore($message, $textContent, $targetLanguage, $sourceLanguage);
    }

    /**
     * Translate a message and store the result.
     *
     * @param Model $message
     * @param string $text
     * @param string $targetLanguage
     * @param string|null $sourceLanguage
     * @return MessageTranslation|null
     */
    public function translateAndStore(Model $message, string $text, string $targetLanguage, ?string $sourceLanguage = null): ?MessageTranslation
    {
        try {
            $result = $this->translateText([$text], $targetLanguage);
            if (isset($result['error'])) {
                Log::error('Translation API error', [
                    'error' => $result['error'],
                    'message_id' => $message->getKey(),
                    'target_language' => $targetLanguage,
                ]);
                return null;
            }

            $translatedText = $result['translations'][0] ?? null;

            if (empty($translatedText)) {
                return null;
            }

            return MessageTranslation::create([
                'messageable_id' => $message->getKey(),
                'messageable_type' => \get_class($message),
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'translated_text' => $translatedText,
            ]);
        } catch (\Exception $e) {
            Log::error('Translation failed', [
                'message_id' => $message->getKey(),
                'target_language' => $targetLanguage,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get the user's membership record in an organization.
     *
     * @param User $user
     * @param Organization $organization
     * @return OrganizationUser|null
     */
    protected function getUserMembership(User $user, Organization $organization): ?OrganizationUser
    {
        return OrganizationUser::where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Extract text content from a message model.
     *
     * @param Model $message
     * @return string|null
     */
    protected function extractTextFromMessage(Model $message): ?string
    {
        // Try common text field names
        $textFields = ['body', 'content', 'text', 'message'];

        foreach ($textFields as $field) {
            if (isset($message->$field) && !empty($message->$field)) {
                return $message->$field;
            }
        }

        return null;
    }

    /**
     * Get all supported languages from config.
     *
     * @return array
     */
    public function getSupportedLanguages(): array
    {
        return config('translation.supported_languages', []);
    }

    /**
     * Check if a language code is valid/supported.
     *
     * @param string $languageCode
     * @return bool
     */
    public function isValidLanguage(string $languageCode): bool
    {
        $supportedLanguages = $this->getSupportedLanguages();
        return \array_key_exists($languageCode, $supportedLanguages);
    }

    /**
     * Validate an array of language codes.
     *
     * @param array $languageCodes
     * @return array Only valid language codes
     */
    public function filterValidLanguages(array $languageCodes): array
    {
        return \array_filter($languageCodes, fn($code) => $this->isValidLanguage($code));
    }

    /**
     * Get translation for a message if needed based on user preferences.
     *
     * @param Model $message
     * @param User $user
     * @param Organization $organization
     * @param string|null $sourceLanguage
     * @return string|null The translated text or null if no translation needed/available
     */
    public function getTranslationForUser(Model $message, User $user, Organization $organization, ?string $sourceLanguage = null): ?string
    {
        // Check if translation is enabled for this user
        if (!$this->isEnabledForUser($user, $organization)) {
            return null;
        }

        // Detect source language if not provided
        if (!$sourceLanguage) {
            $sourceLanguage = $this->detectLanguage($message);
        }

        if (!$sourceLanguage) {
            return null;
        }

        // Get target language based on user preferences
        $targetLanguage = $this->getTargetLanguageForUser($user, $organization, $sourceLanguage);

        if (!$targetLanguage) {
            return null;
        }

        // Get or create translation
        $translation = $this->getOrCreateTranslation($message, $targetLanguage, $sourceLanguage);

        return $translation?->translated_text;
    }

    /**
     * Detect the language of a message (placeholder - can be enhanced).
     *
     * @param Model $message
     * @return string|null
     */
    protected function detectLanguage(Model $message): ?string
    {
        // This is a placeholder - you may want to implement actual language detection
        // For now, return null to indicate unknown language
        return null;
    }

    /**
     * Translate text to a target language.
     *
     * @param string $text
     * @param string $targetLanguage
     * @return string|null
     */
    public function translate(string $text, string $targetLanguage): ?string
    {
        try {
            $result = $this->translateText([$text], $targetLanguage);

            if (isset($result['error'])) {
                Log::error('Translation API error', [
                    'error' => $result['error'],
                    'target_language' => $targetLanguage,
                ]);
                return null;
            }

            return $result['translations'][0] ?? null;
        } catch (\Exception $e) {
            Log::error('Translation failed', [
                'target_language' => $targetLanguage,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
