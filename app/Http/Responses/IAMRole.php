<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;

/**
 * @OA\Schema(
 *     schema="IAMRole",
 *     type="object",
 *     title="IAM Role Response",
 *     description="Response format for IAM roles",
 *     @OA\Property(property="id", type="string", example="role-id"),
 *     @OA\Property(property="name", type="string", example="Admin Role"),
 *     @OA\Property(property="description", type="string", example="Description of the role"),
 *     @OA\Property(property="organizationId", type="string", example="organization-id"),
 *     @OA\Property(property="type", type="string", example="organization"),
 *     @OA\Property(
 *         property="policies",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/IamPolicy")
 *     )
 * )
 */
class IAMRole extends DataInterface
{
    public string $id;
    public string $name;
    public string $description;
    public string $organizationId;
    public string $type;
    public array $policies;

    public function __construct($role)
    {
        $this->id = (string)$role->id;
        $this->name = $role->name;
        $this->description = $role->description;
        $this->organizationId = (string)$role->organization_id;
        $this->type = $role->type;
        $this->policies = $role->policies->map(function ($policy) {
            return new IAMPolicy($policy);
        })->toArray();
    }
}
