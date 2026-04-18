<?php

namespace App\Http\Responses;


/**
 * @OA\Schema(
 *     schema="Preview",
 *     type="object",
 *     @OA\Property(property="preview_url", type="string"),
 *     @OA\Property(property="expires_at", type="string", format="date-time")
 * )
 */
class Preview
{
    public string $preview_url;
    public string $expires_at;

    public function __construct(array $preview)
    {
        $this->preview_url = $preview['preview_url'];
        $this->expires_at = $preview['expires_at'];
    }
}
