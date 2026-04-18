<?php

namespace App\Domain\Conversation\DTOs;

use App\Models\Conversation;
use Illuminate\Http\Request;

readonly class SendWhatsAppMessageDTO
{
    public function __construct(
        public string $fromPhoneNumberId,
        public string $toPhoneNumber,
        public string $conversationId,
        public string $type,
        public ?array $content = null,
        public ?string $contextMessageId = null,
        public ?string $originalText = null,
        public ?string $translatedTo = null,
        public ?string $translationTransactionId = null,
        public ?int $agentId = null,
    ) {}

    public static function fromRequest(Request $request, Conversation $conversation): self
    {
        return new self(
            fromPhoneNumberId: $request->input('from'),
            toPhoneNumber: $request->input('to'),
            conversationId: $conversation->id,
            type: $request->input('type'),
            content: self::extractContent($request),
            contextMessageId: $request->input('context.message_id'),
            originalText: $request->input('original_text'),
            translatedTo: $request->input('translated_to'),
            translationTransactionId: $request->input('translation_transaction_id'),
            agentId: auth('api')->user()?->id,
        );
    }

    private static function extractContent(Request $request): ?array
    {
        $type = $request->input('type');

        return match ($type) {
            'text' => [
                'body' => $request->input('text.body'),
                'preview_url' => $request->input('text.preview_url'),
            ],
            'image' => $request->input('image'),
            'video' => $request->input('video'),
            'audio' => $request->input('audio'),
            'document' => $request->input('document'),
            'location' => $request->input('location'),
            'reaction' => $request->input('reaction'),
            default => null,
        };
    }
}
