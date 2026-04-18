<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;

/**
 * @OA\Schema(
 *     schema="Resource",
 *     type="object",
 *     title="Resource Response",
 *     description="Response format for resources",
 *     @OA\Property(property="id", type="string", example="1"),
 *     @OA\Property(property="version", type="string", example="v1"),
 *     @OA\Property(property="method", type="string", example="GET"),
 *     @OA\Property(property="uri", type="string", example="/api/users"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 * )
 */

class Resource extends DataInterface
{

    public string $id;
    public string $version;
    public string $method;
    public string $uri;
    public bool $is_active;

    /**
     * ResourceResponse constructor.
     *
     * @param $resource
     */
    public function __construct($resource)
    {
        $this->id = (string) $resource->id;
        $this->version = $resource->version;
        $this->method = $resource->method;
        $this->uri = $resource->uri;
        $this->is_active = $resource->is_active;
    }
}
