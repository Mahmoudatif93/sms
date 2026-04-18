<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\AttributeDefinition as AttributeDefinitionModel;

/**
 * @OA\Schema(
 *     schema="AttributeDefinition",
 *     type="object",
 *     title="Attribute Definition",
 *     required={"id", "key", "display_name", "cardinality", "type"},
 *     @OA\Property(property="id", type="string", description="UUID of the Attribute Definition"),
 *     @OA\Property(property="key", type="string", description="The key of the Attribute Definition"),
 *     @OA\Property(property="display_name", type="string", description="Display name of the Attribute"),
 *     @OA\Property(property="cardinality", type="string", enum={"One", "Many"}, description="Cardinality (One or Many)"),
 *     @OA\Property(property="type", type="string", enum={"string", "boolean", "datetime", "number"}, description="Type of the Attribute"),
 *     @OA\Property(property="pii", type="boolean", description="Indicates if the attribute contains PII data"),
 *     @OA\Property(property="read_only", type="boolean", description="Indicates if the attribute is read-only"),
 *     @OA\Property(property="builtin", type="boolean", description="Indicates if the attribute is built-in"),
 *     @OA\Property(property="created_at", type="integer", description="When the attribute was created as a timestamp"),
 *     @OA\Property(property="updated_at", type="integer", description="When the attribute was last updated as a timestamp")
 * )
 */
class AttributeDefinition extends DataInterface
{
    public string $id;
    public string $key;
    public string $display_name;
    public string $cardinality;
    public string $type;
    public bool $pii;
    public bool $read_only;
    public bool $builtin;
    public int $created_at;
    public int $updated_at;

    public function __construct(AttributeDefinitionModel $attributeDefinition)
    {
        $this->id = $attributeDefinition->id;
        $this->key = $attributeDefinition->key;
        $this->display_name = $attributeDefinition->display_name;
        $this->cardinality = $attributeDefinition->cardinality;
        $this->type = $attributeDefinition->type;
        $this->pii = $attributeDefinition->pii;
        $this->read_only = $attributeDefinition->read_only;
        $this->builtin = $attributeDefinition->builtin;
        $this->created_at = $attributeDefinition->created_at;
        $this->updated_at = $attributeDefinition->updated_at;
    }
}
