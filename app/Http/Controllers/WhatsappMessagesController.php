<?php

namespace App\Http\Controllers;

use App\Models\WhatsappMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappMessagesController extends BaseApiController
{
    /**
     * Get a list of WhatsApp messages with optional filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        // Get query parameters
        $type = $request->query('type');
        $direction = $request->query('direction');
        $status = $request->query('status');
        $senderRole = $request->query('sender_role');

        // Build query with filters
        $query = WhatsappMessage::query();

        if (!is_null($type)) {
            $query->where('type', $type);
        }

        if (!is_null($direction)) {
            $query->where('direction', $direction);
        }

        if (!is_null($status)) {
            $query->where('status', $status);
        }

        if (!is_null($senderRole)) {
            $query->where('sender_role', $senderRole);
        }

        // Pagination parameters
        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);

        // Fetch paginated messages
        $messages = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginateResponse(true, 'WhatsApp messages retrieved successfully.', $messages);
    }

    /**
     * Get details of a specific WhatsApp message.
     */
    public function show(WhatsappMessage $whatsappMessage): JsonResponse
    {
        $whatsappMessage->load(['sender', 'recipient', 'conversation', 'statuses', 'imageMessage', 'videoMessage', 'audioMessage']);

        return $this->response(true, 'WhatsApp message details retrieved successfully.', $whatsappMessage);
    }
}
