<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;

/**
 * @OA\Schema(
 *     schema="Organization",
 *     type="object",
 *     title="Organization",
 *     description="Organization details",
 *     required={"id", "name", "status", "owner"},
 *     @OA\Property(
 *         property="id",
 *         type="string",
 *         format="uuid",
 *         description="Unique identifier for the organization",
 *         example="123e4567-e89b-12d3-a456-426614174000"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="The name of the organization",
 *         example="My Organization"
 *     ),
 *     @OA\Property(
 *         property="avatarUrl",
 *         type="string",
 *         format="url",
 *         nullable=true,
 *         description="URL of the organization's avatar",
 *         example="https://example.com/avatar.png"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         description="The current status of the organization",
 *         example="active"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="The current status of the organization",
 *         example="active"
 *     ),
 *     @OA\Property(
 *         property="owner",
 *         type="string",
 *         format="id",
 *         description="UUID of the user who owns the organization",
 *         example="123e4567-e89b-12d3-a456-426614174000"
 *     ),
 * )uuid
 */

class Organization extends DataInterface
{
    public string $id;
    public string $name;
    public ?string $avatarUrl;
    public string $status;
    public ?string $owner;
    public ?string $type;
    public ?string $commercial_registration_number;
    public ?string $file_commercial_register;
    public ?string $file_value_added_tax_certificate;
    public ?string $unified_number;
    public string $createdAt;
    public string $updatedAt;
    public ?int $ownerID;
    public bool $isOwner = false;
    public bool $isCurrent = false;

    /**
     * OrganizationResponse constructor.
     *
     * @param \App\Models\Organization $organization
     */
    public function __construct(\App\Models\Organization $organization)
    {
        $this->id = $organization->id;
        $this->name = $organization->name;
        $this->avatarUrl = $organization->getAvatarUrl() ?? null;
        $this->status = $organization->status;
        $this->owner = $organization?->owner?->name;
        $this->ownerID = $organization?->owner?->id;
        $this->isOwner = false;
        $this->isCurrent = false;
        $this->type = $organization->type;
        $this->commercial_registration_number = $organization->commercial_registration_number;
        $this->unified_number = $organization->unified_number;
        $this->file_commercial_register = $organization->getFileCommercialRegister();
        $this->file_value_added_tax_certificate = $organization->getFileValueAddedTaxCertificate();
    }

    /**
     * Set the ownership status of the organization
     *
     * @param bool $isOwner
     * @return $this
     */
    public function setIsOwner(bool $isOwner): self
    {
        $this->isOwner = $isOwner;
        return $this;
    }

    /**
     * Set the current status of the organization
     *
     * @param bool $isCurrent
     * @return $this
     */
    public function setIsCurrent(bool $isCurrent): self
    {
        $this->isCurrent = $isCurrent;
        return $this;
    }
}
