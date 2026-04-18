<?php

namespace App\Domain\Chatbot\Services;

use App\Domain\Chatbot\DTOs\ChatbotResponseDTO;
use App\Domain\Chatbot\Events\BotResponseSent;
use App\Domain\Chatbot\Events\HandoffInitiated;
use App\Domain\Chatbot\Repositories\ChatbotRepositoryInterface;
use App\Models\ChatbotSettings;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class ChatbotService
{
    public function __construct(
        private ChatbotRepositoryInterface $repository,
        private KnowledgeSearchService $searchService,
        private ChatbotAIService $aiService,
    ) {}

    public function shouldProcess(Conversation $conversation): bool
    {
        // Check if chatbot is enabled for this channel
        $settings = $this->getSettings($conversation->channel_id);

        if (!$settings || !$settings->is_enabled) {
            return false;
        }

        // Check whitelist if enabled
        if (!$this->isContactAllowed($conversation, $settings)) {
            return false;
        }

        // Check if bot is still active for this conversation
        $chatbotConversation = $conversation->chatbotConversation;

        if ($chatbotConversation && !$chatbotConversation->is_bot_active) {
            return false;
        }

        return true;
    }

    private function isContactAllowed(Conversation $conversation, ChatbotSettings $settings): bool
    {
        $contactIdentifier = $conversation->contact?->getPhoneNumberIdentifier();

        return $settings->isContactAllowed($contactIdentifier);
    }

    public function processMessage(Conversation $conversation, string $message): ChatbotResponseDTO
    {
        $settings = $this->getSettings($conversation->channel_id);

        // 1. Check if bot is enabled
        if (!$settings || !$settings->is_enabled) {
            return ChatbotResponseDTO::disabled();
        }

        // 2. Get or create chatbot conversation
        $chatbotConversation = $this->repository->getOrCreateChatbotConversation($conversation);

        // 3. Check if bot is active for this conversation
        if (!$chatbotConversation->is_bot_active) {
            return ChatbotResponseDTO::disabled();
        }

        // 4. Detect language
        $language = $this->searchService->detectLanguage($message);

        // 5. Get knowledge base context (always needed for AI)
        $searchResult = $this->searchService->search($conversation->channel_id, $message, $language);

        // 7. If knowledge search is enabled AND confident match found, respond directly
        if ($settings->use_knowledge_search && $searchResult->isConfident(0.75)) {
            $response = $this->respondFromKnowledge($conversation, $chatbotConversation, $searchResult, $language, $message);

            // Check if this knowledge item requires handoff
            if ($searchResult->requiresHandoff()) {
                $this->handleHandoff($conversation, $chatbotConversation, 'موضوع يتطلب تدخل موظف');
            }
            return $response;
        }

        // 8. Use AI (with knowledge base as context)
        // If use_knowledge_search = false, AI always handles with full knowledge context
        // If use_knowledge_search = true but no confident match, AI handles with search results as context
        $knowledgeContext = $settings->use_knowledge_search
            ? $searchResult->topResults
            : $this->repository->getAllKnowledge($conversation->channel_id);
        $aiResponse = $this->aiService->generateResponse(
            $conversation,
            $message,
            $knowledgeContext,
            $settings,
            $language
        );

        // 9. Handle AI response
        if (!$aiResponse->success) {
            // Increment failed attempts
            $attempts = $chatbotConversation->incrementFailedAttempts();

            if ($chatbotConversation->shouldHandoff($settings->handoff_threshold)) {
                return $this->handleHandoff($conversation, $chatbotConversation, 'عدم قدرة البوت على الإجابة');
            }

            // Return fallback message
            return ChatbotResponseDTO::fromKnowledge(
                $settings->getFallbackMessage($language) ?? $this->getDefaultFallback($language),
                '',
                0.0,
                $language
            );
        }

        // 10. Save the message
        $this->saveMessage($chatbotConversation->id, $message, $aiResponse);

        // 11. Reset failed attempts on success
        $chatbotConversation->resetFailedAttempts();

        // 12. Handle handoff suggestion from AI
        if ($aiResponse->shouldHandoff) {
            $chatbotConversation->incrementFailedAttempts();

            if ($chatbotConversation->shouldHandoff($settings->handoff_threshold)) {
                $this->handleHandoff($conversation, $chatbotConversation, 'اقتراح البوت للتحويل');
            }
        }

        // 13. Dispatch event
        event(new BotResponseSent($conversation, $aiResponse));

        return $aiResponse;
    }

    public function getSettings(string $channelId): ?ChatbotSettings
    {
        return $this->repository->getSettings($channelId);
    }

    public function initiateHandoff(Conversation $conversation, string $reason): void
    {
        $chatbotConversation = $conversation->chatbotConversation;

        if ($chatbotConversation) {
            $chatbotConversation->deactivateBot($reason);
        }

        $conversation->update(['status' => Conversation::STATUS_WAITING]);

        event(new HandoffInitiated($conversation, $reason));
    }

    private function respondFromKnowledge(
        Conversation $conversation,
        $chatbotConversation,
        $searchResult,
        string $language,
        string $userMessage
    ): ChatbotResponseDTO {
        $answer = $searchResult->getBestAnswer($language);

        if (!$answer) {
            return ChatbotResponseDTO::failed('No answer found');
        }

        $response = ChatbotResponseDTO::fromKnowledge(
            $answer,
            $searchResult->bestMatch->id,
            $searchResult->confidence,
            $language,
            $searchResult->mayNeedHandoff()
        );

        // Save the message
        $this->saveMessage($chatbotConversation->id, $userMessage, $response);

        // Reset failed attempts
        $chatbotConversation->resetFailedAttempts();

        // Dispatch event
        event(new BotResponseSent($conversation, $response));

        return $response;
    }

    private function handleHandoff(Conversation $conversation, $chatbotConversation, string $reason): ChatbotResponseDTO
    {
        // Deactivate bot
        $chatbotConversation->deactivateBot($reason);

        // Update conversation status
        $conversation->update(['status' => Conversation::STATUS_WAITING]);

        // Dispatch event
        event(new HandoffInitiated($conversation, $reason));

        return ChatbotResponseDTO::handoff($reason);
    }

    private function saveMessage(string $chatbotConversationId, string $userMessage, ChatbotResponseDTO $response): void
    {
        $this->repository->createMessage([
            'chatbot_conversation_id' => $chatbotConversationId,
            'user_message' => $userMessage,
            'bot_response' => $response->message,
            'knowledge_base_id' => $response->knowledgeBaseId,
            'confidence_score' => $response->confidenceScore,
            'used_ai' => $response->usedAi,
            'tokens_used' => $response->tokensUsed,
            'cost_usd' => $response->costUsd,
            'language' => $response->language,
        ]);
    }

    private function getDefaultFallback(string $language): string
    {
        return $language === 'ar'
            ? 'عذراً، لم أفهم سؤالك. هل يمكنك إعادة صياغته؟'
            : 'Sorry, I didn\'t understand your question. Could you please rephrase it?';
    }
}
