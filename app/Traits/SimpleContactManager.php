<?php

namespace App\Traits;

use App\Models\AttributeDefinition;
use App\Models\ContactAttribute;
use App\Models\ContactEntity;
use App\Models\Identifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

trait SimpleContactManager
{
    /**
     * Find or create a contact based on identifiers.
     * If found, updates the contact with new identifiers and attributes.
     * If not found, creates a new contact.
     *
     * @param string $organizationId
     * @param array $identifiers ['email' => '...', 'phone' => '...', 'fingerprint' => '...']
     * @param array $attributes ['key' => 'value', ...]
     * @return ContactEntity
     * @throws InvalidArgumentException
     */

    public static function ensureCoreAttributeDefinitionsExist_(): void
    {
        $definitions = [
            [
                'key' => 'display-name',
                'display_name' => 'Display Name',
                'type' => 'string',
                'cardinality' => 'one',
                'pii' => false,
                'read_only' => false,
            ],
            [
                'key' => 'name',
                'display_name' => 'Name',
                'type' => 'string',
                'cardinality' => 'one',
                'pii' => false,
                'read_only' => false,
            ],
            [
                'key' => 'first-name',
                'display_name' => 'First Name',
                'type' => 'string',
                'cardinality' => 'one',
                'pii' => false,
                'read_only' => false,
            ],
            [
                'key' => 'last-name',
                'display_name' => 'Last Name',
                'type' => 'string',
                'cardinality' => 'one',
                'pii' => false,
                'read_only' => false,
            ],
            [
                'key' => 'subscribed-whatsapp',
                'display_name' => 'Subscribed to WhatsApp',
                'type' => 'boolean',
                'cardinality' => 'one',
                'pii' => false,
                'read_only' => false,
            ],
            [
                'key' => 'subscribed-messenger',
                'display_name' => 'Subscribed to Messenger',
                'type' => 'boolean',
                'cardinality' => 'one',
                'pii' => false,
                'read_only' => false,
            ],
            [
                'key' => 'subscribed-livechat',
                'display_name' => 'Subscribed to Livechat',
                'type' => 'boolean',
                'cardinality' => 'one',
                'pii' => false,
                'read_only' => false,
            ],
        ];

        foreach ($definitions as $def) {
            AttributeDefinition::firstOrCreate(
                ['key' => $def['key']],
                [
                    'id' => Str::uuid(),
                    'display_name' => $def['display_name'],
                    'type' => $def['type'],
                    'cardinality' => $def['cardinality'],
                    'pii' => $def['pii'],
                    'read_only' => $def['read_only'],
                    'builtin' => true,
                ]
            );
        }
    }


    public function findOrCreateContact_(string $organizationId, array $identifiers, array $attributes = []): ContactEntity
    {
        $this->ensureCoreAttributeDefinitionsExist_();
        if (empty($identifiers)) {
            throw new InvalidArgumentException('At least one identifier is required');
        }

        // Try to find existing contact
        $contact = null;
        foreach ($identifiers as $key => $value) {
            if (!empty($value)) {
                $contact = ContactEntity::query()
                    ->where('organization_id', $organizationId)
                    ->whereHas('identifiers', function ($query) use ($key, $value) {
                        $query->where('key', $key)
                            ->where('value', $value);
                    })
                    ->first();

                if ($contact) {
                    break;
                }
            }
        }

        // If not found, create new contact
        if (!$contact) {
            return DB::transaction(function () use ($organizationId, $identifiers, $attributes) {
                $contact = ContactEntity::create([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $organizationId,
                ]);

                // Add identifiers
                foreach ($identifiers as $key => $value) {
                    if (!empty($value)) {
                        $contact->identifiers()->create([
                            'key' => $key,
                            'value' => $value,
                        ]);
                    }
                }

                // Add attributes
                foreach ($attributes as $key => $value) {
                    $this->upsertAttribute_($contact, $key, $value);
                }

                return $contact;
            });
        }

        // Update existing contact
        $this->updateContact_($contact, $identifiers, $attributes);

        return $contact;
    }

    /**
     * Update an existing contact with new identifiers and attributes.
     *
     * @param ContactEntity $contact
     * @param array $identifiers ['email' => '...', 'phone' => '...', 'fingerprint' => '...']
     * @param array $attributes ['key' => 'value', ...]
     * @return ContactEntity
     */
    public function updateContact_(ContactEntity $contact, array $identifiers = [], array $attributes = []): ContactEntity
    {
        // Update identifiers
        foreach ($identifiers as $key => $value) {
            if (!empty($value)) {
                if ($key === 'email-address' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                Identifier::updateOrCreate(
                    [
                        'contact_id' => $contact->id,
                        'key' => $key,
                    ],
                    [
                        'value' => $value,
                    ]
                );
            }
        }

        // Update attributes
        foreach ($attributes as $key => $value) {
            $this->upsertAttribute_($contact, $key, $value);
        }

        return $contact;
    }

    /**
     * Upsert a contact attribute.
     *
     * @param ContactEntity $contact
     * @param string $key
     * @param mixed $value
     * @return void
     */
    private function upsertAttribute_(ContactEntity $contact, string $key, $value): void
    {
        if ($value === null) {
            return;
        }

        // Convert value to string
        if (is_object($value) && method_exists($value, '__toString')) {
            $value = (string) $value;
        } elseif (!is_string($value) && !is_numeric($value)) {
            $value = json_encode($value);
        }

        $organizationId = $contact->organization?->id;

        // Find or create attribute definition
        $attributeDefinition = AttributeDefinition::forOrgOrGlobal($organizationId)
            ->where('key', $key)
            ->first();

        if (!$attributeDefinition) {
            $attributeDefinition = AttributeDefinition::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'key' => $key,
                'display_name' => ucfirst(str_replace('-', ' ', $key)),
                'cardinality' => 'one',
                'type' => 'string',
                'pii' => in_array($key, ['email', 'phone', 'ip-address']),
                'read_only' => false,
                'builtin' => false,
            ]);
        }

        ContactAttribute::updateOrCreate(
            [
                'contact_id' => $contact->id,
                'attribute_definition_id' => $attributeDefinition->id,
            ],
            [
                'value' => (string) $value,
            ]
        );
    }
}
