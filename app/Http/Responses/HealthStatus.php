<?php

namespace App\Http\Responses;

/**
 * @OA\Schema(
 *     schema="HealthStatus",
 *     type="object",
 *     @OA\Property(property="can_send_message", type="string"),
 *     @OA\Property(
 *         property="entities",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/HealthEntity")
 *     )
 * )
 */

class HealthStatus
{
    public string $can_send_message;
    public array $entities;

    public function __construct(array $healthStatus)
    {
        $this->can_send_message = $healthStatus['can_send_message'];
        $this->entities = array_map(
            fn($entity) => new HealthEntity($entity),
            $healthStatus['entities']
        );
    }
}
