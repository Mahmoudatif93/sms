<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\User;

/**
 * @OA\Schema(
 *     schema="OrganizationMember",
 *     type="object",
 *     title="Organization Member",
 *     description="Represents a member of an organization with relevant details like status and roles",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="User ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="User's full name",
 *         example="John Doe"
 *     ),
 *     @OA\Property(
 *         property="email",
 *         type="string",
 *         description="User's email address",
 *         example="john.doe@example.com"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         description="User's membership status in the organization",
 *         example="active"
 *     ),
 *     @OA\Property(
 *         property="roles",
 *         type="array",
 *         description="Roles assigned to the user in the organization",
 *         @OA\Items(type="string", example="Admin")
 *     )
 * )
 */


class OrganizationMember extends DataInterface
{
    public int $id;
    public ?string $name;
    public ?string $username;
    public string $email;
    public string $status;
    public mixed $roles;
    public mixed $workspaces;
    public bool $isHaveCompleteProfile;

    public function __construct(User $user, ?string $organizationID)
    {
        $this->id = $user->id;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->status = $user->pivot->status;
        $this->roles = $user->IAMRolesForOrg($organizationID)->get()->toArray();
        $this->workspaces = $user->workspaces;
        $this->isHaveCompleteProfile = !is_null($user->password) &&
            !is_null($user->name) &&
            !is_null($user->phone);
    }
}
