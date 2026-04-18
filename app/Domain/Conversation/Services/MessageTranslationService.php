<?php

namespace App\Domain\Conversation\Services;

use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\LanguageDetectionService;
use App\Traits\WhatsappWalletManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageTranslationService
{
    use WhatsappWalletManager;

    public function __construct(
        private LanguageDetectionService $languageDetectionService
    ) {}

    /**
     * Translate outgoing message to conversation's detected language
     */
    public function translateOutgoingMessage(Request $request, Conversation $conversation): void
    {
        try {
            // Only translate text messages
            $messageType = $request->input('type');
            if ($messageType !== 'text') {
                return;
            }

            // Get the conversation's detected language
            $targetLanguage = $conversation->detected_language;
            if (!$targetLanguage) {
                return;
            }

            // Get the text body
            $textBody = $request->input('text.body');
            if (!$textBody) {
                return;
            }

            // Check if organization has auto-translation enabled
            $organization = $conversation->workspace->organization ?? null;
            if (!$organization || !$organization->isAutoTranslationEnabled()) {
                return;
            }

            // Detect the language of the outgoing message
            $sourceLanguage = $this->languageDetectionService->detect($textBody);

            // Skip translation if source language is the same as target language
            if ($sourceLanguage && $sourceLanguage === $targetLanguage) {
                return;
            }

            // Prepare wallet transaction for translation billing
            $transaction = $this->prepareWalletTransactionForTranslation($conversation->workspace);

            // Translate the message
            $translatedText = $this->translate($textBody, $targetLanguage, $conversation->workspace);

            if ($translatedText && $translatedText !== $textBody) {
                // Update the request with translated text
                $request->merge([
                    'text' => [
                        'body' => $translatedText,
                        'preview_url' => $request->input('text.preview_url'),
                    ],
                    'original_text' => $textBody,
                    'translated_to' => $targetLanguage,
                    'translation_transaction_id' => $transaction?->id,
                ]);
            } else {
                // Translation not needed or failed, release funds if reserved
                if ($transaction) {
                    $this->releaseFunds($transaction);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to translate outgoing message', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Translate text to target language
     */
    public function translate(string $text, string $targetLanguage, Workspace $workspace): ?string
    {
        // Integration with translation service (OpenAI, Google Translate, etc.)
        // This is a placeholder - implement based on your translation service
        try {
            // Use existing AI/translation service
            // Return translated text
            return null;
        } catch (\Exception $e) {
            Log::error('Translation failed', [
                'target_language' => $targetLanguage,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Detect language of text
     */
    public function detectLanguage(string $text): ?string
    {
        return $this->languageDetectionService->detect($text);
    }
}
