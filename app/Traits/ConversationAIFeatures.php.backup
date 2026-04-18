<?php

namespace App\Traits;

use App\Http\Responses\ConversationDetails;
use App\Models\Conversation;
use App\Models\Workspace;
use Illuminate\Http\Client\ConnectionException;

trait ConversationAIFeatures
{
    use ChatgptApiManager;

    /**
     * Suggest a reply in the same or target language.
     * @throws ConnectionException
     * @throws \Exception
     */
    public function suggestReply(Conversation $conversation, string $lang = 'auto'): string
    {
        // Use your formatter
        $details = new ConversationDetails($conversation, ['limit' => 10]);
        $messages = $details->messages; // these already have "content"
       

        $input = [
            [
                'role' => 'system',
                'content' => "
You are an AI inbox assistant. Reply naturally and politely in {$lang}.
If lang=auto, detect the language from the conversation.

Rules:
- If the conversation contains RECEIVED messages, reply to the user's last RECEIVED message.
- If there are only SENT messages (from the business), assume the last SENT message is a question or prompt awaiting a customer response, and suggest a helpful, relevant reply the customer might expect (e.g. asking if they need clarification, confirming details, etc.).
"
            ]
        ];

        foreach ($messages as $m) {
            // Flatten WhatsApp template/text/etc into plain text
            $text = $this->extractMessageText($m['content']);
            if ($text) {
                $input[] = [
                    'role' => $m['direction'] === 'RECEIVED' ? 'user' : 'assistant',
                    'content' => $text,
                ];
            }
        }

        $payload = [
            'model' => 'gpt-4o', // best balance: fast & multilingual
            'input' => $input,
        ];

        return $this->runAiAndCharge($conversation->workspace, $payload, 'SUGGEST_REPLY');

    }

    /**
     * Extract plain text from the WhatsApp message content array
     */
    private function extractMessageText(?array $content): ?string
    {
        if (empty($content) || !is_array($content)) {
            return null; // no usable text
        }

        $parts = [];

        // Header (can be text, image, or location)
        if (isset($content['formatted_header'])) {
            $header = $content['formatted_header'];

            if (is_array($header)) {
                switch ($header['type'] ?? null) {
                    case 'text':
                        $parts[] = "Header: " . ($header['text'] ?? '');
                        break;
                    case 'image':
                        $parts[] = "Header: [Image attached]";
                        break;
                    case 'location':
                        $location = [];
                        if (!empty($header['name'])) {
                            $location[] = $header['name'];
                        }
                        if (!empty($header['address'])) {
                            $location[] = $header['address'];
                        }
                        if (!empty($header['latitude']) && !empty($header['longitude'])) {
                            $location[] = "($header[latitude], $header[longitude])";
                        }
                        $parts[] = "Header: Location - " . implode(', ', $location);
                        break;
                }
            }
        }

        // Body (normal template body or text message body)
        if (isset($content['formatted_body'])) {
            $parts[] = trim($content['formatted_body']);
        } elseif (isset($content['text'])) {
            $parts[] = trim($content['text']);
        }

        // Footer (optional but useful context)
        if (!empty($content['formatted_footer'])) {
            $parts[] = "Footer: " . $content['formatted_footer'];
        }

        // Join everything into one plain string for GPT
        return !empty($parts) ? implode(" | ", $parts) : null;
    }

    /**
     * Improve writing of a given text.
     * @throws ConnectionException
     */
    public function improveWriting(Workspace $workspace, string $text, string $lang = 'auto'): string
    {
        $payload = [
            'model' => 'gpt-4o', // best quality for polishing
            'input' => [
                ['role' => 'system', 'content' => "Rewrite the text to be clearer, professional, and natural in {$lang}. If lang=auto, keep same language."],
                ['role' => 'user', 'content' => $text],
            ]
        ];

        return $this->runAiAndCharge($workspace, $payload, 'IMPROVE_WRITING');
    }

    /**
     * Summarize the conversation in the same or target language.
     */
    public function summarizeConversation(Conversation $conversation, string $lang = 'auto'): string
    {
        // Use your formatter
        $details = new ConversationDetails($conversation, ['limit' => 10]);
        $messages = $details->messages; // these already have "content"

        $input = [
            [
                'role' => 'system',
                'content' => "You are an AI inbox assistant that generates clear, structured conversation summaries.

Guidelines:
- Write in professional but natural language.
- Organize chronologically with numbered sections.
- Identify the sender and recipient clearly.
- Extract **intent/purpose** of each message (not just quoting text).
- If the message is repetitive or a follow-up, note its context.
- Translate non-English messages into English in parentheses.
- Keep the summary concise, no more than 5–7 lines.

Format:
**Conversation Summary:**

1. [Sender → Recipient]: Purpose of the message (with key quoted text if helpful).
2. [Sender → Recipient]: Follow-up or reply, summarizing meaning not just exact words.
3. Notes: Special requests, questions, or context."
            ]
        ];

        foreach ($messages as $m) {
            $text = $this->extractMessageText($m['content']);
            if ($text) {
                $input[] = [
                    'role' => $m['direction'] === 'RECEIVED' ? 'user' : 'assistant',
                    'content' => $text,
                ];
            }
        }

        $payload = [
            'model' => 'gpt-4o', // best for high-quality summaries
            'messages' => $input,
        ];

        $response = $this->chatgptRequest($payload);

        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Translate text to a target language.
     *
     * @throws ConnectionException
     */
    public function translateText(
        Workspace $workspace,
        string $text,
        string $targetLang,
        string $sourceLang = 'auto',
        $message =null
    ): string {
        $payload = [
            'model' => 'gpt-4o', 
            'input' => [
                [
                    'role' => 'system',
                    'content' => "
You are a professional translator.

Rules:
- Translate the text accurately and naturally.
- Preserve meaning, tone, and intent.
- Do NOT explain the translation.
- Do NOT add extra text.
- If sourceLang=auto, detect the original language.
- Output ONLY the translated text in {$targetLang}.
"
                ],
                [
                    'role' => 'user',
                    'content' => $text,
                ],
            ],
        ];

        return $this->runAiAndCharge(
            $workspace,
            $payload,
            'TRANSLATE_TEXT'
            ,$message
        );
    }
}
