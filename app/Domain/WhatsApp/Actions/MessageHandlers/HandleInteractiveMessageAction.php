<?php

namespace App\Domain\WhatsApp\Actions\MessageHandlers;

use App\Domain\WhatsApp\Actions\BaseIncomingMessageAction;
use App\Domain\WhatsApp\DTOs\IncomingMessageDTO;
use App\Events\UnifiedMessageEvent;
use App\Events\WhatsappInteractiveResponseReceived;
use App\Http\Responses\Conversation;
use App\Http\Responses\ConversationMessage;
use App\Models\Channel;
use App\Models\WhatsappInteractiveMessage;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Log;

class HandleInteractiveMessageAction extends BaseIncomingMessageAction
{
    public function execute(IncomingMessageDTO $dto): ?WhatsappMessage
    {
        $interactiveType = $dto->getInteractiveType();

        return match ($interactiveType) {
            'button_reply' => $this->handleButtonReply($dto),
            'list_reply' => $this->handleListReply($dto),
            'nfm_reply' => $this->handleNfmReply($dto),
            default => $this->handleUnknownInteractive($dto, $interactiveType),
        };
    }

    protected function getMessageType(): string
    {
        return WhatsappMessage::MESSAGE_TYPE_INTERACTIVE;
    }

    protected function createMessageContent(IncomingMessageDTO $dto, WhatsappMessage $whatsappMessage): object
    {
        // This won't be called directly as we handle each interactive type separately
        return $this->repository->createInteractiveMessage($dto->messageId, [
            'type' => $dto->getInteractiveType(),
            'payload' => $dto->getInteractivePayload(),
        ]);
    }

    private function handleButtonReply(IncomingMessageDTO $dto): ?WhatsappMessage
    {
        $buttonReply = $dto->payload['interactive']['button_reply'] ?? [];
        $replyId = $buttonReply['id'] ?? null;
        $replyTitle = $buttonReply['title'] ?? null;

        $whatsappMessage = $this->createInteractiveMessage($dto, [
            'type' => WhatsappInteractiveMessage::TYPE_BUTTON_REPLY,
            'button_reply_id' => $replyId,
            'button_reply_title' => $replyTitle,
            'payload' => $dto->payload['interactive'] ?? null,
        ]);

        if ($whatsappMessage) {
            $this->dispatchInteractiveWorkflowEvent($whatsappMessage, 'button_reply', $replyId, $replyTitle);
        }

        return $whatsappMessage;
    }

    private function handleListReply(IncomingMessageDTO $dto): ?WhatsappMessage
    {
        $listReply = $dto->payload['interactive']['list_reply'] ?? [];
        $replyId = $listReply['id'] ?? null;
        $replyTitle = $listReply['title'] ?? null;
        $replyDescription = $listReply['description'] ?? null;

        $whatsappMessage = $this->createInteractiveMessage($dto, [
            'type' => WhatsappInteractiveMessage::TYPE_LIST_REPLY,
            'list_reply_id' => $replyId,
            'list_reply_title' => $replyTitle,
            'list_reply_description' => $replyDescription,
            'payload' => $dto->payload['interactive'] ?? null,
        ]);

        if ($whatsappMessage) {
            $this->dispatchInteractiveWorkflowEvent($whatsappMessage, 'list_reply', $replyId, $replyTitle);
        }

        return $whatsappMessage;
    }

    private function handleNfmReply(IncomingMessageDTO $dto): ?WhatsappMessage
    {
        if ($this->messageExists($dto->messageId)) {
            return $this->repository->findMessage($dto->messageId);
        }

        $nfmReply = $dto->payload['interactive']['nfm_reply'] ?? [];
        $responseJson = json_decode($nfmReply['response_json'] ?? '{}', true);

        $sender = $this->createSender($dto);
        $whatsappMessage = $this->createBaseMessage($dto, $sender);

        $this->saveMessageStatus($dto->messageId, $dto->timestamp);

        $flowMessage = $this->repository->createFlowResponseMessage($dto->messageId, [
            'flow_token' => $responseJson['flow_token'] ?? null,
            'name' => $nfmReply['name'] ?? null,
            'body' => $nfmReply['body'] ?? null,
            'response_json' => $nfmReply['response_json'] ?? null,
        ]);

        $this->updateMessageable($dto->messageId, $flowMessage);
        $this->handleConversationAndBroadcast($whatsappMessage, $dto);

        return $whatsappMessage;
    }

    private function handleUnknownInteractive(IncomingMessageDTO $dto, ?string $type): ?WhatsappMessage
    {
        Log::info('Unhandled interactive type: ' . ($type ?? 'unknown'));
        return null;
    }

    private function createInteractiveMessage(IncomingMessageDTO $dto, array $interactiveData): ?WhatsappMessage
    {
        if ($this->messageExists($dto->messageId)) {
            return null;
        }

        $sender = $this->createSender($dto);
        $whatsappMessage = $this->createBaseMessage($dto, $sender);

        $this->saveMessageStatus($dto->messageId, $dto->timestamp);

        $interactiveMessage = $this->repository->createInteractiveMessage($dto->messageId, $interactiveData);

        $this->updateMessageable($dto->messageId, $interactiveMessage);
        $this->handleConversationAndBroadcast($whatsappMessage, $dto);

        return $whatsappMessage;
    }

    private function dispatchInteractiveWorkflowEvent(
        WhatsappMessage $responseMessage,
        string $replyType,
        ?string $replyId,
        ?string $replyTitle
    ): void {
        $originalMessageId = $responseMessage->replied_to_message_id;
        if (!$originalMessageId) {
            Log::info('No original message ID found for interactive reply', [
                'response_message_id' => $responseMessage->id,
            ]);
            return;
        }

        $originalInteractiveMessage = WhatsappInteractiveMessage::where('whatsapp_message_id', $originalMessageId)->first();
        if (!$originalInteractiveMessage) {
            Log::info('Original interactive message not found', [
                'response_message_id' => $responseMessage->id,
                'original_message_id' => $originalMessageId,
            ]);
            return;
        }

        $draftId = $originalInteractiveMessage->interactive_message_draft_id;
        if (!$draftId) {
            Log::info('No draft ID found for original interactive message', [
                'response_message_id' => $responseMessage->id,
                'original_interactive_message_id' => $originalInteractiveMessage->id,
            ]);
            return;
        }

        event(new WhatsappInteractiveResponseReceived(
            $responseMessage,
            $draftId,
            $replyType,
            $replyId ?? '',
            $replyTitle
        ));

        Log::info('Dispatched interactive workflow event', [
            'response_message_id' => $responseMessage->id,
            'draft_id' => $draftId,
            'reply_type' => $replyType,
            'reply_id' => $replyId,
        ]);
    }
}
