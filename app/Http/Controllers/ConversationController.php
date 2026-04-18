<?php

namespace App\Http\Controllers;

use App\Constants\Meta;
use App\Http\Controllers\Whatsapp\WhatsappMessageController;
use App\Http\Responses\ConversationDetails;
use App\Http\Responses\ConversationMessage;
use App\Http\Responses\ConversationNote;
use App\Models\Channel;
use App\Models\ContactEntity;
use App\Models\Conversation;
use App\Models\LiveChatConfiguration;
use App\Models\LiveChatFileMessage;
use App\Models\LiveChatMessage;
use App\Models\LiveChatReactionMessage;
use App\Models\LiveChatTextMessage;
use App\Models\User;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageStatus;
use App\Models\WhatsappPhoneNumber;
use App\Models\Widget;
use App\Models\Workspace;
use App\Models\PreChatForm;
use App\Services\Messaging\LiveChatMessageHandler;
use App\Traits\BusinessTokenManager;
use App\Traits\ConversationAIFeatures;
use App\Traits\ConversationManager;
use App\Traits\LiveChatMessageManager;
use App\Traits\ResponseManager;
use App\Traits\WhatsappWalletManager;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ConversationController extends BaseApiController
{
    use LiveChatMessageManager, BusinessTokenManager, ConversationManager, WhatsappWalletManager, ResponseManager, ConversationAIFeatures;

    protected $liveChatMessageHandler;

    public function __construct(LiveChatMessageHandler $liveChatMessageHandler)
    {
        $this->liveChatMessageHandler = $liveChatMessageHandler;
    }

    public function getAllConversations(Request $request, Workspace $workspace)
    {
        $filter = $request->input('filter', null); // ['','me','unassigned']
        $status = $request->input('status', null);  // ['','archived','not_replied','promotional']
        $sort = $request->input('sort', 'newest');
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search', null);
        $page = $request->get('page', 1);

        // Remove leading zero if search starts with 0
        if ($search && str_starts_with($search, '0')) {
            $search = ltrim($search, '0');
        }

        $accessor = $this->getAccessor($request);

        // Check authorization
        if (!$this->canAccessWorkspaceConversations($accessor, $workspace)) {
            return $this->errorResponse("Unauthorized to view conversations in this workspace.", null, 403);
        }

        // Build base query
        $query = $this->buildBaseConversationQuery($workspace);

        // Apply search filter
        $this->applySearchFilter($query, $search);

        // Apply status filter (pass filter to know if we should exclude promotional)
        $this->applyStatusFilter($query, $status, $filter);

        // Apply assigned_to filter
        $this->applyAssignedToFilter($query, $filter, $accessor);

        // Add latest messages relationships - use eager loading
        $query = $query->with([
            'latestWhatsappMessage',
            'latestMessengerMessage',
            'latestLiveChatMessage',
            'contact.identifiers',
            'contact.attributes.attributeDefinition'
        ]);

        // Apply sorting using last_message_at column if available, fallback to subqueries
        $this->applySortingOptimized($query, $sort);

        // Paginate results
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform the collection
        $collection = $paginated->getCollection()->map(
            fn($conversation) => new \App\Http\Responses\Conversation($conversation)
        );
        $paginated->setCollection($collection);

        // Calculate statistics in a single optimized query (skip if not first page to save time)
        $statistics = $page == 1
            ? $this->calculateConversationStatisticsOptimized($workspace, $accessor, $search)
            : [];

        return $this->paginateResponse(true, 'Conversations retrieved successfully', $paginated, 200, [], ['statistics' => $statistics]);
    }

    /**
     * Check if accessor can access workspace conversations
     */
    private function canAccessWorkspaceConversations($accessor, Workspace $workspace): bool
    {
        return $accessor->isOrganizationOwner($workspace->organization)
            || $accessor instanceof \App\Models\AccessKey
            || $accessor->isMemberOfWorkspace($workspace);
    }

    /**
     * Build base conversation query with platform filters
     */
    private function buildBaseConversationQuery(Workspace $workspace)
    {
        return $workspace->conversations();
    }

    /**
     * Apply search filter to query
     */
    private function applySearchFilter($query, ?string $search): void
    {
        if (empty($search)) {
            return;
        }

        $searchWithoutSpaces = str_replace(' ', '', $search);

        $query->whereHas('contact', function ($contactQuery) use ($search, $searchWithoutSpaces) {
            $contactQuery->where(function ($subQuery) use ($search, $searchWithoutSpaces) {
                $subQuery->whereHas('identifiers', function ($identifierQuery) use ($searchWithoutSpaces) {
                    $identifierQuery->where('key', ContactEntity::IDENTIFIER_TYPE_PHONE)
                        ->whereRaw("REPLACE(value, ' ', '') LIKE ?", ['%' . $searchWithoutSpaces . '%']);
                })
                    ->orWhereHas('attributes', function ($attributeQuery) use ($search) {
                        $attributeQuery->whereHas('attributeDefinition', function ($defQuery) {
                            $defQuery->whereIn('key', ['first-name', 'last-name', 'display-name']);
                        })
                            ->where('value', 'like', '%' . $search . '%');
                    });
            });
        });
    }

    /**
     * Apply status filter to query
     * @param $query
     * @param string|null $status
     * @param string|null $filter - used to determine if promotional should be excluded
     */
    private function applyStatusFilter($query, ?string $status, ?string $filter = null): void
    {
        match ($status) {
            'archived' => $query->where('status', Conversation::STATUS_ARCHIVED),
            'not_replied' => $this->applyNotRepliedFilter($query),
            'promotional' => $this->applyPromotionalFilter($query),
            // When filter is 'me', don't exclude promotional (show all assigned to me including promotional)
            default => $filter === 'me'
            ? $query->where('status', '!=', Conversation::STATUS_ARCHIVED)
            : $this->applyDefaultStatusFilter($query),
        };
    }

    /**
     * Apply default status filter - excludes archived and promotional conversations
     */
    private function applyDefaultStatusFilter($query): void
    {
        $query->where('status', '!=', Conversation::STATUS_ARCHIVED)
            // Exclude promotional conversations (template sent, no customer reply)
            ->where(function ($q) {
                $q->whereDoesntHave('whatsappMessages', function ($subQ) {
                    $subQ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_SENT)
                        ->where('type', WhatsappMessage::MESSAGE_TYPE_TEMPLATE);
                })
                    ->orWhereHas('whatsappMessages', function ($subQ) {
                        $subQ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_RECEIVED);
                    });
            });
    }

    /**
     * Apply not_replied filter - conversations with unread customer messages
     */
    private function applyNotRepliedFilter($query): void
    {
        $query->where('status', '!=', Conversation::STATUS_ARCHIVED)
            ->where(function ($q) {
                $q->whereHas('whatsappMessages', function ($subQ) {
                    $subQ->where('direction', 'RECEIVED')
                        ->where('status', '<>', WhatsappMessage::MESSAGE_STATUS_READ);
                })
                    ->orWhereHas('messengerMessages', function ($subQ) {
                        $subQ->where('direction', 'RECEIVED')
                            ->whereIn('status', ['initiated', 'sent', 'delivered']);
                    })
                    ->orWhereHas('liveChatMessages', function ($subQ) {
                        $subQ->where('sender_type', ContactEntity::class)
                            ->whereIn('status', ['initiated', 'sent', 'delivered']);
                    });
            });
    }

    /**
     * Apply promotional filter - conversations started with template but customer never replied
     * These are conversations where:
     * - Has at least one template message sent
     * - Has NO received messages from customer
     */
    private function applyPromotionalFilter($query): void
    {
        $query->where('status', '!=', Conversation::STATUS_ARCHIVED)
            // Must have at least one template message sent
            ->whereHas('whatsappMessages', function ($subQ) {
                $subQ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_SENT)
                    ->where('type', WhatsappMessage::MESSAGE_TYPE_TEMPLATE);
            })
            // Must NOT have any received messages (customer never replied)
            ->whereDoesntHave('whatsappMessages', function ($subQ) {
                $subQ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_RECEIVED);
            });
    }

    /**
     * Apply assigned_to filter
     */
    private function applyAssignedToFilter($query, ?string $filter, $accessor): void
    {
        if ($filter === 'me') {
            $query->whereHas('agents', function ($q) use ($accessor) {
                $q->where('inbox_agent_id', $accessor->id)
                    ->whereNull('conversation_agents.removed_at');
            });
        } elseif ($filter === 'unassigned') {
            $query->whereDoesntHave('agents', function ($q) use ($accessor) {
                $q->where('inbox_agent_id', $accessor->id)
                    ->whereNull('conversation_agents.removed_at');
            });
        }
    }

    /**
     * Apply sorting to query (optimized version using last_message_at column)
     */
    private function applySortingOptimized($query, string $sort): void
    {
        // Check if last_message_at column exists, use it for faster sorting
        $hasLastMessageAt = \Schema::hasColumn('conversations', 'last_message_at');

        if ($hasLastMessageAt) {
            match ($sort) {
                'oldest' => $query->orderBy('conversations.last_message_at', 'asc')
                    ->orderBy('conversations.updated_at', 'asc'),
                'waiting_longest' => $this->applyWaitingLongestSort($query),
                default => $query->orderBy('conversations.last_message_at', 'desc')
                    ->orderByDesc('conversations.updated_at'),
            };
        } else {
            // Fallback to subqueries if column doesn't exist yet
            $query->withMax('whatsappMessages as last_wa', 'created_at')
                ->withMax('messengerMessages as last_ms', 'created_at')
                ->withMax('liveChatMessages as last_lc', 'created_at');

            match ($sort) {
                'oldest' => $query->orderByRaw('GREATEST(COALESCE(last_wa,0), COALESCE(last_ms,0), COALESCE(last_lc,0)) ASC')
                    ->orderBy('conversations.updated_at'),
                'waiting_longest' => $this->applyWaitingLongestSort($query),
                default => $query->orderByRaw('GREATEST(COALESCE(last_wa,0), COALESCE(last_ms,0), COALESCE(last_lc,0)) DESC')
                    ->orderByDesc('conversations.updated_at'),
            };
        }
    }

    /**
     * Apply waiting longest sort
     */
    private function applyWaitingLongestSort($query): void
    {
        $query->addSelect([
            'oldest_unread_wa' => function ($subQuery) {
                $subQuery->selectRaw('MIN(created_at)')
                    ->from('whatsapp_messages')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->where('direction', 'RECEIVED')
                    ->whereIn('status', ['initiated', 'sent', 'delivered']);
            },
            'oldest_unread_ms' => function ($subQuery) {
                $subQuery->selectRaw('MIN(created_at)')
                    ->from('messenger_messages')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->where('direction', 'RECEIVED')
                    ->whereIn('status', ['initiated', 'sent', 'delivered']);
            },
            'oldest_unread_lc' => function ($subQuery) {
                $subQuery->selectRaw('MIN(created_at)')
                    ->from('livechat_messages')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->where('sender_type', 'App\\Models\\ContactEntity')
                    ->whereIn('status', ['initiated', 'sent', 'delivered']);
            }
        ])
            ->orderByRaw('LEAST(COALESCE(oldest_unread_wa,999999999999), COALESCE(oldest_unread_ms,999999999999), COALESCE(oldest_unread_lc,999999999999)) ASC')
            ->orderBy('conversations.updated_at');
    }

    /**
     * Calculate conversation statistics (optimized - single query with conditional counts)
     */
    private function calculateConversationStatisticsOptimized(Workspace $workspace, $accessor, ?string $search): array
    {
        $workspaceId = $workspace->id;
        $accessorId = $accessor->id;
        $messengerFlag = env('MESSENGER_FEATURE_FLAG') == false ? 1 : 0;

        // Build search condition for contacts if search is provided
        $searchCondition = '';
        $searchBindings = [];
        if (!empty($search)) {
            $searchWithoutSpaces = str_replace(' ', '', $search);
            $searchCondition = "
                AND c.contact_id IN (
                    SELECT DISTINCT ct.id FROM contacts ct
                    LEFT JOIN identifiers ci ON ci.contact_id = ct.id
                    LEFT JOIN contact_attributes ca ON ca.contact_id = ct.id
                    LEFT JOIN attribute_definitions cad ON cad.id = ca.attribute_definition_id 
                    WHERE (
                        (ci.key = 'phone' AND REPLACE(ci.value, ' ', '') LIKE ?)
                        OR (cad.key IN ('first-name', 'last-name', 'display-name') AND ca.value LIKE ?)
                    )
                )
            ";
            $searchBindings = ['%' . $searchWithoutSpaces . '%', '%' . $search . '%'];
        }

        $sql = "
            SELECT
                -- Me count: assigned to me, not archived
                SUM(CASE
                    WHEN c.status != 'archived'
                    AND EXISTS (
                        SELECT 1 FROM conversation_agents ca
                        WHERE ca.conversation_id = c.id
                        AND ca.inbox_agent_id = ?
                        AND ca.removed_at IS NULL
                    )
                    THEN 1 ELSE 0
                END) as me_count,

                -- Unassigned count: not archived, not promotional, no agents assigned
                SUM(CASE
                    WHEN c.status != 'archived'
                    AND NOT EXISTS (
                        SELECT 1 FROM conversation_agents ca
                        WHERE ca.conversation_id = c.id
                        AND ca.removed_at IS NULL
                    )
                    AND (
                        NOT EXISTS (
                            SELECT 1 FROM whatsapp_messages wm
                            WHERE wm.conversation_id = c.id
                            AND wm.direction = 'SENT'
                            AND wm.type = 'template'
                        )
                        OR EXISTS (
                            SELECT 1 FROM whatsapp_messages wm2
                            WHERE wm2.conversation_id = c.id
                            AND wm2.direction = 'RECEIVED'
                        )
                    )
                    THEN 1 ELSE 0
                END) as unassigned_count,

                -- Archived count
                SUM(CASE WHEN c.status = 'archived' THEN 1 ELSE 0 END) as archived_count,

                -- Not replied count: has unread received messages
                SUM(CASE
                    WHEN c.status != 'archived'
                    AND (
                        EXISTS (
                            SELECT 1 FROM whatsapp_messages wm
                            WHERE wm.conversation_id = c.id
                            AND wm.direction = 'RECEIVED'
                            AND wm.status != 'read'
                        )
                        OR EXISTS (
                            SELECT 1 FROM messenger_messages mm
                            WHERE mm.conversation_id = c.id
                            AND mm.direction = 'RECEIVED'
                            AND mm.status IN ('initiated', 'sent', 'delivered')
                        )
                        OR EXISTS (
                            SELECT 1 FROM livechat_messages lm
                            WHERE lm.conversation_id = c.id
                            AND lm.sender_type = 'App\\\\Models\\\\ContactEntity'
                            AND lm.status IN ('initiated', 'sent', 'delivered')
                        )
                    )
                    THEN 1 ELSE 0
                END) as not_replied_count,

                -- Promotional count: template sent, no customer reply
                SUM(CASE
                    WHEN c.status != 'archived'
                    AND EXISTS (
                        SELECT 1 FROM whatsapp_messages wm
                        WHERE wm.conversation_id = c.id
                        AND wm.direction = 'SENT'
                        AND wm.type = 'template'
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM whatsapp_messages wm2
                        WHERE wm2.conversation_id = c.id
                        AND wm2.direction = 'RECEIVED'
                    )
                    THEN 1 ELSE 0
                END) as promotional_count

            FROM conversations c
            WHERE c.workspace_id = ?
            AND c.deleted_at IS NULL
            " . ($messengerFlag ? "AND c.platform != 'messenger'" : "") . "
            {$searchCondition}
        ";

        $bindings = array_merge(
            [$accessorId, $workspaceId],
            $searchBindings
        );

        $result = \DB::selectOne($sql, $bindings);

        return [
            ['filter' => 'me', 'num' => (int) ($result->me_count ?? 0)],
            ['filter' => 'unassigned', 'num' => (int) ($result->unassigned_count ?? 0)],
            ['status' => 'archived', 'num' => (int) ($result->archived_count ?? 0)],
            ['status' => 'not_replied', 'num' => (int) ($result->not_replied_count ?? 0)],
            ['status' => 'promotional', 'num' => (int) ($result->promotional_count ?? 0)],
        ];
    }

    /**
     * Calculate conversation statistics (legacy method - kept for backward compatibility)
     */
    private function calculateConversationStatistics(Workspace $workspace, $accessor, ?string $search): array
    {
        $baseQuery = $this->buildBaseConversationQuery($workspace);
        $this->applySearchFilter($baseQuery, $search);

        // Count for filter: me (including promotional - show all assigned to me)
        $meCount = (clone $baseQuery)
            ->where('status', '!=', Conversation::STATUS_ARCHIVED)
            ->where('status','!=',Conversation::STATUS_PENDING)
            ->whereHas('agents', function ($q) use ($accessor) {
                $q->where('inbox_agent_id', $accessor->id)
                    ->whereNull('conversation_agents.removed_at');
            })
            ->count();

        // Count for filter: unassigned (excluding promotional)
        $unassignedQuery = clone $baseQuery;
        $this->applyDefaultStatusFilter($unassignedQuery);
        $unassignedCount = $unassignedQuery
            ->whereDoesntHave('agents', function ($q) use ($accessor) {
                $q->where('inbox_agent_id', '<>', $accessor->id)
                    ->whereNull('conversation_agents.removed_at');
            })
            ->count();

        // Count for status: archived
        $archivedCount = (clone $baseQuery)
            ->where('status', Conversation::STATUS_ARCHIVED)
            ->count();

        // Count for status: not_replied
        $notRepliedQuery = clone $baseQuery;
        $this->applyNotRepliedFilter($notRepliedQuery);
        $notRepliedCount = $notRepliedQuery->count();

        // Count for status: promotional (template sent, no customer reply)
        $promotionalQuery = clone $baseQuery;
        $this->applyPromotionalFilter($promotionalQuery);
        $promotionalCount = $promotionalQuery->count();

        return [
            ['filter' => 'me', 'num' => $meCount],
            ['filter' => 'unassigned', 'num' => $unassignedCount],
            ['status' => 'archived', 'num' => $archivedCount],
            ['status' => 'not_replied', 'num' => $notRepliedCount],
            ['status' => 'promotional', 'num' => $promotionalCount],
        ];
    }

    /**
     * Refresh conversation count statistics
     * Returns counts for different filters and statuses
     */
    public function stats(Request $request, Workspace $workspace)
    {
        $search = $request->get('search', null);

        // Remove leading zero if search starts with 0
        if ($search && str_starts_with($search, '0')) {
            $search = ltrim($search, '0');
        }

        $accessor = $this->getAccessor($request);

        // Check authorization
        if (!$this->canAccessWorkspaceConversations($accessor, $workspace)) {
            return $this->errorResponse("Unauthorized to view conversations in this workspace.", null, 403);
        }

        // Calculate and return statistics using shared method
        $statistics = $this->calculateConversationStatistics($workspace, $accessor, $search);

        return $this->response(true, 'Conversation statistics retrieved successfully', $statistics);
    }


    public function getConversation(Request $request, Workspace $workspace, Conversation $conversation)
    {
        // Ensure the conversation belongs to a channel inside the workspace
        $channel = $workspace->channels()
            ->where('channel_id', $conversation->channel_id)
            ->first();

        if (!$channel) {
            return $this->response(
                success: false,
                message: "Conversation not found in this workspace.",
                statusCode: 404
            );
        }


        // Exclude conversation if platform is disabled via feature flags
        // if (
        //     ($channel->platform === Channel::MESSENGER_PLATFORM && !env('MESSENGER_FEATURE_FLAG'))
        // ) {
        //     return $this->response(
        //         success: false,
        //         message: "This platform is currently disabled.",
        //         statusCode: 403
        //     );
        // }


        return $this->response(
            message: "Conversation Retrieved Successfully",
            data: new ConversationDetails(
                $conversation,
                [
                    'lang' => $request->input('lang', "en"),
                    'translate' => $request->input('translate', false),
                    'last_message_id' => $request->input('id', null),
                    'limit' => $request->input('limit', 15),
                ]
            )
        );
    }



    public function startNewConversation(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'platform' => 'required|string|in:whatsapp,livechat,messenger',
            'channel_id' => 'required|uuid|exists:channels,id',
            'contact_id' => 'required|uuid|exists:contacts,id',
            'message' => 'nullable|string',
            'inbox_agent_id' => 'nullable|uuid|exists:users,id',
        ]);

        $contact = ContactEntity::findOrFail($validated['contact_id']);
        $channel = Channel::findOrFail($validated['channel_id']);
        //        $agent = $validated['inbox_agent_id'] ? User::find($validated['inbox_agent_id']) : null;

        try {
            $conversation = $this->startConversation(
                platform: $validated['platform'],
                channel: $channel,
                contact: $contact,
                message: $validated['message'] ?? null,
                workspaceId: $workspace->id
                //                inboxAgent: $agent,
            );

            return $this->response(true, 'Conversation started successfully.', $conversation);
        } catch (\Throwable $e) {
            return $this->response(false, 'Failed to start conversation.', ['error' => $e->getMessage()], 500);
        }
    }

    public function sendMessage(Request $request, Workspace $workspace, Conversation $conversation)
    {
        $channel = $conversation->channel;
        // Check feature flags for Live Chat and Messenger
        if (
            ($channel->platform === Channel::MESSENGER_PLATFORM && !env('MESSENGER_FEATURE_FLAG'))
        ) {
            return response()->json(['error' => 'This platform is currently disabled.'], 403);
        }

        return match ($channel->platform) {
            Channel::WHATSAPP_PLATFORM => $this->sendWhatsappMessage($request, $conversation),
            Channel::LIVECHAT_PLATFORM => $this->sendLiveChatMessage($request, $conversation),
            Channel::MESSENGER_PLATFORM => $this->sendMessengerMessage($request, $conversation),
            default => response()->json(['error' => 'Unsupported Platform'], 400),
        };
    }

    private function sendWhatsappMessage(Request $request, Conversation $conversation)
    {
        $channel = $conversation->channel;

        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        if (!$whatsappConfiguration || !$whatsappConfiguration->primary_whatsapp_phone_number_id) {
            return response()->json(['error' => 'WhatsApp Configuration is missing or incomplete'], 400);
        }
        // Add the `from` field to the request data
        $request->merge(['from' => (string) $whatsappConfiguration->primary_whatsapp_phone_number_id]);


        $contact = $conversation->contact;

        if (!$contact || !$contact->getPhoneNumberIdentifier()) {
            return $this->response(false, 'Conversation has an invalid contact.', null, 422);
        }

        $senderPhone = $contact->getPhoneNumberIdentifier();
        $request->merge(['to' => $senderPhone]);


        $request->merge(['conversation_id' => $conversation->id]);

        // Translate outgoing text message to conversation's detected language
        $this->translateOutgoingMessage($request, $conversation);

        // Check the message type and call the appropriate function
        $messageType = $request->input('type');

        if ($messageType === 'template') {
            try {
                $transaction = $this->prepareWalletTransactionForTemplate(
                    channel: $channel,
                    conversation: $conversation,
                    workspace: $conversation->workspace,
                    contact: $contact,
                    senderPhone: $senderPhone,
                    templateId: $request->get('template_id')
                );

                if ($transaction) {
                    $request->merge(['transaction_id' => $transaction->id]);
                }
            } catch (\Exception $e) {
                return $this->response(false, $e->getMessage(), null, 422);
            }
        }

        return match ($messageType) {
            'text' => $this->sendWhatsappTextMessage($request),
            'location' => $this->sendWhatsappLocationMessage($request),
            'template' => $this->sendWhatsappTemplateMessage($request),
            'image' => $this->sendWhatsappImageMessage($request),
            'video' => $this->sendWhatsappVideoMessage($request),
            'audio' => $this->sendWhatsappAudioMessage($request),
            'flow' => $this->sendWhatsappFlowMessage($request),
            'document' => $this->sendWhatsappDocumentMessage($request),
            'interactive' => $this->sendWhatsappInteractiveMessage($request),
            'files' => $this->sendWhatsappFilesMessage($request),
            'reaction' => $this->sendWhatsappReactionMessage($request),
            default => response()->json(['error' => 'Unsupported message type'], 400),
        };
    }


    private function sendWhatsappDocumentMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendDocumentMessage($request);
    }


    private function sendWhatsappFlowMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendFlowMessage($request);
    }

    private function sendWhatsappTextMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendTextMessage($request);
    }

    private function sendWhatsappLocationMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendLocationMessage($request);
    }

    private function sendWhatsappTemplateMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendTemplateMessage($request);
    }

    private function sendWhatsappImageMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendImageMessage($request);
    }

    private function sendWhatsappVideoMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendVideoMessage($request);
    }

    private function sendWhatsappAudioMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendAudioMessage($request);
    }

    private function sendWhatsappInteractiveMessage(Request $request)
    {
        // Delegate to WhatsApp interactive message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendInteractiveMessage($request);
    }

    private function sendWhatsappFilesMessage(Request $request)
    {
        // Delegate to WhatsApp files message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendFilesMessage($request);
    }

    private function sendWhatsappReactionMessage(Request $request)
    {
        // Delegate to WhatsApp files message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendReactionMessage($request);
    }

    private function sendLiveChatMessage(Request $request, Conversation $conversation)
    {
        try {
            $validator = Validator::make($request->all(), [
                'conversation_id' => 'required|uuid|exists:conversations,id',
                'type' => 'required|string|in:text,files,reaction',
                'message' => 'required_if:type,text|string',
                'reply_to_message_id' => 'nullable|uuid|exists:livechat_messages,id',
                'files' => 'required_if:type,files|array|min:1',
                'files.*.file' => 'required|file|max:10240', // 10MB max per file
                'files.*.type' => 'required|string|in:image,video,audio,document',
                'files.*.caption' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }
            $conversation->status = Conversation::STATUS_OPEN;
            $conversation->save();
            $type = $request->input('type');

            if ($type === 'text') {
                $message = $this->sendLiveChatTextMessage($request, $conversation);
                $this->liveChatMessageHandler->handleAgentIncomingMessage($message, $conversation);

                // Disable pre-chat form after sending text message
                // $this->disablePreChatFormForConversation($conversation);

                return $this->response(true, 'Message sent successfully', new ConversationMessage($message, Channel::LIVECHAT_PLATFORM));
            }

            if ($type === 'files') {
                $messages = $this->sendLiveChatFilesMessage($request, $conversation);
                $responseMessages = [];
                foreach ($messages as $message) {
                    $this->liveChatMessageHandler->handleAgentIncomingMessage($message, $conversation);
                    $responseMessages[] = new ConversationMessage($message, Channel::LIVECHAT_PLATFORM);
                }
                // Disable pre-chat form after sending text message
                // $this->disablePreChatFormForConversation($conversation);

                return $this->response(true, 'Messages sent successfully', ['messages' => $responseMessages, 'errors' => []]);
            }

            if ($type === 'reaction') {
                $message = $this->sendLiveChatReactionMessage($request, $conversation);
                return $this->response(true, 'Reaction sent successfully', $message);
            }

            return $this->response(false, 'Unsupported message type', null, 400);
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    private function sendLiveChatTextMessage(Request $request, Conversation $conversation)
    {
        try {
            $channel = $conversation->channel;
            $connector = $channel->connector;
            $liveChatConfiguration = LiveChatConfiguration::where('connector_id', $connector->id)->firstOrFail();
            $messageText = $request->input('message');
            $textMessage = LiveChatTextMessage::create([
                'text' => $messageText,
            ]);
            $message = $conversation->messages()->create([
                'channel_id' => $conversation->channel_id,
                'workspace_id' => $conversation->workspace_id,
                'widget_id' => $liveChatConfiguration->widget_id,
                'type' => 'text',
                'status' => 'sent',
                'agent_id' => auth('api')->user()->id ?? null,
                'direction' => LiveChatMessage::MESSAGE_STATUS_SENT,
                'content' => $request->input('message'),
                'sender_type' => Widget::class,
                'sender_id' => $liveChatConfiguration->widget_id,
                'messageable_type' => get_class($textMessage),
                'messageable_id' => $textMessage->id,
                'is_read' => false,
                'replied_to_message_id' => $request->has('context.message_id') ? $request->input('context.message_id') : null,
            ]);
            $this->saveMessageStatus($message->id, 'sent');
            return $message;
        } catch (Throwable $e) {
            throw $e;
            // return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    private function sendLiveChatReactionMessage(Request $request, Conversation $conversation)
    {
        $content = $request->input('content');
        $messageId = $content['message_id'];
        $emoji = $content['emoji'];

        $livechatMessage = LiveChatMessage::where('id', $messageId)
            ->where('conversation_id', $conversation->id)
            ->firstOrFail();
        if (empty($emoji)) {
            LiveChatReactionMessage::where('livechat_message_id', $messageId)
            ->where('direction', LiveChatMessage::MESSAGE_DIRECTION_SENT)
            ->delete();
        } else {
            LiveChatReactionMessage::updateOrCreate(
                [
                    'livechat_message_id' => $livechatMessage->id,
                    'direction' => LiveChatMessage::MESSAGE_DIRECTION_SENT
                ],
                [
                    'emoji' => $emoji,
                ]
            );
        }
        $this->liveChatMessageHandler->handleAgentReactionUpdate($livechatMessage, $emoji);

        return $livechatMessage;
    }



    private function sendLiveChatFilesMessage(Request $request, Conversation $conversation): array
    {
        $channel = $conversation->channel;
        $connector = $channel->connector;
        $liveChatConfiguration = LiveChatConfiguration::where('connector_id', $connector->id)->firstOrFail();

        $files = $request->file('files');
        $filesData = $request->input('files', []);
        $messages = [];

        foreach ($files as $index => $fileData) {
            $file = $fileData['file'] ?? null;
            if (!$file) {
                continue;
            }

            $caption = $filesData[$index]['caption'] ?? null;
            $fileType = $filesData[$index]['type'] ?? 'document';

            // Create the file message
            $fileMessage = LiveChatFileMessage::create([
                'caption' => $caption,
            ]);

            // Upload file to media collection
            $fileMessage
                ->addMedia($file)
                ->toMediaCollection('livechat_media', 'oss');

            // Create the message record
            $message = $conversation->messages()->create([
                'channel_id' => $conversation->channel_id,
                'workspace_id' => $conversation->workspace_id,
                'widget_id' => $liveChatConfiguration->widget_id,
                'type' => 'file',
                'status' => 'sent',
                'agent_id' => auth('api')->user()->id ?? null,
                'direction' => LiveChatMessage::MESSAGE_STATUS_SENT,
                'sender_type' => Widget::class,
                'sender_id' => $liveChatConfiguration->widget_id,
                'messageable_type' => LiveChatFileMessage::class,
                'messageable_id' => $fileMessage->id,
                'is_read' => false,
                'replied_to_message_id' => $request->input('reply_to_message_id'),
            ]);

            $this->saveMessageStatus($message->id, 'sent');
            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * Mark messages as read for a conversation
     *
     * @param Request $request
     * @param Workspace $workspace
     * @param Conversation $conversation
     * @return JsonResponse
     */
    public function markMessagesAsRead(Request $request, Workspace $workspace, Conversation $conversation)
    {
        try {
            // Ensure the conversation belongs to a channel inside the workspace
            if (!$workspace->channels()->where('channel_id', $conversation->channel_id)->exists()) {
                return $this->response(
                    success: false,
                    message: "Conversation not found in this workspace.",
                    statusCode: 404
                );
            }

            $channel = $conversation->channel;

            // Handle based on platform type
            return match ($channel->platform) {
                Channel::WHATSAPP_PLATFORM => $this->markWhatsappMessagesAsRead($channel, $conversation),
                Channel::LIVECHAT_PLATFORM => $this->markLiveChatMessagesAsRead($conversation),
                default => $this->response(false, 'Unsupported Platform', null, 400),
            };
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Mark WhatsApp messages as read
     *
     * @param Channel $channel
     * @param Conversation $conversation
     * @return JsonResponse
     */
    private function markWhatsappMessagesAsRead(Channel $channel, Conversation $conversation)
    {
        // Ensure we have the required data
        $contact = $conversation->contact;
        if (!$contact) {
            return $this->response(false, 'Contact information is missing for this conversation.', null, 400);
        }

        $phoneNumberId = $contact->getPhoneNumberIdentifier();
        if (!$phoneNumberId) {
            return $this->response(false, 'Valid phone number is missing for this contact.', null, 400);
        }

        // Get WhatsApp phone number linked to the channel
        $whatsappPhoneNumber = $channel->connector->whatsappConfiguration->whatsappPhoneNumber;
        if (!$whatsappPhoneNumber) {
            return $this->response(false, 'WhatsApp phone number configuration is missing.', null, 400);
        }

        // Get the consumer phone number
        $consumerPhoneNumberID = null;

        // Fetch WhatsAppConsumerPhoneNumber by phone number
        $consumerPhoneNumber = WhatsappConsumerPhoneNumber::where('phone_number', $phoneNumberId)
            ->where('whatsapp_business_account_id', $whatsappPhoneNumber->whatsapp_business_account_id)
            ->first();
        if ($consumerPhoneNumber) {
            $consumerPhoneNumberID = $consumerPhoneNumber->id;
        } else {
            return $this->response(false, 'WhatsApp consumer phone number not found.', null, 404);
        }


        // Fetch messages sent by the consumer and received by the business
        $messages = WhatsappMessage::where(function ($query) use ($consumerPhoneNumberID, $whatsappPhoneNumber) {
            $query->where('recipient_type', WhatsappPhoneNumber::class)
                ->where('recipient_id', $whatsappPhoneNumber->id)
                // ->where('sender_type', WhatsappConsumerPhoneNumber::class)
                ->where('sender_id', $consumerPhoneNumberID);
        })
            ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_RECEIVED)
            ->where('status', WhatsappMessage::MESSAGE_STATUS_DELIVERED)
            ->get();

        $count = 0;
        foreach ($messages as $message) {
            // Mark message as read via API
            $this->markWhatsappMessageAsReadApi($whatsappPhoneNumber, $message->id);

            // Update the message status to 'read' in the database
            $message->status = WhatsappMessage::MESSAGE_STATUS_READ;
            $message->save();

            // Save the message status update
            $this->saveWhatsappMessageStatus($message->id, WhatsappMessage::MESSAGE_STATUS_READ);
            $count++;
        }

        return $this->response(
            true,
            $count > 0 ? "{$count} WhatsApp messages marked as read." : "No unread WhatsApp messages found.",
            ['marked_count' => $count]
        );
    }

    /**
     * Call WhatsApp API to mark a message as read
     *
     * @param WhatsappPhoneNumber $whatsappPhoneNumber
     * @param string $messageId
     * @return void
     */
    private function markWhatsappMessageAsReadApi($whatsappPhoneNumber, $messageId)
    {
        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        if ($whatsappBusinessAccount->name == 'Dreams SMS') {
            $accessToken = Meta::ACCESS_TOKEN;
        } else {
            // Use the trait method to get a valid access token
            $accessToken = $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);
        }

        if (!$accessToken) {
            Log::error('Failed to get a valid access token for marking WhatsApp message as read');
            return;
        }

        // Call WhatsApp API to mark the message as read
        $response = Http::withToken($accessToken)
            ->post("https://graph.facebook.com/v20.0/{$whatsappPhoneNumber->id}/messages", [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $messageId,
            ]);

        if (!$response->successful()) {
            Log::error('Failed to mark WhatsApp message as read via API', [
                'message_id' => $messageId,
                'status_code' => $response->status(),
                'response' => $response->json()
            ]);
        }
    }

    /**
     * Save WhatsApp message status
     *
     * @param string $messageId
     * @param string $status
     */
    private function saveWhatsappMessageStatus(string $messageId, string $status): void
    {
        WhatsappMessageStatus::create([
            'whatsapp_message_id' => $messageId,
            'status' => $status,
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Mark LiveChat messages as read
     *
     * @param Conversation $conversation
     * @return JsonResponse
     */
    private function markLiveChatMessagesAsRead(Conversation $conversation)
    {
        // Get unread messages for the conversation
        $unreadMessages = $conversation->messages()
            ->where('status', '!=', LiveChatMessage::MESSAGE_STATUS_READ)
            ->get();

        $count = 0;

        foreach ($unreadMessages as $message) {
            // Skip messages sent by the current user (already "read")
            if ($message->sender_type != ContactEntity::class) {
                continue;
            }

            $message->markAsRead();
            $count++;
            $this->liveChatMessageHandler->handleAgentStatusUpdate($message, LiveChatMessage::MESSAGE_STATUS_READ);
        }

        return $this->response(
            true,
            $count > 0 ? "{$count} messages marked as read." : "No unread messages found.",
            ['marked_count' => $count]
        );
    }

    /**
     * Mark messages as deliverd for a conversation
     *
     * @param Request $request
     * @param Workspace $workspace
     * @param Conversation $conversation
     * @return JsonResponse
     */
    public function markMessagesAsDelivered(Request $request, Workspace $workspace, Conversation $conversation)
    {
        try {
            // Ensure the conversation belongs to a channel inside the workspace
            if (!$workspace->channels()->where('channel_id', $conversation->channel_id)->exists()) {
                return $this->response(
                    success: false,
                    message: "Conversation not found in this workspace.",
                    statusCode: 404
                );
            }

            $channel = $conversation->channel;

            // Handle based on platform type
            return match ($channel->platform) {
                Channel::LIVECHAT_PLATFORM => $this->markLiveChatMessagesAsDeliverd($conversation),
                default => $this->response(false, 'Unsupported Platform', null, 400),
            };
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Mark LiveChat messages as read
     *
     * @param Conversation $conversation
     * @return JsonResponse
     */
    private function markLiveChatMessagesAsDeliverd(Conversation $conversation)
    {
        // Get unread messages for the conversation
        $unreadMessages = $conversation->messages()
            ->where('status', '==', LiveChatMessage::MESSAGE_STATUS_SENT)
            ->get();

        $count = 0;

        foreach ($unreadMessages as $message) {
            // Skip messages sent by the current user (already "read")
            if ($message->sender_type != ContactEntity::class) {
                continue;
            }

            $message->markAsDeliverd();
            $count++;
            $this->liveChatMessageHandler->handleAgentStatusUpdate($message, LiveChatMessage::MESSAGE_STATUS_DELIVERED);
        }

        return $this->response(
            true,
            $count > 0 ? "{$count} messages marked as read." : "No unread messages found.",
            ['marked_count' => $count]
        );
    }

    /**
     * Get notes for a conversation
     *
     * @param Request $request
     * @param Workspace $workspace
     * @param Conversation $conversation
     * @return JsonResponse
     */
    public function getConversationNotes(Request $request, Workspace $workspace, Conversation $conversation)
    {
        try {
            // Ensure the conversation belongs to a channel inside the workspace
            if (!$workspace->channels()->where('channel_id', $conversation->channel_id)->exists()) {
                return $this->response(
                    success: false,
                    message: "Conversation not found in this workspace.",
                    statusCode: 404
                );
            }

            // Fetch notes with user information
            $notes = $conversation->notes()->with('user')->latest()->get();

            return $this->response(
                true,
                "Conversation notes retrieved successfully",
                $notes->map(fn($note) => new ConversationNote($note))
            );
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Add a note to a conversation
     *
     * @param Request $request
     * @param Workspace $workspace
     * @param Conversation $conversation
     * @return JsonResponse
     */
    public function addConversationNote(Request $request, Workspace $workspace, Conversation $conversation)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:10000',
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            // Ensure the conversation belongs to a channel inside the workspace
            if (!$workspace->channels()->where('channel_id', $conversation->channel_id)->exists()) {
                return $this->response(
                    success: false,
                    message: "Conversation not found in this workspace.",
                    statusCode: 404
                );
            }

            $authenticatedUser = auth('api')->user();

            $user = User::find($authenticatedUser->getAuthIdentifier());

            // Create the note
            $note = $conversation->notes()->create([
                'user_id' => $user->id,
                'content' => $request->input('content'),
            ]);

            // Load the user relationship
            $note->load('user');

            return $this->response(
                true,
                "Note added successfully",
                new ConversationNote($note)
            );
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Heartbeat to maintain agent's active status on a conversation
     */
    public function conversationHeartbeat(Request $request, Workspace $workspace, Conversation $conversation)
    {
        try {
            // Ensure the conversation belongs to a channel inside the workspace
            if (!$workspace->channels()->where('channel_id', $conversation->channel_id)->exists()) {
                return $this->response(
                    success: false,
                    message: "Conversation not found in this workspace.",
                    statusCode: 404
                );
            }

            // Only track for LiveChat
            if ($conversation->channel->platform === Channel::LIVECHAT_PLATFORM) {
                // Update agent viewing status
                $this->liveChatMessageHandler->trackAgentView($conversation->id, Auth::id());
            }

            return $this->response(
                true,
                "Heartbeat updated successfully",
                ['timestamp' => now()]
            );
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }


    /**
     * Close a conversation
     *
     * @param Request $request
     * @param Workspace $workspace
     * @param Conversation $conversation
     * @return JsonResponse
     */
    public function closeConversation(Request $request, Workspace $workspace, Conversation $conversation)
    {
        try {
            // Ensure the conversation belongs to a channel inside the workspace
            if (!$workspace->channels()->where('channel_id', $conversation->channel_id)->exists()) {
                return $this->response(
                    success: false,
                    message: "Conversation not found in this workspace.",
                    statusCode: 404
                );
            }

            // Check if conversation is already closed/ended
            if ($conversation->status === Conversation::STATUS_ARCHIVED) {
                return $this->response(
                    success: false,
                    message: "Conversation is already closed.",
                    statusCode: 400
                );
            }

            // Update conversation status to ended
            $conversation->status = Conversation::STATUS_ARCHIVED;
            $conversation->save();
            // Optional: Add a system note about conversation closure
            $conversation->notes()->create([
                'user_id' => auth('api')->user()->id,
                'content' => 'Conversation closed by ' . auth('api')->user()->name,
                'is_system_note' => true
            ]);

            // Handle platform-specific close actions
            $channel = $conversation->channel;
            if ($channel->platform === Channel::LIVECHAT_PLATFORM) {
                // Handle LiveChat-specific close actions if needed
                $this->liveChatMessageHandler->handleConversationClosed($conversation, 'agent');
            }

            return $this->response(
                true,
                "Conversation closed successfully",
                new ConversationDetails($conversation)
            );
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Reopen a closed conversation
     *
     * @param Request $request
     * @param Workspace $workspace
     * @param Conversation $conversation
     * @return JsonResponse
     */
    public function reopenConversation(Request $request, Workspace $workspace, Conversation $conversation)
    {
        try {
            // Ensure the conversation belongs to a channel inside the workspace
            if (!$workspace->channels()->where('channel_id', $conversation->channel_id)->exists()) {
                return $this->response(
                    success: false,
                    message: "Conversation not found in this workspace.",
                    statusCode: 404
                );
            }

            // Check if conversation is already open/active
            if ($conversation->status !== Conversation::STATUS_CLOSED) {
                return $this->response(
                    success: false,
                    message: "Conversation is already active.",
                    statusCode: 400
                );
            }

            // Update conversation status to active
            $conversation->status = Conversation::STATUS_ACTIVE;
            $conversation->save();

            // Optional: Add a system note about conversation reopening
            $conversation->notes()->create([
                'user_id' => Auth::id(),
                'content' => 'Conversation reopened by ' . Auth::user()->name,
                'is_system_note' => true
            ]);

            // Handle platform-specific reopen actions
            $channel = $conversation->channel;
            if ($channel->platform === Channel::LIVECHAT_PLATFORM) {
                // Handle LiveChat-specific reopen actions if needed
                $this->liveChatMessageHandler->handleConversationReopened($conversation);
            }

            return $this->response(
                true,
                "Conversation reopened successfully",
                new ConversationDetails($conversation)
            );
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    // 👇 Assign agent
    public function assignAgent(Request $request, Workspace $workspace, Conversation $conversation, User $user)
    {
        if (!$user->isInboxAgent()) {
            return $this->response(false, "User is not a valid inbox agent.", null, 403);
        }

        $assigned = $this->assignInboxAgentToConversation($user, $conversation);

        if (!$assigned) {
            return $this->response(false, "Agent is already assigned.", null, 409);
        }

        return $this->response(true, "Agent assigned successfully.");
    }

    // 👇 Remove agent
    public function removeAgent(Request $request, Workspace $workspace, Conversation $conversation, User $user)
    {
        if (!$user->isInboxAgent()) {
            return $this->response(false, "User is not a valid inbox agent.", null, 403);
        }

        $removed = $this->removeInboxAgentFromConversation($user, $conversation);

        if (!$removed) {
            return $this->response(false, "Agent not currently assigned.", null, 409);
        }

        return $this->response(true, "Agent removed successfully.");
    }

    private function sendMessengerMessage(Request $request, Conversation $conversation)
    {
        $channel = $conversation->channel;

        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $messengerConfiguration = $connector->messengerConfiguration;

        if (!$messengerConfiguration || !$messengerConfiguration->meta_page_id) {
            return response()->json(['error' => 'Messenger Configuration is missing or incomplete'], 400);
        }

        // Check the message type and call the appropriate function
        $messageType = $request->input('type');

        // Add the `from` field to the request data
        $request->merge(['from' => (string) $messengerConfiguration->meta_page_id]);

        $psid = $conversation->contact->messengerConsumers()->first()->psid;

        $request->merge(['to' => $psid]);

        return match ($messageType) {
            'text' => $this->sendMessengerTextMessage($request),
            //            'location' => $this->sendWhatsappLocationMessage($request),
            //            'template' => $this->sendWhatsappTemplateMessage($request),
            //            'image' => $this->sendWhatsappImageMessage($request),
            //            'video' => $this->sendWhatsappVideoMessage($request),
            //            'audio' => $this->sendWhatsappAudioMessage($request),
            default => response()->json(['error' => 'Unsupported message type'], 400),
        };
        //
        //
        //        // Check for `contact_id` or `to` and ensure `to` is set
        //        if ($request->has('contact_id')) {
        //            $contact = ContactEntity::find($request->input('contact_id'));
        //
        //            if (!$contact || !$contact->getPhoneNumberIdentifier()) {
        //                return $this->response(false, 'Contact is not a valid contact.', null, 422);
        //            }
        //
        //            // Merge the phone number from `contact_id` into the request
        //            $request->merge(['to' => $contact->getPhoneNumberIdentifier()]);
        //        }
        //
        //        if (!$request->has('to')) {
        //            return $this->response(false, 'The "to" field is required.', null, 422);
        //        }
        //
        //        // Check the message type and call the appropriate function
        //        $messageType = $request->input('type');
        //
        //        return match ($messageType) {
        //            'text' => $this->sendWhatsappTextMessage($request),
        //            'location' => $this->sendWhatsappLocationMessage($request),
        //            'template' => $this->sendWhatsappTemplateMessage($request),
        //            'image' => $this->sendWhatsappImageMessage($request),
        //            'video' => $this->sendWhatsappVideoMessage($request),
        //            'audio' => $this->sendWhatsappAudioMessage($request),
        //            default => response()->json(['error' => 'Unsupported message type'], 400),
        //        };
    }

    private function sendMessengerTextMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $messengerController = app(MessengerMessageController::class);

        return $messengerController->sendTextMessage($request);
    }


    /**
     * Switch the workspace of a conversation
     *
     * @param Request $request
     * @param Workspace $workspace The current workspace context
     * @param Conversation $conversation The conversation to switch
     * @return JsonResponse
     */
    public function switchWorkspace(Request $request, Workspace $workspace, Conversation $conversation)
    {
        try {
            $validated = $request->validate([
                'new_workspace_id' => 'required|uuid|exists:workspaces,id',
            ]);

            $newWorkspace = Workspace::findOrFail($validated['new_workspace_id']);

            // 1. Ensure the new workspace belongs to the same organization
            if ($workspace->organization_id !== $newWorkspace->organization_id) {
                return $this->response(
                    false,
                    "Target workspace must belong to the same organization.",
                    null,
                    403
                );
            }

            // 2. Ensure the conversation’s channel is available in the target workspace
            $channel = $conversation->channel;
            if (!$newWorkspace->channels()->where('channels.id', $channel->id)->exists()) {
                return $this->response(
                    false,
                    "Channel not available in the target workspace.",
                    null,
                    422
                );
            }


            // 4. Switch conversation workspace
            $conversation->workspace_id = $newWorkspace->id;
            $conversation->save();

            $authenticatedUser = auth('api')->user();

            $user = User::find($authenticatedUser->getAuthIdentifier());

            // Optional: add system note
            $conversation->notes()->create([
                'user_id' => $user->id,
                'content' => "Conversation moved to workspace {$newWorkspace->name} by " . $user->name,
                'is_system_note' => true,
            ]);

            return $this->response(
                true,
                "Conversation moved successfully.",
                new \App\Http\Responses\Conversation($conversation)
            );
        } catch (\Throwable $e) {
            return $this->response(false, 'Failed to switch workspace: ' . $e->getMessage(), null, 500);
        }
    }

    public function aiSuggestReply(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        try {
            $lang = $request->input('lang', 'auto');
            $suggestion = $this->suggestReply($conversation, $lang);

            return $this->response(true, "AI suggestion generated", [
                'suggestion' => $suggestion,
            ]);
        } catch (Throwable $e) {
            return $this->response(false, "Failed to suggest reply", ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Improve writing for a draft text
     */
    public function aiImproveWriting(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        $request->validate(['text' => 'required|string|max:5000']);

        try {
            $lang = $request->input('lang', 'auto');
            $text = $request->input('text');
            $improved = $this->improveWriting($workspace, $text, $lang);

            return $this->response(true, "AI improvement generated", [
                'improved_text' => $improved,
            ]);
        } catch (Throwable $e) {
            return $this->response(false, "Failed to improve writing", ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Summarize a conversation
     */
    public function aiSummarize(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        try {
            $lang = $request->input('lang', 'auto');
            $summary = $this->summarizeConversation($conversation, $lang);

            return $this->response(true, "Conversation summarized", [
                'summary' => $summary,
            ]);
        } catch (Throwable $e) {
            return $this->response(false, "Failed to summarize conversation", ['error' => $e->getMessage()], 500);
        }
    }



    private function disablePreChatFormForConversation(Conversation $conversation): void
    {
        try {
            $channel = $conversation->channel;

            if (!$channel) {
                Log::warning('No channel found for conversation', ['conversation_id' => $conversation->id]);
                return;
            }

            // Update all pre-chat forms for this channel to disabled
            $affected = PreChatForm::where('channel_id', $channel->id)
                ->update(['enabled' => false]);

            if ($affected > 0) {
                Log::info('Pre-chat form(s) disabled after agent message', [
                    'conversation_id' => $conversation->id,
                    'channel_id' => $channel->id,
                    'forms_disabled' => $affected,
                    'agent_id' => auth('api')->user()->id ?? null
                ]);
            }
        } catch (\Exception $e) {
            // Don't break the main flow if this fails
            Log::error('Failed to disable pre-chat form', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Translate outgoing message to the conversation's detected language.
     *
     * @param Request $request
     * @param Conversation $conversation
     * @return void
     */
    private function translateOutgoingMessage(Request $request, Conversation $conversation): void
    {
        try {
            // Only translate text messages
            $messageType = $request->input('type');
            if ($messageType !== 'text') {
                return;
            }

            // Get the conversation's detected language
            $targetLanguage = $conversation->detected_language;
            if (!$targetLanguage) {
                return;
            }

            // Get the text body
            $textBody = $request->input('text.body');
            if (!$textBody) {
                return;
            }

            // Check if organization has auto-translation enabled
            $organization = $conversation->workspace->organization ?? null;
            if (!$organization || !$organization->isAutoTranslationEnabled()) {
                return;
            }

            // Detect the language of the outgoing message
            $languageDetectionService = app(\App\Services\LanguageDetectionService::class);
            $sourceLanguage = $languageDetectionService->detect($textBody);

            // Skip translation if source language is the same as target language
            if ($sourceLanguage && $sourceLanguage === $targetLanguage) {
                return;
            }

            // Prepare wallet transaction for translation billing
            $transaction = $this->prepareWalletTransactionForTranslation($conversation->workspace);

            // Translate the message using ConversationAIFeatures trait
            $translatedText = $this->translateText($conversation->workspace, $textBody, $targetLanguage);

            if ($translatedText && $translatedText !== $textBody) {
                // Update the request with translated text
                $request->merge([
                    'text' => [
                        'body' => $translatedText,
                        'preview_url' => $request->input('text.preview_url'),
                    ],
                    'original_text' => $textBody,
                    'translated_to' => $targetLanguage,
                    'translation_transaction_id' => $transaction?->id,
                ]);
            } else {
                // Translation not needed or failed, release funds if reserved
                if ($transaction) {
                    $this->releaseFunds($transaction);
                }
            }
        } catch (\Exception $e) {
            // Don't break the main flow if translation fails
            Log::error('Failed to translate outgoing message', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
