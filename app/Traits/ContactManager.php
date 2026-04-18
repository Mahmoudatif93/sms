<?php

namespace App\Traits;

use App\Models\AttributeDefinition;
use App\Models\Channel;
use App\Models\ContactAttribute;
use App\Models\ContactEntity;
use App\Models\Identifier;
use App\Models\MessengerConsumer;
use App\Models\MetaPage;
use App\Rules\WhatsappValidPhoneNumber;
use Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Validator;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

trait ContactManager
{
    use WhatsappPhoneNumberManager;

    public static function ensureCoreAttributeDefinitionsExist(): void
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

    /**
     * Fetch and validate a contact by its ID.
     *
     * @param string $contactId
     * @return ContactEntity|null
     */
    public function getContactById(string $contactId): ?ContactEntity
    {
        return ContactEntity::find($contactId);
    }

    /**
     * Find or create a contact based on visitor data.
     *
     * @param array $visitorData Visitor data including fingerprint, browser, ip_address, referrer, last_seen
     * @param Channel $channel The channel associated with the visitor
     * @return ContactEntity Found or created contact
     */
    public function findOrCreateVisitorContact(array $visitorData, Channel $channel): ContactEntity
    {
        // Extract identifiers from visitor data
        $identifiers = [
            'fingerprint' => $visitorData['fingerprint'] ?? null,
            'email-address' => $visitorData['email'] ?? null,
            'phone-number' => $visitorData['phone'] ?? null,
        ];

        // Extract attributes
        $attributes = [
            'browser' => $visitorData['browser'] ?? null,
            'referrer' => $visitorData['referrer'] ?? null,
            'last-seen' => $visitorData['last-seen'] ?? now(),
            'ip-address' => $visitorData['ip-address'] ?? null,
            'subscribed-livechat' => 1,
        ];

        return $this->findOrCreateContact(
            $identifiers,
            $attributes,
            $channel->organization()?->id,
            $channel->id
        );
    }

    /**
     * Find or create a contact based on multiple identifiers.
     * Unified method that works across different platforms and controllers.
     *
     * @param array $identifiers Array of identifiers (email, phone, fingerprint)
     * @param array $attributes Array of contact attributes to update/create
     * @param string $organizationId
     * @param string|null $channelId Optional channel ID
     * @return ContactEntity Found or created contact
     * @throws \Throwable
     */
    public function findOrCreateContact(array $identifiers, array $attributes = [], ?string $organizationId = null, ?string $channelId = null): ContactEntity
    {
        // Validate that at least one identifier is provided
        if (empty($identifiers)) {
            throw new InvalidArgumentException('At least one identifier (email, phone, fingerprint) is required');
        }

        // Resolve organization from the workspace

        // Try to find an existing contact with any of the provided identifiers
        $contact = null;

        // Check identifiers in order of priority
        $identifierTypes = [
            'email' => Identifier::EMAIL_KEY,
            'phone' => Identifier::PHONE_NUMBER_KEY,
            'fingerprint' => Identifier::FINGERPRINT_KEY
        ];

        foreach ($identifierTypes as $key => $type) {
            if (!empty($identifiers[$key])) {
                $existingContact = ContactEntity::query()
                    ->where('organization_id', $organizationId)
                    ->whereHas('identifiers', function ($query) use ($identifiers, $key, $type) {
                        $query->where('key', $type)
                            ->where('value', $identifiers[$key]);
                    })
                    ->first();

                if ($existingContact) {
                    $contact = $existingContact;
                    break;
                }
            }
        }

        // If no contact found, create a new one
        if (!$contact) {
            return DB::transaction(function () use ($identifiers, $attributes, $organizationId, $identifierTypes) {
                // Create new contact
                $contact = ContactEntity::create([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $organizationId
                ]);



                // Add identifiers
                foreach ($identifierTypes as $key => $type) {
                    if (!empty($identifiers[$key])) {
                        // Validate email format
                        if ($key === 'email' && !filter_var($identifiers[$key], FILTER_VALIDATE_EMAIL)) {
                            continue;
                        }

                        $contact->identifiers()->create([
                            'key' => $type,
                            'value' => $identifiers[$key]
                        ]);
                    }
                }

                // Add attributes
                foreach ($attributes as $key => $value) {
                    $this->upsertContactAttribute($contact, $key, $value);
                }

                return $contact;
            });
        }

        // Update existing contact with any new identifiers or attributes
        $this->updateContactIdentifiers($contact, $identifiers);

        foreach ($attributes as $key => $value) {
            $this->upsertContactAttribute($contact, $key, $value);
        }

        return $contact;
    }

