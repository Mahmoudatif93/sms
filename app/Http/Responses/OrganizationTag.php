<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;


class OrganizationTag extends DataInterface
{
    public string $id;
    
    public string $organization_name;
    public ?string $created_at;
    public ?string $updated_at;


    public function __construct(\App\Models\OrganizationTag $OrganizationTag)
    {
        $this->id = $OrganizationTag->organization->id;
        $this->name_ar = $OrganizationTag->organization->name;
        $this->created_at = $OrganizationTag->created_at;
        $this->updated_at = $OrganizationTag->updated_at;

    }
}
