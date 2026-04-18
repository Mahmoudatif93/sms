<?php

namespace App\Domain\Messenger\Actions;

use App\Domain\Messenger\DTOs\MessageStatusDTO;
use App\Domain\Messenger\Repositories\MessengerMessageRepositoryInterface;
use App\Events\MessengerMessageStatusUpdated;
use App\Models\MessengerMessage;
use Illuminate\Support\Facades\Log;

class HandleMessageStatusAction
{
    public function __construct(
        private MessengerMessageRepositoryInterface $repository
    ) {}

    public function execute(MessageStatusDTO $dto): void
    {
        if (!$dto->isValid()) {
            Log::warning('Invalid message status payload', [
                'pageId' => $dto->pageId,
                'status' => $dto->status,
            ]);
            return;
        }
        if ($dto->hasMessageIds()) {
            $this->updateByMessageIds($dto);
        } else {
            $this->updateByWatermark($dto);
        }
    }

    private function updateByMessageIds(MessageStatusDTO $dto): void
    {
        $status = $this->mapStatus($dto->status);
        foreach ($dto->messageIds as $messageId) {
            $message = $this->repository->findMessage($messageId);

            if (!$message) {
                Log::debug("Messenger message not found for status update", [
                    'messageId' => $messageId,
                    'status' => $dto->status,
                ]);
                continue;
            }

            if ($this->shouldUpdateStatus($message, $status)) {
                $this->repository->updateMessageStatus($messageId, $status);
                $this->repository->saveMessageStatus($messageId, $status);

                Log::info("Messenger message status updated", [
                    'messageId' => $messageId,
                    'oldStatus' => $message->status,
                    'newStatus' => $status,
                ]);

                $message->status = $status;
                event(new MessengerMessageStatusUpdated($message));
            }
        }
    }

    private function updateByWatermark(MessageStatusDTO $dto): void
    {
        $status = $this->mapStatus($dto->status);
        $watermarkTimestamp = $dto->watermark / 1000;

        // Find consumer by PSID
        $consumer = $this->repository->findConsumerByPsid($dto->senderId, $dto->pageId);

        if (!$consumer) {
            Log::debug("Messenger consumer not found for status update", [
                'psid' => $dto->senderId,
                'pageId' => $dto->pageId,
            ]);
            return;
        }

        $messages = $this->repository->getMessagesBefore(
            pageId: $dto->pageId,
            consumerId: $consumer->id,
            beforeTimestamp: $watermarkTimestamp,
            direction: MessengerMessage::MESSAGE_DIRECTION_SENT,
            targetStatus: $status
        );
        foreach ($messages as $message) {
            if ($this->shouldUpdateStatus($message, $status)) {
                $this->repository->updateMessageStatus($message->id, $status);
                $this->repository->saveMessageStatus($message->id, $status);

                Log::info("Messenger message status updated by watermark", [
                    'messageId' => $message->id,
                    'newStatus' => $status,
                    'watermark' => $dto->watermark,
                ]);

                $message->status = $status;
                event(new MessengerMessageStatusUpdated($message));
            }
        }
    }

    private function mapStatus(string $dtoStatus): string
    {
        return match ($dtoStatus) {
            MessageStatusDTO::STATUS_DELIVERED => MessengerMessage::MESSAGE_STATUS_DELIVERED,
            MessageStatusDTO::STATUS_READ => MessengerMessage::MESSAGE_STATUS_READ,
            default => $dtoStatus,
        };
    }

    private function shouldUpdateStatus(MessengerMessage $message, string $newStatus): bool
    {
        $statusOrder = [
            MessengerMessage::MESSAGE_STATUS_INITIATED => 1,
            MessengerMessage::MESSAGE_STATUS_SENT => 2,
            MessengerMessage::MESSAGE_STATUS_DELIVERED => 3,
            MessengerMessage::MESSAGE_STATUS_READ => 4,
        ];

        $currentOrder = $statusOrder[$message->status] ?? 0;
        $newOrder = $statusOrder[$newStatus] ?? 0;

        return $newOrder > $currentOrder;
    }
}
