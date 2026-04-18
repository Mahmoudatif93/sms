<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;

/**
 * @OA\Schema(
 *     title="ConversationAnalyticsAllConversations",
 *     description="Schema for All Conversations Analytics",
 *     @OA\Property(
 *         property="total",
 *         type="integer",
 *         description="Total number of all conversations",
 *         example=10
 *     ),
 *     @OA\Property(
 *         property="marketing",
 *         type="integer",
 *         description="Number of marketing conversations",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="utility",
 *         type="integer",
 *         description="Number of utility conversations",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="authentication",
 *         type="integer",
 *         description="Number of authentication conversations",
 *         example=3
 *     ),
 *     @OA\Property(
 *         property="authenticationInternational",
 *         type="integer",
 *         description="Number of international authentication conversations",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="service",
 *         type="integer",
 *         description="Number of service conversations",
 *         example=3
 *     ),
 *     required={"total", "marketing", "utility", "authentication", "authenticationInternational", "service"}
 * )
 */
class ConversationAnalyticsAllConversations extends DataInterface
{
    public int $total;
    public int $marketing;
    public int $utility;
    public int $authentication;
    public int $authenticationInternational;
    public int $service;

    public function __construct(array $all_conversations)
    {
        $this->total = $all_conversations['Total'];
        $this->marketing = $all_conversations['MARKETING'];
        $this->utility = $all_conversations['UTILITY'];
        $this->authentication = $all_conversations['AUTHENTICATION'];
        $this->authenticationInternational = $all_conversations['AUTHENTICATION_INTERNATIONAL'];
        $this->service = $all_conversations['SERVICE'];
    }
}
