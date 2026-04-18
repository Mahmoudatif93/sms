<?php

namespace Database\Seeders;

use App\Models\AttributeDefinition;
use Illuminate\Database\Seeder;
use Str;

class AttributeDefinitionSeeder extends Seeder
{


    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attributeDefinitions = [
            [
                'id' => Str::uuid(),
                'workspace_id' => null,
                'key' => 'display-name',
                'display_name' => 'Display Name',
                'cardinality' => 'One',
                'type' => 'string',
                'pii' => true,
                'read_only' => false,
                'builtin' => true,
                'created_at' => time(),
                'updated_at' => time(),
            ],
            [
                'id' => Str::uuid(),
                'workspace_id' => null,
                'key' => 'first-name',
                'display_name' => 'First Name',
                'cardinality' => 'One',
                'type' => 'string',
                'pii' => true,
                'read_only' => false,
                'builtin' => true,
                'created_at' => time(),
                'updated_at' => time(),
            ],
            [
                'id' => Str::uuid(),
                'workspace_id' => null,
                'key' => 'last-name',
                'display_name' => 'Last Name',
                'cardinality' => 'One',
                'type' => 'string',
                'pii' => true,
                'read_only' => false,
                'builtin' => true,
                'created_at' => time(),
                'updated_at' => time(),
            ],
            [
                'id' => Str::uuid(),
                'workspace_id' => null,
                'key' => 'email-address',
                'display_name' => 'Email',
                'cardinality' => 'Many',
                'type' => 'string',
                'pii' => true,
                'read_only' => true,
                'builtin' => true,
                'created_at' => time(),
                'updated_at' => time(),
            ],
            [
                'id' => Str::uuid(),
                'workspace_id' => null,
                'key' => 'subscribed-whatsapp',
                'display_name' => 'Whatsapp Subscription',
                'cardinality' => 'One',
                'type' => 'boolean',
                'pii' => false,
                'read_only' => false,
                'builtin' => true,
                'created_at' => time(),
                'updated_at' => time(),
            ]
        ];

        foreach ($attributeDefinitions as $attributeDefinition) {
            AttributeDefinition::create($attributeDefinition);
        }

    }
}
