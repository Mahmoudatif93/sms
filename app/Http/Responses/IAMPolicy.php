<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;

/**
 * @OA\Schema(
 *     schema="IamPolicy",
 *     type="object",
 *     title="IAM Policy Response",
 *     description="Response format for IAM policies",
 *     @OA\Property(property="id", type="string", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="name", type="string", example="Policy Name"),
 *     @OA\Property(property="description", type="string", example="Description of the policy"),
 *     @OA\Property(property="type", type="string", example="custom", enum={"custom", "managed"}),
 *     @OA\Property(property="scope", type="string", example="organization"),
 *     @OA\Property(property="createdAt", type="string", format="date-time", example="2024-10-07T17:17:02.138Z"),
 *     @OA\Property(property="updatedAt", type="string", format="date-time", example="2024-10-07T17:17:02.138Z"),
 *     @OA\Property(
 *         property="definitions",
 *         type="array",
 *         description="Array of policy definitions",
 *         @OA\Items(ref="#/components/schemas/IamPolicyDefinition")
 *     )
 * )
 */
class IAMPolicy extends DataInterface
{
    public string $id;
    public string $name;
    public string $description;
    public string $type;
    public string $scope;
    public string $createdAt;
    public string $updatedAt;
    public array $definitions;  // Array of IamPolicyDefinitionResponse

    /**
     * IAMPolicy constructor.
     *
     * @param \App\Models\IAMPolicy $policy
     */
    public function __construct(\App\Models\IAMPolicy $policy)
    {
        $this->id = (string) $policy->id;
        $this->name = $policy->name;
        $this->description = $policy->description;
        $this->type = $policy->type;
        $this->scope = $policy->scope;
        // Map definitions using IamPolicyDefinitionResponse
        $this->definitions = $policy->definitions->map(function ($definition) {
            return new IAMPolicyDefinition($definition);
        })->toArray();
    }
}
