<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;

/**
 * @OA\Schema(
 *     title="ConversationAnalyticsPaidConversations",
 *     description="Schema for Paid Conversations Analytics",
 *     @OA\Property(
 *         property="total",
 *         type="integer",
 *         description="Total number of paid conversations",
 *         example=4
 *     ),
 *     @OA\Property(
 *         property="marketing",
 *         type="integer",
 *         description="Number of marketing paid conversations",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="utility",
 *         type="integer",
 *         description="Number of utility paid conversations",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="authentication",
 *         type="integer",
 *         description="Number of authentication paid conversations",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="authenticationInternational",
 *         type="integer",
 *         description="Number of international authentication paid conversations",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="service",
 *         type="integer",
 *         description="Number of service paid conversations",
 *         example=0
 *     ),
 *     required={"total", "marketing", "utility", "authentication", "authenticationInternational", "service"}
 * )
 */
class ConversationAnalyticsPaidConversations extends DataInterface
{
    public int $total = 0;
    public int $marketing = 0;
    public int $utility = 0;
    public int $authentication = 0;
    public int $authenticationInternational = 0;
    public int $service = 0;

    public function __construct(array $paid_conversations)
    {
        $this->total = $paid_conversations['Total'];
        $this->marketing = $paid_conversations['MARKETING'];
        $this->utility = $paid_conversations['UTILITY'];
        $this->authentication = $paid_conversations['AUTHENTICATION'];
        $this->authenticationInternational = $paid_conversations['AUTHENTICATION_INTERNATIONAL'];
        $this->service = $paid_conversations['SERVICE'];
    }
}
