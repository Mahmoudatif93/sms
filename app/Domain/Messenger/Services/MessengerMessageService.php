<?php

namespace App\Domain\Messenger\Services;

use App\Domain\Messenger\Actions\MarkMessagesAsReadAction;
use App\Domain\Messenger\Actions\SendFilesMessageAction;
use App\Domain\Messenger\Actions\SendTextMessageAction;
use App\Domain\Messenger\DTOs\MessengerMessageResultDTO;
use App\Domain\Messenger\DTOs\SendTextMessageDTO;
use App\Models\Conversation;
use Illuminate\Http\Request;

class MessengerMessageService
{
    public function __construct(
        private MarkMessagesAsReadAction $markAsReadAction,
        private SendTextMessageAction $sendTextMessageAction,
        private SendFilesMessageAction $sendFilesMessageAction,
    ) {}

    public function markAsRead(Conversation $conversation): array
    {
        return $this->markAsReadAction->execute($conversation);
    }

    public function sendTextMessage(SendTextMessageDTO $dto, Conversation $conversation): MessengerMessageResultDTO
    {
        $dto = $dto->withMessagingType($this->determineMessagingType($conversation));

        $result = $this->sendTextMessageAction->execute($dto);

        if (!$result['success']) {
            return MessengerMessageResultDTO::failure($result['error']);
        }

        return MessengerMessageResultDTO::success($result['message']);
    }

    public function sendFilesMessage(
        Request $request,
        string $pageId,
        string $recipientPsid,
        ?string $conversationId,
        ?string $replyToMessageId = null
    ): MessengerMessageResultDTO {
        $result = $this->sendFilesMessageAction->execute(
            $request,
            $pageId,
            $recipientPsid,
            $conversationId,
            $replyToMessageId
        );
        if (!$result['success']) {
            return MessengerMessageResultDTO::failure(
                $result['error'],
                400,
                $result['errors'] ?? []
            );
        }

        return MessengerMessageResultDTO::success(
            $result['messages'],
            $result['errors'] ?? []
        );
    }

    private function determineMessagingType(Conversation $conversation): string
    {
        // Check if we're within the 7-day messaging window
        if ($conversation->isInCustomerServiceWindow()) {
            return 'RESPONSE';
        }

        // Outside the window, need to use MESSAGE_TAG with HUMAN_AGENT
        return 'MESSAGE_TAG';
    }
}