    /**
     * Update or insert a contact attribute.
     *
     * @param ContactEntity $contact The contact
     * @param string $key The attribute key
     * @param mixed $value The attribute value
     * @return void
     */
    private function upsertContactAttribute(ContactEntity $contact, string $key, $value): void
    {
        // Skip null values
        if ($value === null) {
            return;
        }

        // Convert the value to string if it's not already
        if (is_object($value) && method_exists($value, '__toString')) {
            $value = (string) $value;
        } elseif (!is_string($value) && !is_numeric($value)) {
            $value = json_encode($value);
        }

        // 🔹 Resolve organizationId through the workspace
        //$organizationId = $contact->organization()?->id;
        $organizationId = $contact->organization?->id;


        // 🔹 Find existing attribute definition (either builtin or org-level)
        $attributeDefinition = AttributeDefinition::forOrgOrGlobal($organizationId)
            ->where('key', $key)
            ->first();

        if (!$attributeDefinition) {
            // Create a new attribute definition if it doesn't exist
            $attributeDefinition = AttributeDefinition::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'key' => $key,
                'display_name' => ucfirst(str_replace('-', ' ', $key)),
                'cardinality' => 'one', // Assuming single value
                'type' => 'string',
                'pii' => in_array($key, ['email', 'phone', 'ip-address']), // Mark PII fields
                'read_only' => false,
                'builtin' => false
            ]);
        }

        // Update or create the attribute
        ContactAttribute::updateOrCreate(
            [
                'contact_id' => $contact->id,
                'attribute_definition_id' => $attributeDefinition->id
            ],
            [
                'value' => (string) $value
            ]
        );
    }

    /**
     * Update contact identifiers based on visitor data.
     *
     * @param ContactEntity $contact The contact to update
     * @param array $identifiers
     * @return void
     */
    public function updateContactIdentifiers(ContactEntity $contact, array $identifiers): void
    {
        // Map of data keys to identifier types
        // $identifierMap = [
        //     'fingerprint' => Identifier::FINGERPRINT_KEY,
        //     'email' => Identifier::EMAIL_KEY,
        //     'phone' => Identifier::PHONE_NUMBER_KEY,
        // ];
        // Update each identifier if data is available
        foreach ($identifiers as $dataKey => $identifierKey) {
            if (!empty($identifiers[$dataKey])) {
                // For email, validate format
                if ($dataKey === 'email' && !filter_var($identifiers[$dataKey], FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                // Update or create the identifier
                Identifier::updateOrCreate(
                    [
                        'contact_id' => $contact->id,
                        'key' => $dataKey
                    ],
                    [
                        'value' => $identifiers[$dataKey]
                    ]
                );
            }
        }
    }

    public function getOrCreateContactByMessengerPsid(string $psid, string $pageId, ?string $metaAccessToken): ?ContactEntity
    {
        // 1. Try to find MessengerConsumer
        $consumer = MessengerConsumer::where('psid', $psid)->first();
        $metaPage = MetaPage::where('page_id', $pageId)->first();
        //        $workspace = $metaPage ? $metaPage-> : null;

        if (!$consumer) {
            return null; // No consumer => don't create a contact yet
        }

        // 2. Check if already linked to a contact
        if ($consumer->contact) {
            return $consumer->contact;
        }

        // 3. Create a new contact
        $contact = ContactEntity::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $consumer->workspace_id, // If available
        ]);

        // 4. Attach the consumer to the contact
        $consumer->contact_id = $contact->id;
        $consumer->save();

        // 5. Add `hasMessengerSubscription` boolean attribute
        $attributeDef = AttributeDefinition::where('key', 'subscribed-messenger')->first();
        if ($attributeDef) {
            ContactAttribute::updateOrCreate([
                'contact_id' => $contact->id,
                'attribute_definition_id' => $attributeDef->id
            ], [
                'value' => true
            ]);
        }

        // 6. Attempt to fetch name via Graph API using /{page_id}/conversations
        if ($metaAccessToken) {
            $response = Http::withToken($metaAccessToken)
                ->get("https://graph.facebook.com/v20.0/{$pageId}/conversations", [
                    'fields' => 'id,participants',
                ]);

            if ($response->successful()) {
                $conversations = $response->json('data') ?? [];

                foreach ($conversations as $conversation) {
                    $participants = $conversation['participants']['data'] ?? [];

                    foreach ($participants as $participant) {
                        if ($participant['id'] == $psid) {
                            $name = $participant['name'] ?? null;

                            if ($name) {
                                $nameAttrDef = AttributeDefinition::where('key', 'display-name')->first();
                                if ($nameAttrDef) {
                                    ContactAttribute::updateOrCreate([
                                        'contact_id' => $contact->id,
                                        'attribute_definition_id' => $nameAttrDef->id
                                    ], [
                                        'value' => $name
                                    ]);
                                }
                            }
                            break 2;
                        }
                    }
                }
            }
        }

        return $contact;
    }

    public function getNameFromMessengerAPI(string $psid, string $pageId, string $pageAccessToken): ?string
    {
        $response = Http::withToken($pageAccessToken)
            ->get("https://graph.facebook.com/v22.0/{$pageId}/conversations", [
                'fields' => 'id,participants',
            ]);

        if (!$response->successful()) {
            return 'Facebook User';
        }

        if ($response->successful()) {
            $conversations = $response->json('data') ?? [];
            foreach ($conversations as $conversation) {
                $participants = $conversation['participants']['data'] ?? [];
                foreach ($participants as $participant) {
                    if ($participant['id'] == $psid) {
                        return $participant['name'] ?? null;
                    }
                }
            }
        }

        return "Facebook User";
    }

    /**
     * Resolve the WhatsApp-recipient phone number from a `to` object and validate it.
     *
     * @param array $to ['type' => ..., 'value' => ...]
     * @param string $organizationId
     * @return array ['success' => bool, 'phone' => string|null, 'error' => string|null, 'contact' => ContactEntity|null]
     */
    public function resolveWhatsappReceiverPhoneOrFail(array $to, string $organizationId): array
    {
        if (!isset($to['type']) || !isset($to['value'])) {
            return [
                'success' => false,
                'phone' => null,
                'error' => 'The "to" object must include both type and value.',
                'contact' => null,
            ];
        }

        $type = $to['type'];
        $value = $to['value'];
        $contact = null;
        $senderPhone = null;


        switch ($type) {
            case 'contact':
                // Look for contact in org, not just this workspace
                $contact = ContactEntity::where('id', $value)
                    ->where('organization_id', $organizationId)
                    ->first();

                if (!$contact || !$contact->getPhoneNumberIdentifier()) {
                    return [
                        'success' => false,
                        'phone' => null,
                        'error' => 'Invalid contact ID or missing phone number.',
                        'contact' => null,
                    ];
                }


                $senderPhone = $contact->getPhoneNumberIdentifier();
                break;

            case 'phone-number':
                $senderPhone = $this->normalizePhoneNumber($value);

                if (!$senderPhone) {
                    return [
                        'success' => false,
                        'phone' => null,
                        'error' => 'Failed to normalize the phone number.',
                        'contact' => null,
                    ];
                }

                // Validate format
                $validator = Validator::make(['to' => $senderPhone], [
                    'to' => ['required', 'string', new WhatsappValidPhoneNumber()],
                ]);

                if ($validator->fails()) {
                    return [
                        'success' => false,
                        'phone' => null,
                        'error' => $validator->errors()->first('to'),
                        'contact' => null,
                    ];
                }

                // Look up contact by phone anywhere in the org
                $contact = ContactEntity::where('organization_id', $organizationId)
                    ->whereHas('identifiers', function ($query) use ($senderPhone) {
                        $query->where('key', ContactEntity::IDENTIFIER_TYPE_PHONE)
                            ->where('value', $senderPhone);
                    })
                    ->first();

                if (!$contact) {
                    // Create new contact in this workspace
                    $contact = ContactEntity::create([
                        'id' => Str::uuid(),
                        'organization_id' => $organizationId, // default Organization
                    ]);


                    $contact->identifiers()->create([
                        'key' => ContactEntity::IDENTIFIER_TYPE_PHONE,
                        'value' => $senderPhone,
                    ]);

                    $contact->setDisplayName('WhatsApp User');
                    $contact->markAsWhatsappSubscribed();
                }
                break;

            default:
                return [
                    'success' => false,
                    'phone' => null,
                    'error' => 'Unsupported "to" type: ' . $type,
                    'contact' => null,
                ];
        }

        return [
            'success' => true,
            'phone' => $senderPhone,
            'error' => null,
            'contact' => $contact,
        ];
    }


    protected function getContactName(ContactEntity $contact, string $platform): ?string
    {
        return match ($platform) {
                // @todo Name Or
            Channel::WHATSAPP_PLATFORM => $contact ->getWhatsappNameAttribute() ?? $contact->getPhoneNumberIdentifier(),
            Channel::LIVECHAT_PLATFORM => $this->getIpAddressFromAttribute($contact) ?? 'Visitor', // $contact->getNameAttribute() ??
            Channel::MESSENGER_PLATFORM => $contact->getDiplayNameAttribute() ?? $contact->getPhoneNumberIdentifier() ?? 'Unknown',
            Channel::TICKETING_PLATFORM => $contact->getNameAttribute() ?? $contact->getEmailIdentifier() ?? 'Unknown',
            default => response()->json(['error' => 'Unsupported Platform'], 400),
        };


    }

    protected function getIpAddressFromAttribute(ContactEntity $contact): string
    {
        $ipAddress = $contact->getIpAddressFromAttribute();
        if (!$ipAddress) {
            return 'Unknown';
        }
        $geoData = getLocationFromIP($ipAddress);
        return ($geoData['city'] ?? 'Unknown') . ', ' . ($geoData['country'] ?? 'Unknown - ') . ' (' . $ipAddress . ')';
    }

    /**
     * Create a new contact for a visitor.
     *
     * @param array $visitorData Visitor data
     * @param Channel $channel The channel
     * @return ContactEntity The newly created contact
     */
    // private function createNewVisitorContact(array $visitorData, Channel $channel): ContactEntity
    // {
    //     // Create a new contact
    //     $contact = ContactEntity::create([
    //         'id' => (string) Str::uuid(),
    //         'organization_id' => $channel->organization()?->id,
    //     ]);


    //     // Create fingerprint identifier (unique)
    //     $contact->identifiers()->create([
    //         'key' => 'fingerprint',
    //         'value' => $visitorData['fingerprint']
    //     ]);

    //     // Mark contact as subscribed to livechat

    //     // Store other visitor data as attributes
    //     $this->updateVisitorAttributes($contact, $visitorData);

    //     return $contact;
    // }

    /**
     * Update visitor attributes for an existing contact.
     *
     * @param ContactEntity $contact The contact to update
     * @param array $visitorData The visitor data
     * @return void
     */
    private function updateVisitorAttributes(ContactEntity $contact, array $visitorData): void
    {
        // Update/create browser attribute
        if (!empty($visitorData['browser'])) {
            $this->upsertContactAttribute($contact, 'browser', $visitorData['browser']);
        }

        // Update/create referrer attribute
        if (!empty($visitorData['referrer'])) {
            $this->upsertContactAttribute($contact, 'referrer', $visitorData['referrer']);
        }

        // Update last_seen attribute
        if (!empty($visitorData['last-seen'])) {
            $this->upsertContactAttribute($contact, 'last-seen', $visitorData['last-seen']);
        }

        // Update city attribute if available
        if (!empty($visitorData['ip-address'])) {
            $this->upsertContactAttribute($contact, 'ip-address', $visitorData['ip-address']);
        }
    }

    private function createContactFromMessengerConsumer(MessengerConsumer $messengerConsumer, string $workspaceId): ContactEntity
    {
        $contact = ContactEntity::create([
            'id' => Str::uuid(),
            'workspace_id' => $workspaceId,
        ]);

        // 1. Link contact to consumer
        $messengerConsumer->contact()->associate($contact);
        $messengerConsumer->save();

        // 2. Add 'hasMessengerSubscription' attribute
        $attributeDef = AttributeDefinition::where('key', 'subscribed-messenger')->first();
        if ($attributeDef) {
            ContactAttribute::updateOrCreate([
                'contact_id' => $contact->id,
                'attribute_definition_id' => $attributeDef->id
            ], [
                'value' => true
            ]);
        }

        // 3. Prepare name-related attributes
        $displayNameAD = AttributeDefinition::where('key', 'display-name')->first();
        $firstNameAD = AttributeDefinition::where('key', 'first-name')->first();
        $lastNameAD = AttributeDefinition::where('key', 'last-name')->first();

        $fullName = trim($messengerConsumer->name ?? '');
        [$firstName, $lastName] = explode(' ', $fullName . ' ', 2); // avoid undefined offset

        // 4. Save display name
        if ($displayNameAD && !$contact->attributes()->where('attribute_definition_id', $displayNameAD->id)->exists()) {
            ContactAttribute::create([
                'contact_id' => $contact->id,
                'attribute_definition_id' => $displayNameAD->id,
                'value' => $fullName,
            ]);
        }

        // 5. Save first name
        if ($firstNameAD && !$contact->attributes()->where('attribute_definition_id', $firstNameAD->id)->exists()) {
            ContactAttribute::create([
                'contact_id' => $contact->id,
                'attribute_definition_id' => $firstNameAD->id,
                'value' => trim($firstName),
            ]);
        }

        // 6. Save last name
        if ($lastNameAD && !$contact->attributes()->where('attribute_definition_id', $lastNameAD->id)->exists()) {
            ContactAttribute::create([
                'contact_id' => $contact->id,
                'attribute_definition_id' => $lastNameAD->id,
                'value' => trim($lastName),
            ]);
        }

        return $contact;
    }

    /**
     * Count the total number of records in the request without processing them
     * This is used to determine if we should use a background job
     */
    private function countRecordsInInput(Request $request): int
    {
        $count = 0;

        // Count single phone number
        if ($request->filled('single_phone_number')) {
            $structuredRows[] = ['phone-number' => $request->input('single_phone_number')];
        }

        // Count phone numbers from text
        if ($request->filled('phone_numbers_text')) {
            $lines = explode("\n", $request->input('phone_numbers_text'));
            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    $count++;
                }
            }
        }

        // Count phone numbers from Excel file
        if ($request->hasFile('phone_numbers_file')) {
            $file = $request->file('phone_numbers_file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();

            $rowCount = 0;
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                if ($rowIndex === 1)
                    continue; // Skip header

                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $hasData = false;

                foreach ($cellIterator as $cell) {
                    if (trim((string) $cell->getValue()) !== '') {
                        $hasData = true;
                        break;
                    }
                }

                if ($hasData) {
                    $rowCount++;
                }
            }
            $count += $rowCount;
        }

        return $count;
    }

    /**
     * Extract raw phone numbers from input without processing/validation
     * This is used for background job processing
     */
    private function extractRawPhoneNumbers(Request $request): array
    {
        $phoneNumbers = [];

        // From single phone number
        if ($request->filled('single_phone_number')) {
            $phoneNumbers[] = [
                'phone-number' => $request->input('single_phone_number'),
                // 'display-name' => 'add from audiance'
            ];
        }

        // From text input
        if ($request->filled('phone_numbers_text')) {
            $lines = explode("\n", $request->input('phone_numbers_text'));

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line !== '') {
                    $phoneNumbers[] = ['phone-number' => $line];
                }

            }
        }
        // From Excel file
        if ($request->hasFile('phone_numbers_file')) {
            $file = $request->file('phone_numbers_file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $header = [];

            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $cells = [];

                foreach ($cellIterator as $cell) {
                    $cells[] = trim((string) $cell->getValue());
                }

                if ($rowIndex === 1) {
                    $header = array_map(fn($h) => strtolower(trim($h)), $cells);
                    continue;
                }

                if (count($header) !== count($cells)) {
                    continue;
                }

                $phoneNumbers[] = array_combine($header, $cells);
            }
        }

        return $phoneNumbers;
    }

    /**
     * Process phone numbers array (used by background job)
     */
    public function processPhoneNumbersArray(array $phoneNumbers, string $organizationID): array
    {
        $contacts = [];

        foreach ($phoneNumbers as $row) {
            $rawPhone = $row['phone-number'] ?? null;
            // $row['display-name'] = 'add from audiance';

            if (!$rawPhone) {
                continue;
            }

            $normalized = $this->normalizePhoneNumber($rawPhone);

            $validator = Validator::make(['phone' => $normalized], [
                'phone' => ['required', new WhatsappValidPhoneNumber()]
            ]);

            $isValid = !$validator->fails();
            $error = $isValid ? null : $validator->errors()->first('phone');

            //  TODO: Lookup contact in this org, not just this workspace
            $contact = null;
            if ($isValid) {
                if ($organizationID) {
                    $contact = ContactEntity::where('organization_id', $organizationID)
                        ->whereHas('identifiers', function ($q) use ($normalized) {
                            $q->where('key', ContactEntity::IDENTIFIER_TYPE_PHONE)
                                ->where('value', $normalized);
                        })
                        ->first();
                    // $contact = ContactEntity::select('contacts.*')
                    //     ->join('identifiers as i', 'i.contact_id', '=', 'contacts.id')
                    //     ->where('contacts.organization_id', $organizationID)
                    //     ->where('i.key', ContactEntity::IDENTIFIER_TYPE_PHONE)
                    //     ->where('i.value', $normalized)
                    //     ->first();
                }
            }

            $contacts[] = [
                'raw' => $row,
                'normalized_phone' => $normalized,
                'is_valid' => $isValid,
                'error' => $error,
                'contact' => $contact, // ✅ TODO: return matched contact if found
            ];
        }

        return $contacts;
    }

    private function extractContactsFromInput(Request $request, string $organizationID): array
    {
        $contacts = [];

        // From structured input (textarea or single)
        $structuredRows = [];

        if ($request->filled('single_phone_number')) {
            $structuredRows[] = ['phone-number' => $request->input('single_phone_number')];
        }

        if ($request->filled('phone_numbers_text')) {
            $lines = explode("\n", $request->input('phone_numbers_text'));
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $structuredRows[] = ['phone-number' => $line];
                }
            }
        }

        // From Excel file
        if ($request->hasFile('phone_numbers_file')) {
            $file = $request->file('phone_numbers_file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $header = [];

            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $cells = [];

                foreach ($cellIterator as $cell) {
                    $value = $cell->getValue();

                    if (ExcelDate::isDateTime($cell)) {
                        // Convert Excel serial number to real datetime string
                        $cells[] = ExcelDate::excelToDateTimeObject($value)->format('Y-m-d H:i:s');
                    } else {
                        // Normal non-date cell
                        $cells[] = trim((string) $value);
                    }
                }

                if ($rowIndex === 1) {
                    $header = array_map(fn($h) => strtolower(trim($h)), $cells);
                    continue;
                }

                if (count($header) !== count($cells)) {
                    continue;
                }

                $structuredRows[] = array_combine($header, $cells);
            }
        }

        foreach ($structuredRows as $row) {
            $rawPhone = $row['phone-number'] ?? null;

            if (!$rawPhone) {
                continue;
            }

            $normalized = $this->normalizePhoneNumber($rawPhone);

            $validator = Validator::make(['phone' => $normalized], [
                'phone' => ['required', new WhatsappValidPhoneNumber()]
            ]);

            $isValid = !$validator->fails();
            $error = $isValid ? null : $validator->errors()->first('phone');

            // ✅ TODO: Lookup contact in this org, not just this workspace
            $contact = null;
            if ($isValid) {


                if ($organizationID) {
                    $contact = ContactEntity::where('organization_id', $organizationID)
                        ->whereHas('identifiers', function ($q) use ($normalized) {
                            $q->where('key', ContactEntity::IDENTIFIER_TYPE_PHONE)
                                ->where('value', $normalized);
                        })
                        ->first();

                }
            }

            $contacts[] = [
                'raw' => $row,
                'normalized_phone' => $normalized,
                'is_valid' => $isValid,
                'error' => $error,
                'contact' => $contact, // ✅ TODO: return matched contact if found
            ];
        }

        return $contacts;
    }


    //    public function validateContactInWorkspace(ContactEntity $contact): bool
