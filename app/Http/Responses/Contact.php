<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\ContactEntity;
use App\Models\Identifier;
use App\Models\ContactAttribute;

/**
 * @OA\Schema(
 *     schema="Contact",
 *     type="object",
 *     title="Contact Response",
 *     required={"id", "workspace_id", "identifiers", "attributes"},
 *     @OA\Property(property="id", type="string", description="UUID of the contact"),
 *     @OA\Property(property="workspace_id", type="string", description="ID of the workspace the contact belongs to"),
 *     @OA\Property(
 *         property="identifiers",
 *         type="array",
 *         @OA\Items(type="object", description="An identifier for the contact",
 *              @OA\Property(property="key", type="string", description="Identifier key (e.g., email-address or phone-number)"),
 *              @OA\Property(property="value", type="string", description="The value of the identifier")
 *         ),
 *         description="List of identifiers (e.g., email, phone)"
 *     ),
 *     @OA\Property(
 *         property="attributes",
 *         type="object",
 *         description="List of attributes with their values"
 *     )
 * )
 */
class Contact extends DataInterface
{
    public string $id;
    public ?string $organization_id;
    public array $identifiers;
    public array $attributes;
    public bool $hasMessengerSubscription;
    public int $created_at;
    public int $updated_at;

    public function __construct(ContactEntity $contact)
    {
        $this->id = $contact->id;
        $this->organization_id = $contact->organization_id;

        // Fetch and format identifiers
        $this->identifiers = Identifier::where('contact_id', $contact->id)
            ->get()
            ->map(function ($identifier) {
                return [
                    'key' => $identifier->key,
                    'value' => $identifier->value
                ];
            })->toArray();

        // Fetch and format attributes
        $this->attributes = ContactAttribute::where('contact_id', $contact->id)
            ->get()
            ->groupBy('attribute_definition_id')  // Group by attribute definition
            ->mapWithKeys(function ($attributes) {
                $attributeDefinition = $attributes->first()->attributeDefinition;
                if (!isset($attributeDefinition->cardinality)) {
                    return [];
                }
                // If cardinality is "One", return a single value
                if ($attributeDefinition->cardinality == 'one') {
                    return [
                        $attributeDefinition->key => $attributes->first()->value
                    ];
                }

                // If cardinality is "Many", return an array of values
                return [
                    $attributeDefinition->key => $attributes->pluck('value')->toArray()
                ];
            })->toArray();
        $this->hasMessengerSubscription = $contact->has_messenger_subscription;
        $this->created_at = $contact->created_at;
        $this->updated_at = $contact->updated_at;
    }
}

