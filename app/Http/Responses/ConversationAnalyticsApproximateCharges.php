<?php

namespace App\Http\Responses;

/**
 * @OA\Schema(
 *     title="ConversationAnalyticsApproximateCharges",
 *     description="Schema for Approximate Charges Analytics",
 *     @OA\Property(
 *         property="total",
 *         type="string",
 *         description="Total approximate charges",
 *         example="$0.00"
 *     ),
 *     @OA\Property(
 *         property="marketing",
 *         type="string",
 *         description="Approximate charges for marketing conversations",
 *         example="$0.00"
 *     ),
 *     @OA\Property(
 *         property="utility",
 *         type="string",
 *         description="Approximate charges for utility conversations",
 *         example="$0.00"
 *     ),
 *     @OA\Property(
 *         property="authentication",
 *         type="string",
 *         description="Approximate charges for authentication conversations",
 *         example="$0.00"
 *     ),
 *     @OA\Property(
 *         property="authenticationInternational",
 *         type="string",
 *         description="Approximate charges for international authentication conversations",
 *         example="$0.00"
 *     ),
 *     @OA\Property(
 *         property="service",
 *         type="string",
 *         description="Approximate charges for service conversations",
 *         example="$0.00"
 *     ),
 *     required={"total", "marketing", "utility", "authentication", "authenticationInternational", "service"}
 * )
 */
class ConversationAnalyticsApproximateCharges
{
    public string $total;
    public string $marketing;
    public string $utility;
    public string $authentication;
    public string $authenticationInternational;
    public string $service;

    public function __construct(array $approximate_charges)
    {
        $this->total = $approximate_charges['Total'];
        $this->marketing = $approximate_charges['MARKETING'];
        $this->utility = $approximate_charges['UTILITY'];
        $this->authentication = $approximate_charges['AUTHENTICATION'];
        $this->authenticationInternational = $approximate_charges['AUTHENTICATION_INTERNATIONAL'];
        $this->service = $approximate_charges['SERVICE'];
    }
}
