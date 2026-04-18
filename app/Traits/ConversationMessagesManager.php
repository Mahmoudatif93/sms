<?php

namespace App\Traits;

use App\Http\Responses\ConversationMessage;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\MessageBilling;
use App\Models\MessageTranslation;
use App\Models\OrganizationWhatsappExtra;
use App\Models\Service;
use App\Traits\ConversationAIFeatures;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

trait ConversationMessagesManager
{
    use ConversationAIFeatures;
    /**
     * Load and format messages with eager loading and sorting.
     *
     * @param Conversation $conversation
     * @param array $options
     * @return array
     */
    public function loadFormattedMessages(Conversation $conversation, array $options = []): array
    {
        $translationOptions = [
            'translate' => $options['translate'] ?? false,
            'lang' => $options['lang'] ?? 'en'
        ];

        $messages = $this->getMessagesWithEagerLoading($conversation, $options);
        $formatted = [];
        // Check if translation is enabled and organization allows it
        $shouldTranslate = $translationOptions['translate'] && $this->canTranslate($conversation);
        foreach ($messages as $message) {
            $formattedMessage = (new ConversationMessage($message, $conversation->platform, $translationOptions))->toArray();

            // Translate message content if enabled
            if ($shouldTranslate && !empty($translationOptions['lang'])) {
                $formattedMessage = $this->translateMessageContent(
                    $formattedMessage,
                    $message,
                    $conversation,
                    $translationOptions['lang']
                );
            }



            $formatted[] = $formattedMessage;
        }

        usort($formatted, fn($a, $b) => strtotime($a['created_at']) <=> strtotime($b['created_at']));

        return $formatted;
    }

    /**
     * Check if translation is allowed for the conversation's organization.
     *
     * @param Conversation $conversation
     * @return bool
     */
    private function canTranslate(Conversation $conversation): bool
    {
        $organization = $conversation->workspace->organization ?? null;

        if (!$organization) {
            return false;
        }
        return $organization->isAutoTranslationEnabled();
    }

    /**
     * Translate message content to target language with billing.
     *
     * @param array $formattedMessage
     * @param mixed $message
     * @param Conversation $conversation
     * @param string $targetLang
     * @return array
     */
    private function translateMessageContent(array $formattedMessage, $message, Conversation $conversation, string $targetLang): array
    {

        try {
            // Check if translation already exists in database
            $existingTranslation = MessageTranslation::getTranslation($message, $targetLang);
            if ($existingTranslation) {
                return $formattedMessage;
            }

            // Extract text from content
            $textToTranslate = $this->extractTextForTranslation($formattedMessage['content'] ?? []);

            if (empty($textToTranslate)) {
                return $formattedMessage;
            }

            // Detect the language of the message text
            $languageDetectionService = app(\App\Services\LanguageDetectionService::class);
            $sourceLanguage = $languageDetectionService->detect($textToTranslate);

            // Skip if source language is the same as target language
            if ($sourceLanguage && $sourceLanguage === $targetLang) {
                return $formattedMessage;
            }

            $workspace = $conversation->workspace;
            $organization = $workspace->organization;
            // Get translation quota for billing
            $extra = OrganizationWhatsappExtra::where('organization_id', $organization->id)->first();
            $translationQuota = $extra->translation_quota ?? 0;
            // Translate using ConversationAIFeatures trait method
            $translatedText = $this->translateText($workspace, $textToTranslate, $targetLang, $sourceLanguage, $message);

            if (!empty($translatedText)) {
                // Store translation in database
                MessageTranslation::create([
                    'messageable_id' => $message->id,
                    'messageable_type' => get_class($message),
                    'source_language' => $sourceLanguage,
                    'target_language' => $targetLang,
                    'translated_text' => $translatedText,
                ]);

                // Add translated content to the message
                $formattedMessage['translations'] = [
                    [
                    'translated_text' => $translatedText,
                    'target_language' => $targetLang,
                    ]
                ];
                // Create billing record if quota is set
                // if ($translationQuota > 0) {
                //     $this->createMessageTranslationBilling($message, $organization, $translationQuota);
                // }
            }

            return $formattedMessage;
        } catch (\Exception $e) {
            Log::error('Failed to translate message content', [
                'message_id' => $formattedMessage['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            return $formattedMessage;
        }
    }

    /**
     * Extract text from message content for translation.
     *
     * @param array $content
     * @return string|null
     */
    private function extractTextForTranslation(array $content): ?string
    {
        // Try different text fields
        if (!empty($content['text'])) {
            return $content['text'];
        }

        if (!empty($content['formatted_body'])) {
            return $content['formatted_body'];
        }

        if (!empty($content['body'])) {
            return $content['body'];
        }

        if (!empty($content['caption'])) {
            return $content['caption'];
        }

        return null;
    }

    /**
     * Create billing record for message translation.
     *
     * @param mixed $message
     * @param mixed $organization
     * @param float $cost
     * @return void
     */
    private function createMessageTranslationBilling($message, $organization, float $cost): void
    {
        try {
            // Get wallet
            $wallet = $this->getObjectWallet(
                $organization,
                Service::where('name', \App\Enums\Service::OTHER)->value('id')
            );

            if (!$wallet || !$wallet->hasSufficientFunds($cost)) {
                return;
            }

            // Deduct from wallet and confirm immediately
            $transaction = $this->reserveFunds($wallet, $cost, [
                'type' => 'translation_view',
                'message_id' => $message->id,
            ], 'Translation view charge');

            if ($transaction) {
                $this->confirmFunds($transaction);

                // Create billing record
                MessageBilling::create([
                    'messageable_id' => $message->id,
                    'messageable_type' => get_class($message),
                    'type' => MessageBilling::TYPE_TRANSLATION,
                    'cost' => $cost,
                    'is_billed' => true,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create translation billing', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Retrieve messages with proper eager loading and optional pagination.
     *
     * @param Conversation $conversation
     * @param array $options
     * @return Collection
     */
    public function getMessagesWithEagerLoading(Conversation $conversation, array $options = []): Collection
    {
        $limit = $options['limit'] ?? 15;
        $lastMessageId = $options['last_message_id'] ?? null;
        $lastMessageCreatedAt = null;

        if ($lastMessageId) {
            $lastMessage = $conversation->messages()->find($lastMessageId);
            $lastMessageCreatedAt = $lastMessage?->created_at;
        }
        $query = $conversation->messages();

        match ($conversation->platform) {
            Channel::LIVECHAT_PLATFORM => $query->withMessageableRelations(),
            Channel::MESSENGER_PLATFORM => $query->withMessageableRelations(),
            Channel::WHATSAPP_PLATFORM => $query->withMessageableRelations(),
            default => null
        };

        if ($lastMessageCreatedAt) {
            $query->where('created_at', '<', \Carbon\Carbon::createFromTimestamp($lastMessageCreatedAt)->addHours(3));
        }

        return $query->orderBy('created_at', 'desc')->take($limit)->get();
    }
}
