<?php

namespace App\Domain\Chatbot\Services;

use App\Domain\Chatbot\DTOs\ChatbotResponseDTO;
use App\Models\ChatbotSettings;
use App\Models\Conversation;
use App\Traits\ChatgptApiManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ChatbotAIService
{
    use ChatgptApiManager;

    public function generateResponse(
        Conversation $conversation,
        string $message,
        Collection $relevantKnowledge,
        ChatbotSettings $settings,
        string $language = 'ar'
    ): ChatbotResponseDTO {
        try {
            // 1. Build context from knowledge base
            $context = $this->buildContext($relevantKnowledge, $language);

            // 2. Build system prompt
            $systemPrompt = $this->buildSystemPrompt($settings, $context, $language);

            // 3. Build conversation history (last few messages)
            $history = $this->buildConversationHistory($conversation);

            // 4. Build input with history
            $input = $this->buildInputWithHistory($history, $message);

            // 5. Prepare payload with JSON format enforcement
            $payload = [
                'model' => $settings->ai_model,
                'input' => $input,
                'instructions' => $systemPrompt,
                'max_output_tokens' => $settings->max_tokens,
                'temperature' => $settings->temperature,
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'chatbot_response',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => [
                                    'type' => 'string',
                                    'description' => 'The response message to send to the customer',
                                ],
                                'customer_requested_handoff' => [
                                    'type' => 'boolean',
                                    'description' => 'True if customer explicitly wants to speak with a human agent',
                                ],
                                'response_type' => [
                                    'type' => 'string',
                                    'enum' => ['normal', 'fallback'],
                                    'description' => 'Type of response: normal for regular replies, fallback for unclear messages',
                                ],
                            ],
                            'required' => ['message', 'customer_requested_handoff', 'response_type'],
                            'additionalProperties' => false,
                        ],
                        'strict' => true,
                    ],
                ],
            ];

            // 5. Call OpenAI API
            $response = $this->chatgptRequest($payload);

            // 6. Parse response
            $result = $this->parseChatgptResponse($response, $payload);

            $text = $result['text'] ?? '';
            $usage = $result['usage'] ?? [];
            $cost = $result['cost_usd'] ?? 0.0;

            if (empty($text)) {
                return ChatbotResponseDTO::failed('لم يتم الحصول على رد من الذكاء الاصطناعي');
            }

            // 7. Parse JSON response if AI returned structured format
            $parsedResponse = $this->parseStructuredResponse($text);
            $responseMessage = $parsedResponse['message'] ?? $text;
            $customerRequestedHandoff = $parsedResponse['customer_requested_handoff'] ?? false;
            $responseType = $parsedResponse['response_type'] ?? 'normal';

            return ChatbotResponseDTO::fromAi(
                message: $responseMessage,
                tokensUsed: ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
                costUsd: $cost,
                language: $language,
                customerRequestedHandoff: $customerRequestedHandoff,
                responseType: $responseType
            );

        } catch (\Exception $e) {
            Log::error('Chatbot AI Service Error', [
                'conversation_id' => $conversation->id,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            return ChatbotResponseDTO::failed($e->getMessage());
        }
    }

    private function buildContext(Collection $knowledge, string $language): string
    {
        if ($knowledge->isEmpty()) {
            return '';
        }

        $contextItems = $knowledge->map(function ($item) use ($language) {
            $question = $item->getQuestion($language);
            $answer = $item->getAnswer($language);

            if ($question && $answer) {
                return "س: {$question}\nج: {$answer}";
            }

            return $answer;
        })->filter()->values();

        return $contextItems->join("\n\n---\n\n");
    }

    private function buildSystemPrompt(ChatbotSettings $settings, string $context, string $language): string
    {
        $basePrompt = $settings->getDefaultSystemPrompt();

        $languageInstruction = $language === 'ar'
            ? 'الرد يجب أن يكون باللغة العربية.'
            : 'Please respond in English.';

        $jsonInstruction = $language === 'ar'
            ? "# ⚠️ تنسيق الرد (إلزامي - لا تتجاهل هذا):\n" .
              "**يجب أن يكون ردك بصيغة JSON فقط. لا ترد بنص عادي أبداً.**\n\n" .
              "```json\n" .
              "{\n" .
              "  \"message\": \"رسالتك للعميل هنا\",\n" .
              "  \"customer_requested_handoff\": false,\n" .
              "  \"response_type\": \"normal\"\n" .
              "}\n" .
              "```\n\n" .
              "### قواعد الحقول:\n" .
              "1. `customer_requested_handoff` - حدد `true` أو `false` بناءً على نية العميل:\n" .
              "   **متى تكون `true`:**\n" .
              "   - العميل يريد التحدث مع إنسان/موظف/ممثل خدمة عملاء (بأي صيغة كانت)\n" .
              "   - العميل يطلب منك تنفيذ إجراء لا تستطيع فعله (مثل: الحجز نيابة عنه، تعديل بياناته)\n" .
              "   **متى تكون `false`:**\n" .
              "   - العميل يسأل سؤال عام (كيف، أين، متى، كم، ما هو)\n" .
              "   - العميل يطلب معلومة أو رابط\n" .
              "   - أنت عرضت خيار التحويل لكن العميل لم يطلبه بعد\n" .
              "2. `response_type` يجب أن يكون أحد القيم التالية:\n" .
              "   - `\"normal\"`: رد عادي على سؤال أو استفسار العميل.\n" .
              "   - `\"fallback\"`: استخدمها عندما لا تستطيع تقديم رد مفيد:\n" .
              "     * الرسالة غير مفهومة أو رموز عشوائية.\n" .
              "     * الرسالة غير موجهة للشات بوت (مثل: \"يا سارة لا تردي\").\n" .
              "     * الرسالة داخلية أو اختبارية.\n" .
              "     * لا تستطيع فهم ما يريده العميل.\n" .
              "   - في حالة `fallback`، اترك `message` فارغة.\n\n" .
              "**تذكر: رد فقط بـ JSON. أي رد بنص عادي سيتم رفضه.**"
            : "# ⚠️ Response Format (Mandatory - Do NOT ignore this):\n" .
              "**Your response MUST be in JSON format only. Never respond with plain text.**\n\n" .
              "```json\n" .
              "{\n" .
              "  \"message\": \"Your message to the customer here\",\n" .
              "  \"customer_requested_handoff\": false,\n" .
              "  \"response_type\": \"normal\"\n" .
              "}\n" .
              "```\n\n" .
              "### Field Rules:\n" .
              "1. `customer_requested_handoff` - set `true` or `false` based on customer intent:\n" .
              "   **When `true`:**\n" .
              "   - Customer wants to speak with a human/agent/representative (in any phrasing)\n" .
              "   - Customer asks you to perform an action you cannot do (e.g., book for them, modify their data)\n" .
              "   **When `false`:**\n" .
              "   - Customer is asking a general question (how, where, when, how much, what is)\n" .
              "   - Customer is requesting information or a link\n" .
              "   - You offered transfer option but customer hasn't requested it yet\n" .
              "2. `response_type` must be one of:\n" .
              "   - `\"normal\"`: Normal response to customer's question or inquiry.\n" .
              "   - `\"fallback\"`: Use when you cannot provide a useful response:\n" .
              "     * Message is unintelligible or random characters.\n" .
              "     * Message is not directed at the chatbot (e.g., \"Sarah don't reply\").\n" .
              "     * Message is internal/testing.\n" .
              "     * You cannot understand what the customer wants.\n" .
              "   - For `fallback`, leave `message` empty.\n\n" .
              "**Remember: Respond ONLY with JSON. Any plain text response will be rejected.**";

        // Build prompt with knowledge base FIRST (most important), then JSON format
        if (!empty($context)) {
            $contextInstruction = $language === 'ar'
                ? "# ⚠️ قاعدة إلزامية - الأولوية القصوى:\n" .
                  "**انسخ الإجابة من قاعدة المعرفة حرفياً بدون أي تعديل.**\n\n" .
                  "## قاعدة المعرفة (س: سؤال / ج: جواب):\n" .
                  "{$context}\n\n" .
                  "## تعليمات النسخ:\n" .
                  "- إذا تطابق سؤال العميل مع سؤال في القاعدة: **انسخ الإجابة كاملة حرف بحرف**\n" .
                  "- لا تختصر، لا تلخص، لا تعيد الصياغة\n" .
                  "- إذا كانت الإجابة طويلة، أرسلها كاملة\n" .
                  "- فقط إذا لم يوجد تطابق، يمكنك صياغة رد مناسب"
                : "# ⚠️ Mandatory Rule - Highest Priority:\n" .
                  "**Copy answers from knowledge base VERBATIM without any modification.**\n\n" .
                  "## Knowledge Base (Q: question / A: answer):\n" .
                  "{$context}\n\n" .
                  "## Copy Instructions:\n" .
                  "- If customer's question matches a question in the knowledge base: **copy the answer exactly, word for word**\n" .
                  "- Do not shorten, summarize, or rephrase\n" .
                  "- If the answer is long, send it completely\n" .
                  "- Only if no match exists, compose an appropriate response";

            $prompt = "{$basePrompt}\n\n{$languageInstruction}\n\n{$contextInstruction}\n\n{$jsonInstruction}";
        } else {
            $prompt = "{$basePrompt}\n\n{$languageInstruction}\n\n{$jsonInstruction}";
        }

        return $prompt;
    }

    private function buildConversationHistory(Conversation $conversation): array
    {
        $chatbotConversation = $conversation->chatbotConversation;

        if (!$chatbotConversation) {
            return [];
        }

        $messages = $chatbotConversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->reverse();

        $history = [];

        foreach ($messages as $message) {
            $history[] = [
                'role' => 'user',
                'content' => $message->user_message,
            ];

            if ($message->bot_response) {
                $history[] = [
                    'role' => 'assistant',
                    'content' => $message->bot_response,
                ];
            }
        }

        return $history;
    }

    /**
     * Build input array with conversation history for OpenAI Responses API
     */
    private function buildInputWithHistory(array $history, string $currentMessage): array|string
    {
        // If no history, just return the current message as string
        if (empty($history)) {
            return $currentMessage;
        }

        // Build input array with history + current message
        $input = [];

        foreach ($history as $item) {
            $input[] = [
                'type' => 'message',
                'role' => $item['role'],
                'content' => $item['content'],
            ];
        }

        // Add current user message
        $input[] = [
            'type' => 'message',
            'role' => 'user',
            'content' => $currentMessage,
        ];

        return $input;
    }

    private function parseStructuredResponse(string $text): array
    {
        // Try to extract JSON from the response
        // The AI might return pure JSON or JSON wrapped in markdown code blocks
        $jsonPattern = '/```(?:json)?\s*(\{[\s\S]*?\})\s*```/';

        if (preg_match($jsonPattern, $text, $matches)) {
            $jsonString = $matches[1];
        } elseif (str_starts_with(trim($text), '{') && str_ends_with(trim($text), '}')) {
            $jsonString = trim($text);
        } else {
            // No JSON found, return text as message
            return ['message' => $text, 'customer_requested_handoff' => false, 'response_type' => 'normal'];
        }

        try {
            $decoded = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);

            return [
                'message' => $decoded['message'] ?? $text,
                'customer_requested_handoff' => (bool) ($decoded['customer_requested_handoff'] ?? false),
                'response_type' => $decoded['response_type'] ?? 'normal',
            ];
        } catch (\JsonException $e) {
            return ['message' => $text, 'customer_requested_handoff' => false, 'response_type' => 'normal'];
        }
    }
}
