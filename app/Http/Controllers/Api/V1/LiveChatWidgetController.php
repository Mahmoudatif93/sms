<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseApiController;
use App\Domain\Conversation\Services\LiveChatWidgetService;
use App\Domain\Conversation\Requests\Widget\InitializeChatRequest;
use App\Domain\Conversation\Requests\Widget\SendWidgetMessageRequest;
use App\Domain\Conversation\Requests\Widget\SubmitPreChatFormRequest;
use App\Domain\Conversation\Requests\Widget\SubmitPostChatFormRequest;
use App\Domain\Conversation\Requests\Widget\SendReactionRequest;
use App\Domain\Conversation\Requests\Widget\GetChatHistoryRequest;
use App\Domain\Conversation\Requests\Widget\MarkMessagesRequest;
use App\Domain\Conversation\Requests\Widget\UpdateWidgetSettingsRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class LiveChatWidgetController extends BaseApiController
{
    public function __construct(
        private LiveChatWidgetService $widgetService
    ) {}

    public function initializeChat(InitializeChatRequest $request): JsonResponse
    {
        try {
            $result = $this->widgetService->initializeChat($request->toDTO());
            return $this->response(true, 'Chat initialized successfully', $result);
        } catch (Throwable $e) {
            return $this->response(false, $e->getMessage(), null, 400);
        }
    }

    public function sendMessage(SendWidgetMessageRequest $request): JsonResponse
    {
        try {
            $result = $this->widgetService->sendMessage($request->toDTO());
            return $this->response(true, 'Message sent successfully', $result);
        } catch (Throwable $e) {
            return $this->response(false, $e->getMessage(), null, 400);
        }
    }

    public function submitPreChatForm(SubmitPreChatFormRequest $request): JsonResponse
    {
        try {
            $result = $this->widgetService->submitPreChatForm($request->toDTO());
            return $this->response(true, 'Pre-chat form submitted successfully', $result);
        } catch (Throwable $e) {
            return $this->response(false, $e->getMessage(), null, 400);
        }
    }

    public function submitPostChatForm(SubmitPostChatFormRequest $request): JsonResponse
    {
        try {
            $result = $this->widgetService->submitPostChatForm($request->toDTO());
            return $this->response(true, 'Post-chat form submitted successfully', $result);
        } catch (Throwable $e) {
            return $this->response(false, $e->getMessage(), null, 400);
        }
    }

    public function sendReaction(SendReactionRequest $request): JsonResponse
    {
        try {
            $result = $this->widgetService->sendReaction($request->toDTO());
            $message = empty($request->emoji) ? 'Reaction removed successfully' : 'Reaction sent successfully';
            return $this->response(true, $message, $result);
        } catch (Throwable $e) {
            return $this->response(false, $e->getMessage(), null, 400);
        }
    }

    public function getChatHistory(GetChatHistoryRequest $request): JsonResponse
    {
        try {
            $result = $this->widgetService->getChatHistory(
                $request->getSessionId(),
                $request->getBeforeId(),
                $request->getLimit()
            );
            return $this->response(true, 'Chat history retrieved successfully', $result);
        } catch (Throwable $e) {
            return $this->response(false, $e->getMessage(), null, 400);
        }
    }

    public function endConversation(Request $request): JsonResponse
    {
        try {
            $request->validate(['session_id' => 'required|uuid|exists:conversations,id']);
            $result = $this->widgetService->endConversation($request->input('session_id'));
            return $this->response(true, 'Chat conversation ended successfully', $result);
        } catch (Throwable $e) {
            return $this->response(false, $e->getMessage(), null, 400);
        }
    }

    public function closeChat(Request $request): JsonResponse
    {
        try {
            $request->validate(['session_id' => 'required|uuid|exists:conversations,id']);
            $result = $this->widgetService->closeChat($request->input('session_id'));
            return $this->response(true, 'Chat conversation closed successfully', $result);
        } catch (Throwable $e) {
            return $this->response(false, $e->getMessage(), null, 400);
        }
    }

    public function markMessagesAsRead(MarkMessagesRequest $request): JsonResponse
    {
        try {
            $this->widgetService->markMessagesAsRead(
                $request->getSessionId(),
                $request->getMessageIds()
            );
            return $this->response(true, 'Messages marked as read successfully');
        } catch (Throwable $e) {
            return $this->response(false, $e->getMessage(), null, 400);
        }
    }

    public function markMessagesAsDelivered(MarkMessagesRequest $request): JsonResponse
    {
        try {
            $request->validate(['message_ids' => 'required|array']);
            $this->widgetService->markMessagesAsDelivered($request->getMessageIds());
            return $this->response(true, 'Messages marked as delivered successfully');
        } catch (Throwable $e) {
            return $this->response(false, $e->getMessage(), null, 400);
        }
    }

    public function sessionHeartbeat(Request $request): JsonResponse
    {
        try {
            $request->validate(['session_id' => 'required|uuid|exists:conversations,id']);
            $this->widgetService->sessionHeartbeat($request->input('session_id'));
            return $this->response(true, 'Conversation heartbeat updated');
        } catch (Throwable $e) {
            return $this->response(false, $e->getMessage(), null, 400);
        }
    }

    public function getPreviousConversations(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'contact_id' => 'required|uuid|exists:contacts,id',
                'widget_id' => 'required|uuid|exists:widgets,id',
            ]);

            $result = $this->widgetService->getPreviousConversations(
                $request->input('contact_id'),
                $request->input('widget_id')
            );
            return $this->response(true, 'Previous conversations retrieved successfully', $result);
        } catch (Throwable $e) {
            return $this->response(false, $e->getMessage(), null, 400);
        }
    }

    public function updateWidgetSettings(UpdateWidgetSettingsRequest $request, Workspace $workspace, $id): JsonResponse
    {
        try {
            $result = $this->widgetService->updateWidgetSettings($request->toDTO($id));
            return $this->response(true, 'Widget settings updated successfully', $result);
        } catch (Throwable $e) {
            return $this->response(false, $e->getMessage(), null, 400);
        }
    }
}
