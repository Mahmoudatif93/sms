<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;

/**
 * @OA\Schema(
 *     title="WhatsappConversationAnalytics",
 *     description="Schema for WhatsApp Conversation Analytics",
 *     @OA\Property(
 *         property="all_conversations",
 *         ref="#/components/schemas/ConversationAnalyticsAllConversations"
 *     ),
 *     @OA\Property(
 *         property="free_conversations",
 *         ref="#/components/schemas/ConversationAnalyticsFreeConversations"
 *     ),
 *     @OA\Property(
 *         property="paid_conversations",
 *         ref="#/components/schemas/ConversationAnalyticsPaidConversations"
 *     ),
 *     @OA\Property(
 *         property="approximate_charges",
 *         ref="#/components/schemas/ConversationAnalyticsApproximateCharges"
 *     )
 * )
 */
class WhatsappConversationAnalytics extends DataInterface
{

    public ConversationAnalyticsApproximateCharges $approximate_charges;
    public ConversationAnalyticsAllConversations $all_conversations;

    public ConversationAnalyticsPaidConversations $paid_conversations;

    public ConversationAnalyticsFreeConversations $free_conversations;


    public function __construct(array $dataPoints)
    {
        $counters = $this->updateFromDataPoints($dataPoints);
        $this->all_conversations = new ConversationAnalyticsAllConversations($counters['All Conversations']);
        $this->free_conversations = new ConversationAnalyticsFreeConversations($counters['Free Conversations']);
        $this->paid_conversations = new ConversationAnalyticsPaidConversations($counters['Paid Conversations']);
        $this->approximate_charges = new ConversationAnalyticsApproximateCharges($counters['Approximate Charges']);
    }

    public function updateFromDataPoints(array $dataPoints): array
    {
        $counters = [
            'All Conversations' => [
                'Total' => 0,
                'MARKETING' => 0,
                'UTILITY' => 0,
                'AUTHENTICATION' => 0,
                'AUTHENTICATION_INTERNATIONAL' => 0,
                'SERVICE' => 0,
            ],
            'Paid Conversations' => [
                'Total' => 0,
                'MARKETING' => 0,
                'UTILITY' => 0,
                'AUTHENTICATION' => 0,
                'AUTHENTICATION_INTERNATIONAL' => 0,
                'SERVICE' => 0,
            ],
            'Free Conversations' => [
                'Total' => 0,
                'FREE_TIER' => 0,
                'FREE_ENTRY_POINT' => 0
            ],
            'Approximate Charges' => [
                'Total' => 0,
                'MARKETING' => 0,
                'UTILITY' => 0,
                'AUTHENTICATION' => 0,
                'AUTHENTICATION_INTERNATIONAL' => 0,
                'SERVICE' => 0,
            ]
        ];

        foreach ($dataPoints as $point) {
            $this->updateCounters($point, $counters);
        }

        return $counters;
    }

    private function updateCounters($point, array &$counters): void
    {
        $category = strtoupper($point->conversation_category ?? 'UNKNOWN');
        $conversationType = strtoupper($point->conversation_type ?? 'UNKNOWN');

        // Update All Conversations
        $counters['All Conversations']['Total'] += $point->conversation;
        if (isset($counters['All Conversations'][$category])) {
            $counters['All Conversations'][$category] += $point->conversation;
        }

        // Update Free Conversations
        if ($conversationType === 'FREE_TIER' || $conversationType === 'FREE_ENTRY_POINT') {
            $counters['Free Conversations']['Total'] += $point->conversation;
            if (isset($counters['Free Conversations'][$conversationType])) {
                $counters['Free Conversations'][$conversationType] += $point->conversation;
            }
        }

        // Update Paid Conversations
        if ($conversationType !== 'FREE_TIER' && $conversationType !== 'FREE_ENTRY_POINT') {
            $counters['Paid Conversations']['Total'] += $point->conversation;
            if (isset($counters['Paid Conversations'][$conversationType])) {
                $counters['Paid Conversations'][$conversationType] += $point->conversation;
            }
        }

        // Update Approximate Charges
        if (isset($counters['Approximate Charges'][$category])) {
            $counters['Approximate Charges'][$category] += $point->cost;
        }
        $counters['Approximate Charges']['Total'] += $point->cost;
    }
}
