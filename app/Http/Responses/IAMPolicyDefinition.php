<?php

namespace App\Http\Responses;

/**
 * @OA\Schema(
 *     schema="IamPolicyDefinition",
 *     type="object",
 *     title="IAM Policy Definition",
 *     description="Response format for IAM policy definitions",
 *     @OA\Property(property="id", type="string", example="definition-id-123"),
 *     @OA\Property(property="effect", type="string", example="allow", enum={"allow", "deny"}),
 *     @OA\Property(property="action", type="string", example="view", enum={"view", "edit", "delete", "any", "update"}),
 *     @OA\Property(
 *         property="resources",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="int", example="123548"),
 *             @OA\Property(property="uri", type="string", example="/api/v1/resource"),
 *             @OA\Property(property="method", type="string", example="GET"),
 *             @OA\Property(property="version", type="string", example="v1")
 *         ),
 *         description="Array of resources linked to this policy definition"
 *     )
 * )
 */
class IAMPolicyDefinition
{
    public string $id;
    public string $effect;
    public mixed $actions;
    public mixed $resource;

    /**
     * IamPolicyDefinitionResponse constructor.
     *
     * @param $definition
     */
    public function __construct(\App\Models\IAMPolicyDefinition $definition)
    {
        $this->id = (string)$definition->id;
        $this->effect = $definition->effect;
        $this->actions = $definition->actions;

        // Pluck the desired fields (uri, method, version) from related resources
        $this->resource = $definition->resource;
    }
}