//    {
//        return $contact->workspaces()->where('workspace_id', $this->workspace_id)->exists();
//    }


    /**
     * Create a new contact from processed phone data (same logic as BulkImportContactsJob)
     */
    private function createContactFromPhoneData(array $processedData, string $organizationId)
    {
        try {
            $rawData = $processedData['raw'];
            $normalizedPhone = $processedData['normalized_phone'];

            // Create new contact (same as BulkImportContactsJob)
            $contact = ContactEntity::create([
                'id' => Str::uuid(),
                'organization_id' => $organizationId
            ]);

            // Add phone identifier (same key as BulkImportContactsJob)
            $contact->identifiers()->create([
                'key' => 'phone-number',
                'value' => $normalizedPhone
            ]);

            // Get attribute definitions (cached)
            $whatsappDef = cache()->remember("attr_def_{$organizationId}_subscribed-whatsapp", 3600, function () use ($organizationId) {
                return AttributeDefinition::forOrgOrGlobal($organizationId)->where('key', 'subscribed-whatsapp')->first();
            });

            $firstNameDef = cache()->remember("attr_def_{$organizationId}_first-name", 3600, function () use ($organizationId) {
                return AttributeDefinition::forOrgOrGlobal($organizationId)->where('key', 'first-name')->first();
            });

            $lastNameDef = cache()->remember("attr_def_{$organizationId}_last-name", 3600, function () use ($organizationId) {
                return AttributeDefinition::forOrgOrGlobal($organizationId)->where('key', 'last-name')->first();
            });

            $displayNameDef = cache()->remember("attr_def_{$organizationId}_display-name", 3600, function () use ($organizationId) {
                return AttributeDefinition::forOrgOrGlobal($organizationId)->where('key', 'display-name')->first();
            });

            // Add WhatsApp subscription attribute
            if ($whatsappDef) {
                ContactAttribute::create([
                    'contact_id' => $contact->id,
                    'attribute_definition_id' => $whatsappDef->id,
                    'value' => true,
                ]);
            }

            // Add name attributes if available
            if (!empty($rawData['first-name']) && $firstNameDef) {
                ContactAttribute::create([
                    'contact_id' => $contact->id,
                    'attribute_definition_id' => $firstNameDef->id,
                    'value' => $rawData['first-name'],
                ]);
            }

            if (!empty($rawData['last-name']) && $lastNameDef) {
                ContactAttribute::create([
                    'contact_id' => $contact->id,
                    'attribute_definition_id' => $lastNameDef->id,
                    'value' => $rawData['last-name'],
                ]);
            }

            if (!empty($rawData['display-name']) && $displayNameDef) {
                ContactAttribute::create([
                    'contact_id' => $contact->id,
                    'attribute_definition_id' => $displayNameDef->id,
                    'value' => $rawData['display-name'],
                ]);
            }

            return $contact;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error creating contact from phone data', [
                'error' => $e->getMessage(),
                'processed_data' => $processedData,
                'organization_id' => $organizationId
            ]);
            return null;
        }
    }


}
