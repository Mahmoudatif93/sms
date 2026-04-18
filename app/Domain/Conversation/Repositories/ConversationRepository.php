<?php

namespace App\Domain\Conversation\Repositories;

use App\Domain\Conversation\DTOs\ConversationFilterDTO;
use App\Domain\Conversation\DTOs\ConversationStatsDTO;
use App\Models\ContactEntity;
use App\Models\Conversation;
use App\Models\WhatsappMessage;
use App\Models\Workspace;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConversationRepository implements ConversationRepositoryInterface
{
    public function __construct(
        private Conversation $model
    ) {}

    public function getAll(Workspace $workspace, ConversationFilterDTO $filters, $accessor): LengthAwarePaginator
    {
        $query = $this->buildBaseQuery($workspace);

        $this->applySearchFilter($query, $filters->search);
        $this->applyStatusFilter($query, $filters->status, $filters->filter);
        $this->applyAssignedToFilter($query, $filters->filter, $accessor);

        $query->with([
            'latestWhatsappMessage',
            'latestMessengerMessage',
            'latestLiveChatMessage',
            'contact.identifiers',
            'contact.attributes.attributeDefinition'
        ]);

        $this->applySorting($query, $filters->sort);

        return $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    public function findById(string $id): ?Conversation
    {
        return $this->model->find($id);
    }

    public function findByIdInWorkspace(string $id, Workspace $workspace): ?Conversation
    {
        return $workspace->conversations()->where('id', $id)->first();
    }

    public function create(array $data): Conversation
    {
        return $this->model->create($data);
    }

    public function update(Conversation $conversation, array $data): Conversation
    {
        $conversation->update($data);
        return $conversation->fresh();
    }

    public function updateStatus(Conversation $conversation, string $status): Conversation
    {
        $conversation->status = $status;
        $conversation->save();
        return $conversation;
    }

    public function getStatistics(Workspace $workspace, $accessor, ?string $search = null): ConversationStatsDTO
    {
        $workspaceId = $workspace->id;
        $accessorId = $accessor->id;
        $messengerFlag = env('MESSENGER_FEATURE_FLAG') == false ? 1 : 0;

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

                SUM(CASE WHEN c.status = 'archived' THEN 1 ELSE 0 END) as archived_count,

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

        $bindings = array_merge([$accessorId, $workspaceId], $searchBindings);
        $result = DB::selectOne($sql, $bindings);

        return ConversationStatsDTO::fromDatabaseResult($result);
    }

    public function getActiveConversation(string $contactId, string $platform, string $channelId): ?Conversation
    {
        return $this->model
            ->where('contact_id', $contactId)
            ->where('platform', $platform)
            ->where('channel_id', $channelId)
            ->whereNotIn('status', [Conversation::STATUS_ARCHIVED, Conversation::STATUS_CLOSED])
            ->first();
    }

    public function belongsToWorkspace(Conversation $conversation, Workspace $workspace): bool
    {
        return $workspace->channels()
            ->where('channel_id', $conversation->channel_id)
            ->exists();
    }

    private function buildBaseQuery(Workspace $workspace)
    {
        return $workspace->conversations();
    }

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
                    })->where('value', 'like', '%' . $search . '%');
                });
            });
        });
    }

    private function applyStatusFilter($query, ?string $status, ?string $filter = null): void
    {
        match ($status) {
            'archived' => $query->where('status', Conversation::STATUS_ARCHIVED),
            'not_replied' => $this->applyNotRepliedFilter($query),
            'promotional' => $this->applyPromotionalFilter($query),
            default => $filter === 'me'
                ? $query->where('status', '!=', Conversation::STATUS_ARCHIVED)
                : $this->applyDefaultStatusFilter($query),
        };
    }

    private function applyDefaultStatusFilter($query): void
    {
        $query->where('status', '!=', Conversation::STATUS_ARCHIVED)
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

    private function applyPromotionalFilter($query): void
    {
        $query->where('status', '!=', Conversation::STATUS_ARCHIVED)
            ->whereHas('whatsappMessages', function ($subQ) {
                $subQ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_SENT)
                    ->where('type', WhatsappMessage::MESSAGE_TYPE_TEMPLATE);
            })
            ->whereDoesntHave('whatsappMessages', function ($subQ) {
                $subQ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_RECEIVED);
            });
    }

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

    private function applySorting($query, string $sort): void
    {
        $hasLastMessageAt = Schema::hasColumn('conversations', 'last_message_at');

        if ($hasLastMessageAt) {
            match ($sort) {
                'oldest' => $query->orderBy('conversations.last_message_at', 'asc')
                    ->orderBy('conversations.updated_at', 'asc'),
                'waiting_longest' => $this->applyWaitingLongestSort($query),
                default => $query->orderBy('conversations.last_message_at', 'desc')
                    ->orderByDesc('conversations.updated_at'),
            };
        } else {
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
}
