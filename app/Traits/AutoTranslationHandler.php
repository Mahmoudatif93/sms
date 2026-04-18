<?php

namespace App\Traits;

use App\Models\Conversation;
use App\Models\Organization;
use App\Models\WhatsappMessage;
use App\Services\LanguageDetectionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

trait AutoTranslationHandler
{
    /**
     * Process auto-translation for an incoming message.
     * Detects the language and updates the conversation's detected language.
     *
     * @param Model $message The message model (WhatsappMessage, LiveChatMessage, etc.)
     * @param Conversation $conversation
     * @param string|null $textContent The text content of the message
     * @return void
     */
    protected function processAutoTranslation(Model $message, Conversation $conversation, ?string $textContent): void
    {
        if (empty($textContent)) {
            return;
        }

        try {
            // Get language detection service
            $languageDetectionService = app(LanguageDetectionService::class);

            // Detect language
            $detectedLanguage = $languageDetectionService->detect($textContent);
            if (!$detectedLanguage) {
                Log::debug('Could not detect language for message', ['message_id' => $message->getKey()]);
                return;
            }

            // Update conversation's detected language
            $conversation->updateDetectedLanguage($detectedLanguage);

        } catch (\Exception $e) {
            Log::error('Auto-translation failed', [
                'message_id' => $message->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get organization from conversation.
     *
     * @param Conversation $conversation
     * @return Organization|null
     */
    protected function getOrganizationFromConversation(Conversation $conversation): ?Organization
    {
        // Try to get organization through workspace
        $workspace = $conversation->workspace;

        if ($workspace) {
            return $workspace->organization;
        }

        // Try to get through channel
        $channel = $conversation->channel;

        if ($channel) {
            $workspace = $channel->workspaces()->first();
            return $workspace?->organization;
        }

        return null;
    }

    /**
     * Extract text content from a WhatsApp message.
     *
     * @param WhatsappMessage $message
     * @return string|null
     */
    protected function extractTextFromWhatsappMessage(WhatsappMessage $message): ?string
    {
        $messageable = $message->messageable;

        if (!$messageable) {
            return null;
        }

        // Check common text fields
        if (isset($messageable->body)) {
            return $messageable->body;
        }

        if (isset($messageable->caption)) {
            return $messageable->caption;
        }

        if (isset($messageable->text)) {
            return $messageable->text;
        }

        return null;
    }
}
