<?php

namespace App\Domain\WhatsApp\Actions;

use App\Domain\WhatsApp\DTOs\MessageStatusDTO;
use App\Domain\WhatsApp\Repositories\WhatsAppMessageRepositoryInterface;
use App\Events\WhatsappMessageStatusUpdated;
use App\Logging\MetaConversationTextLogs;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageStatus;
use App\Traits\WhatsappWalletManager;
use Illuminate\Support\Facades\Log;

class HandleStatusUpdateAction
{
    use WhatsappWalletManager;

    public function __construct(
        private WhatsAppMessageRepositoryInterface $repository
    ) {}

    public function execute(MessageStatusDTO $dto): void
    {
        $whatsappMessage = $this->repository->findMessage($dto->messageId);

        if (!$whatsappMessage) {
            Log::error("Message with ID {$dto->messageId} not found.");
            return;
        }

        // Update message status
        $this->repository->updateMessageStatus(
            $dto->messageId,
            $dto->status,
            $dto->conversationId
        );

        // Finalize wallet transaction
        $this->finalizeWhatsappWalletTransaction($whatsappMessage, $dto->status);

        // Save status record
        $messageStatus = $this->saveStatusRecord($dto);

        // Handle errors if any
        if ($dto->hasErrors()) {
            $this->handleStatusErrors($dto, $messageStatus, $whatsappMessage);
        } else {
            $this->logSuccessfulStatus($dto, $whatsappMessage);
        }
        // Dispatch status update event
        event(new WhatsappMessageStatusUpdated($whatsappMessage));
    }

    private function saveStatusRecord(MessageStatusDTO $dto): WhatsappMessageStatus
    {
        return WhatsappMessageStatus::updateOrCreate(
            [
                'whatsapp_message_id' => $dto->messageId,
                'status' => $dto->status,
            ],
            [
                'timestamp' => $dto->timestamp,
            ]
        );
    }

    private function handleStatusErrors(
        MessageStatusDTO $dto,
        WhatsappMessageStatus $messageStatus,
        WhatsappMessage $whatsappMessage
    ): void {
        foreach ($dto->errors as $error) {
            $this->repository->createStatusError($messageStatus->id, $error);

            // Log status for known errors
            $this->logStatusForError($dto, $whatsappMessage, $error);

            // Handle specific error code 131047 (Re-engagement message blocked)
            if (($error['code'] ?? null) === 131047) {
                $this->repository->logMetaConversation([
                    'conversation_id' => $whatsappMessage->conversation_id,
                    'whatsapp_message_id' => $whatsappMessage->id,
                    'whatsapp_conversation_id' => $whatsappMessage->whatsapp_conversation_id,
                    'decision' => 'csw_closed_failed',
                    'category_attempted' => optional($whatsappMessage->template?->whatsappTemplate)?->category,
                    'message_type' => $whatsappMessage->type,
                    'direction' => $whatsappMessage->direction,
                    'was_blocked' => true,
                    'meta_error_code' => 131047,
                    'meta_error_message' => $error['error_data']['details'] ?? 'Re-engagement message',
                ]);
            }
        }
    }

    private function logStatusForError(MessageStatusDTO $dto, WhatsappMessage $message, array $error): void
    {
        $decision = match ($dto->status) {
            'sent' => 'sent_successful',
            'delivered' => 'delivered_successful',
            'read' => 'read_by_user',
            default => null,
        };

        if ($decision) {
            $this->repository->logMetaConversation([
                'conversation_id' => $message->conversation_id,
                'whatsapp_message_id' => $message->id,
                'whatsapp_conversation_id' => $message->whatsapp_conversation_id,
                'decision' => $decision,
                'category_attempted' => $dto->pricingCategory ?? optional($message->template?->whatsappTemplate)?->category,
                'message_type' => $message->type,
                'direction' => $message->direction,
                'was_blocked' => false,
                'meta_error_code' => $error['code'] ?? null,
                'meta_error_message' => $error['message'] ?? null,
            ]);
        }
    }

    private function logSuccessfulStatus(MessageStatusDTO $dto, WhatsappMessage $message): void
    {
        $decision = match ($dto->status) {
            'sent' => 'sent_successful',
            'delivered' => 'delivered_successful',
            'read' => 'read_by_user',
            default => null,
        };

        if (!$decision) {
            return;
        }

        $this->repository->logMetaConversation([
            'conversation_id' => $message->conversation_id,
            'whatsapp_message_id' => $message->id,
            'whatsapp_conversation_id' => $message->whatsapp_conversation_id,
            'decision' => $decision,
            'category_attempted' => $dto->pricingCategory ?? optional($message->template?->whatsappTemplate)?->category,
            'message_type' => $message->type,
            'direction' => $message->direction,
            'was_blocked' => false,
        ]);
    }
}
