<?php

namespace App\Http\Controllers;

use App\Http\Responses\Contact;
use App\Http\Responses\ValidatorErrorResponse;
use App\Jobs\BulkImportContactsJob;
use App\Models\AttributeDefinition;
use App\Models\BulkImportLog;
use App\Models\ContactAttribute;
use App\Models\ContactEntity;
use App\Models\Identifier;
use App\Models\Organization;
use App\Models\Workspace;
use App\Rules\WhatsappValidPhoneNumber;
use App\Traits\ContactManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContactController extends BaseApiController
{
    use ContactManager;

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $organizationId = $organization->id;
        $this->ensureCoreAttributeDefinitionsExist();

        $validator = Validator::make(
            $request->all(),
            [
                'identifiers' => [
                    'required',
                    'array',
                    function ($attribute, $value, $fail) {
                        $hasEmail = collect($value)->contains(fn($id) => $id['key'] === 'email-address');
                        $hasPhone = collect($value)->contains(fn($id) => $id['key'] === 'phone-number');

                        if (!$hasEmail && !$hasPhone) {
                            $fail(__('messages.identifier_required'));
                        }
                    },
                ],
                'identifiers.*.key' => 'required|string|in:email-address,phone-number',
                'identifiers.*.value' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) use ($request) {
                        $identifier = collect($request->input('identifiers'))->firstWhere('value', $value);

                        if ($identifier && $identifier['key'] === 'email-address' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $fail('Invalid email address format.');
                        }

                        if ($identifier && $identifier['key'] === 'phone-number' && !(new WhatsappValidPhoneNumber())->passes('phone-number', $value)) {
                            $fail('Invalid phone number format.');
                        }
                    }
                ],
                'attributes' => 'nullable|array',
                'attributes.*' => [
                    'required',
                    function ($attribute, $value, $fail) use ($organizationId) {
                        $key = explode('.', $attribute)[1] ?? null;
                        if (!$key)
                            return;

                        $exists = AttributeDefinition::forOrgOrGlobal($organizationId)
                            ->where('key', $key)
                            ->exists();

                        if (!$exists) {
                            $fail(__('messages.attribute_key_invalid', ['key' => $key]));
                        }
                    }
                ]
            ]
        );

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }


        // Check if the contact already exists but in organization
        // Prevent duplicate identifiers
        foreach ($request->input('identifiers') as $identifier) {
            if (
                Identifier::existsForOrg(
                    organizationId: $organizationId,
                    key: $identifier['key'],
                    value: $identifier['value']
                )
            ) {
                return $this->response(
                    false,
                    __('messages.contact_already_exists', [
                        'key' => $identifier['key'],
                        'value' => $identifier['value'],
                    ]),
                    null,
                    400
                );
            }
        }

        // Create contact
        $contact = ContactEntity::create([
            'id' => Str::uuid(),
            'organization_id' => $organizationId,
        ]);


        foreach ($request->input('identifiers') as $identifier) {
            $contact->identifiers()->create($identifier);
        }

        if ($request->filled('attributes')) {
            foreach ($request->input('attributes') as $key => $value) {
                $definition = AttributeDefinition::forOrgOrGlobal($organizationId)->where('key', $key)->first();
                if ($definition) {
                    ContactAttribute::create([
                        'contact_id' => $contact->id,
                        'attribute_definition_id' => $definition->id,
                        'value' => $value,
                    ]);
                }
            }
        }

        return $this->response(true, 'Contact created successfully for Workspace', new Contact($contact));
    }

    public function update(Request $request, Organization $organization, ContactEntity $contact): JsonResponse
    {
        if ($contact->organization_id !== $organization->id) {
            return $this->response(false, 'Contact does not belong to this organization.', null, 403);
        }

        $organizationId = $organization->id;
        $this->ensureCoreAttributeDefinitionsExist();

        $validator = Validator::make(
            $request->all(),
            [
                'identifiers' => 'sometimes|array',
                'attributes' => 'nullable|array',
                'attributes.*' => [
                    'required',
                    function ($attribute, $value, $fail) use ($organizationId) {
                        $key = explode('.', $attribute)[1] ?? null;
                        if (!$key)
                            return;

                        $exists = AttributeDefinition::forOrgOrGlobal($organizationId)
                            ->where('key', $key)
                            ->exists();

                        if (!$exists) {
                            $fail("The attribute key '{$key}' is invalid for this organization.");
                        }
                    }
                ]
            ]
        );

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        if ($request->filled('identifiers')) {
            Identifier::where('contact_id', $contact->id)->delete();
            foreach ($request->input('identifiers') as $identifier) {
                $contact->identifiers()->create($identifier);
            }
        }

        if ($request->filled('attributes')) {
            foreach ($request->input('attributes') as $key => $value) {
                $definition = AttributeDefinition::forOrgOrGlobal($organizationId)->where('key', $key)->first();
                if ($definition) {
                    ContactAttribute::updateOrCreate(
                        ['contact_id' => $contact->id, 'attribute_definition_id' => $definition->id],
                        ['value' => $value]
                    );
                }
            }
        }

        return $this->response(true, 'Contact updated successfully for Workspace');
    }

    public function index(Request $request, Organization $organization)
    {

        $organizationId = $organization->id;
        $platform = $request->get('platform');
        $isAll = filter_var($request->get('all'), FILTER_VALIDATE_BOOLEAN);

        $query = ContactEntity::where('organization_id', $organizationId)
            ->when(
                $request->get('type') === 'phone',
                fn($q) => $q->withAnyIdentifierKey([ContactEntity::IDENTIFIER_TYPE_PHONE => ContactEntity::IDENTIFIER_TYPE_PHONE])
            )
            ->when(
                $request->filled('search'),
                fn($q) => $q->withAnyIdentifierValue([$request->get('search') => $request->get('search')])
            )
            ->when($platform === 'messenger', function ($q) use ($organizationId) {
                $attrId = AttributeDefinition::forOrgOrGlobal($organizationId)->where('key', 'subscribed-messenger')->value('id');
                if ($attrId) {
                    $q->whereHas('attributes', fn($sub) => $sub->where('attribute_definition_id', $attrId)->where('value', true));
                }
            });

        if ($isAll) {
            $contacts = $query->get();
            return $this->response(data: $contacts->map(fn($c) => new Contact($c)));
        }

        $paginated =  $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 15));
        //$query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15), ['*'], 'page', $request->get('page', 1));
        $response = $paginated->getCollection()->map(fn($c) => new Contact($c));
        $paginated->setCollection($response);

        return $this->paginateResponse(true, 'Contacts retrieved successfully', $paginated);
    }

    public function destroy(Organization $organization, ContactEntity $contact): JsonResponse
    {

        if ($contact->organization_id !== $organization->id) {
            return $this->response(false, 'Contact does not belong to this organization.', null, 403);
        }


        Identifier::where('contact_id', $contact->id)->delete();
        ContactAttribute::where('contact_id', $contact->id)->delete();
        $contact->delete();

        return $this->response(true, 'Contact deleted successfully');
    }

    public function bulkImport(Request $request, Organization $organization): JsonResponse
    {
        $organizationId = $organization->id;
        $this->ensureCoreAttributeDefinitionsExist();

        $validator = Validator::make($request->all(), [
            'single_phone_number' => 'nullable|string',
            'phone_numbers_text' => 'nullable|string',
            'phone_numbers_file' => 'nullable|file|mimes:xlsx,xls',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        // First, count the records without processing them to determine if we should use a job
        $recordCount = $this->countRecordsInInput($request);

        // Check if this is a large import that should be processed as a job
        $shouldUseJob = ($request->filled('phone_numbers_text') || $request->hasFile('phone_numbers_file')) && $recordCount > 1;

        if ($shouldUseJob) {
            // Extract raw phone numbers for job processing (without validation)
            $rawPhoneNumbers = $this->extractRawPhoneNumbers($request);
            // Create import log
            $importLog = BulkImportLog::create([
                'organization_id' => $organizationId,
                'user_id' => auth()->id(),
                'status' => BulkImportLog::STATUS_PENDING,
                'total_records' => $recordCount,
            ]);

            $chunkSize = 1000;
            $chunks = array_chunk($rawPhoneNumbers, $chunkSize);
            // Dispatch job for large imports
            foreach ($chunks as $index => $chunk) {
                BulkImportContactsJob::dispatch(
                    $organizationId,
                    $rawPhoneNumbers,
                    auth()->id(),
                    $importLog->id
                )->onQueue('imports');
            }


            return $this->response(true, __('messages.bulk_import_started'), [
                'message' => __('messages.bulk_import_queued'),
                'total_records' => $recordCount,
                'import_log_id' => $importLog->id,
                'processing_method' => 'background_job',
                'invalid_entries' => [],
                'created' => [
                    'count' => 1,
                    'contacts' => [],
                ]
            ]);
        }

        // Process small imports synchronously (single phone number or small batches)
        $rows = $this->extractContactsFromInput($request, $organizationId);
        $created = [];
        $invalid = [];

        $whatsappDef = AttributeDefinition::forOrgOrGlobal($organizationId)->where('key', 'subscribed-whatsapp')->first();

        $definitions = AttributeDefinition::forOrgOrGlobal($organizationId)
            ->get()
            ->keyBy('key');


        foreach ($rows as $row) {

            if (!$row['is_valid']) {
                $invalid[] = [
                    'raw' => $row['raw'],
                    'normalized_phone' => $row['normalized_phone'],
                    'error' => $row['error'],
                ];
                continue;
            }

            $phone = $row['normalized_phone'];

            // Existing contact
            $existing = ContactEntity::where('organization_id', $organizationId)
                ->whereHas(
                    'identifiers',
                    fn($q) =>
                    $q->where('key', 'phone-number')
                        ->where('value', $phone)
                )->first();

            if ($existing) {
                $contact = $existing;
            } else {
                $contact = ContactEntity::create([
                    'id' => Str::uuid(),
                    'organization_id' => $organizationId,
                ]);

                $contact->identifiers()->create([
                    'key' => 'phone-number',
                    'value' => $phone,
                ]);
            }


            // Subscribed-whatsapp
            if ($whatsappDef) {
                ContactAttribute::create([
                    'contact_id' => $contact->id,
                    'attribute_definition_id' => $whatsappDef->id,
                    'value' => true,
                ]);
            }

            // Dynamic attributes from sheet
            foreach ($row['raw'] as $key => $value) {

                if ($value === null || $value === '' || $key === 'phone-number') {
                    continue; // skip empty + identifier
                }

                if ($definitions->has($key)) {
                    ContactAttribute::updateOrCreate(
                        [
                            'contact_id' => $contact->id,
                            'attribute_definition_id' => $definitions[$key]->id,
                        ],
                        [
                            'value' => $value,
                        ]
                    );
                }
            }

            $created[] = new Contact($contact);
        }


        return $this->response(true, 'Import completed', [
            'created' => $created,
            'invalid_entries' => $invalid,
            'processing_method' => 'synchronous'
        ]);
    }

    public function show(Organization $organization, ContactEntity $contact)
    {
        // Ensure the contact belongs to this organization
        if ($contact->organization_id !== $organization->id) {
            return $this->response(
                false,
                __('messages.contact_not_in_organization'),
                null,
                403
            );
        }

        // Eager load related data if needed (attributes, identifiers, etc.)
        $contact->load(['identifiers', 'attributes.attributeDefinition']);

        // Wrap it in your Contact response class
        $contactResource = new Contact($contact);

        return $this->response(
            true,
            'Contact retrieved successfully.',
            $contactResource
        );
    }
}
