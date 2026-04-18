<?php

namespace App\Http\Responses;

/**
 * @OA\Schema(
 *     schema="Workspace",
 *     type="object",
 *     title="Workspace Response",
 *     description="Workspace resource representation",
 *     required={"id", "organizationId", "name", "status"},
 *     @OA\Property(
 *         property="id",
 *         type="string",
 *         format="uuid",
 *         description="Workspace ID",
 *         example="123e4567-e89b-12d3-a456-426614174000"
 *     ),
 *     @OA\Property(
 *         property="organizationId",
 *         type="string",
 *         format="uuid",
 *         description="Organization ID to which the workspace belongs",
 *         example="123e4567-e89b-12d3-a456-426614174000"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Name of the workspace",
 *         example="Workspace Name"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         nullable=true,
 *         description="Description of the workspace",
 *         example="Workspace Description"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         description="Status of the workspace",
 *         example="active"
 *     ),
 *     @OA\Property(
 *         property="createdAt",
 *         type="integer",
 *         format="int64",
 *         nullable=true,
 *         description="Timestamp when the workspace was created",
 *         example=1609459200
 *     ),
 *     @OA\Property(
 *         property="updatedAt",
 *         type="integer",
 *         format="int64",
 *         nullable=true,
 *         description="Timestamp when the workspace was last updated",
 *         example=1609459200
 *     )
 * )
 */
class Workspace
{

    public string $id;
    public string $organizationId;
    public string $name;
    public ?string $description;
    public string $status;
    public mixed $channels; // Include channels
    public ?int $createdAt;
    public ?int $updatedAt;
    public bool $isCurrent; 

    public function __construct(\App\Models\Workspace $workspace,bool $isCurrent=false)
    {
        $this->id = $workspace->id;
        $this->organizationId = $workspace->organization_id;
        $this->name = $workspace->name;
        $this->description = $workspace->description;
        $this->status = $workspace->status;
        // Map channels to the response
        $this->channels = $workspace->channels->map(function ($channel) {
            return new Channel($channel);
        });
        $this->createdAt = $workspace->created_at;
        $this->updatedAt = $workspace->updated_at;
        $this->isCurrent = $isCurrent;
    }
}
