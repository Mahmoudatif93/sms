<?php

namespace App\Http\Responses;


use App\Http\Whatsapp\WhatsappFlows\FlowValidationError;

/**
 * @OA\Schema(
 *     schema="HealthEntity",
 *     type="object",
 *     @OA\Property(property="entity_type", type="string"),
 *     @OA\Property(property="id", type="string"),
 *     @OA\Property(property="can_send_message", type="string"),
 *     @OA\Property(
 *         property="errors",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/FlowValidationError")
 *     ),
 *     @OA\Property(
 *         property="additional_info",
 *         type="array",
 *         @OA\Items(type="string")
 *     )
 * )
 */
class HealthEntity
{
    public string $entity_type;
    public string $id;
    public string $can_send_message;
    public array $errors;
    public array $additional_info;

    public function __construct(array $entity)
    {
        $this->entity_type = $entity['entity_type'];
        $this->id = $entity['id'];
        $this->can_send_message = $entity['can_send_message'];
        $this->errors = isset($entity['errors']) ? array_map(
            fn($error) => new FlowValidationError($error),
            $entity['errors']
        ) : [];
        $this->additional_info = $entity['additional_info'] ?? [];
    }

}
