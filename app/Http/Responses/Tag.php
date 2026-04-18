<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;


class Tag extends DataInterface
{
    public int $id;
    public string $name_ar;
    public string $name_en;
    public ?Tag $parent;
    public ?string $created_at;
    public ?string $updated_at;

    public function __construct(\App\Models\Tag $tag)
    {
        $this->id = $tag->id;
        $this->name_ar = $tag->name_ar;
        $this->name_en = $tag->name_en;
        $this->parent = $tag->parent ? new self($tag->parent) : null;
        $this->created_at = $tag->created_at;
        $this->updated_at = $tag->updated_at;

    }
}
