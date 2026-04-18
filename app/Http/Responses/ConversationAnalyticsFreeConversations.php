<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;

/**
 * @OA\Schema(
 *     title="ConversationAnalyticsFreeConversations",
 *     description="Schema for Free Conversations Analytics",
 *     @OA\Property(
 *         property="total",
 *         type="integer",
 *         description="Total number of free conversations",
 *         example=3
 *     ),
 *     @OA\Property(
 *         property="freeTier",
 *         type="integer",
 *         description="Number of free tier conversations",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="freeEntryPoint",
 *         type="integer",
 *         description="Number of free entry point conversations",
 *         example=1
 *     ),
 *     required={"total", "freeTier", "freeEntryPoint"}
 * )
 */
class ConversationAnalyticsFreeConversations extends DataInterface
{
    public int $total = 0;
    public int $freeTier = 0;
    public int $freeEntryPoint = 0;

    public function __construct(array $free_conversations)
    {
        $this->total = $free_conversations['Total'];
        $this->freeTier = $free_conversations['FREE_TIER'];
        $this->freeEntryPoint = $free_conversations['FREE_ENTRY_POINT'];
    }
}
