<?php

namespace App\Http\Controllers;

use App\Http\Responses\Contact;
use App\Http\Responses\ValidatorErrorResponse;
use App\Jobs\ProcessListContactsJob;
use App\Models\AttributeDefinition;
use App\Models\ContactAttribute;
use App\Models\ContactEntity;
use App\Models\IAMList;
use App\Models\Organization;
use App\Models\Workspace;
use App\Traits\ContactManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ListController extends BaseApiController
{
    use ContactManager;
    /**
     * @OA\Get(
     *     path="api/workspaces/{workspaceId}/lists",
     *     summary="Display a listing of IAM lists in a workspace.",
     *     operationId="index",
     *     tags={"Lists"},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         description="ID of the workspace",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Filter lists by name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="identifier",
     *         in="query",
     *         description="Filter lists to include only those with contacts having a specified identifier (e.g., phone-number, email)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="id", type="string", example="123e4567-e89b-12d3-a456-426614174000"),
     *                 @OA\Property(property="name", type="string", example="Customer List"),
     *                 @OA\Property(property="workspace_id", type="string", example="workspace123"),
     *                 @OA\Property(property="contacts_count", type="integer", example=5),
     *                 @OA\Property(property="type", type="string", example="general"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="List of customers"),
     *                 @OA\Property(property="created_at", type="integer", format="timestamp", example=1632332800),
     *                 @OA\Property(property="updated_at", type="integer", format="timestamp", example=1632927600)
     *             )),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=4),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Forbidden")
     *         )
     *     )
     * )
     */
    public function index(Request $request, Organization $organization)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $name = $request->get('name', null);
        $status = $request->get('status', null);

        $identifier = $request->get('identifier', null);

        $query = IAMList::where('organization_id', $organization->id)
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->with(['parent', 'children'])
            ->withCount('contacts');
        if ($name) {
            $query->where('name', 'like', '%' . $name . '%');
        }

        if ($identifier) {
            $query->whereHas('contacts.identifiers', function ($query) use ($identifier) {
                $query->where('key', $identifier);
            });
        }

        $query = $query->orderBy('created_at','desc');
        $lists = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'data' => $lists->items(),
            'pagination' => [
                'total' => $lists->total(),
                'per_page' => $lists->perPage(),
                'current_page' => $lists->currentPage(),
                'last_page' => $lists->lastPage(),
                'from' => $lists->firstItem(),
                'to' => $lists->lastItem(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/workspaces/{workspaceId}/lists",
     *     summary="Store a newly created list.",
     *     operationId="storeList",
     *     tags={"Lists"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="List information",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="Name of the list", example="Example List"),
     *             @OA\Property(property="description", type="string", description="Description of the list", example="This is an example list."),
     *             @OA\Property(property="contact_ids", type="array", description="Array of contact IDs",
     *                 @OA\Items(type="string", description="Contact ID", example="dd479c4d-7ce6-439e-9adc-6d128a1b6e2d")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="List created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", description="Success message", example="List created successfully"),
     *             @OA\Property(property="list", type="object", description="Created list object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation Error(s)",
     *         @OA\JsonContent(ref="#/components/schemas/ValidatorErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Workspace not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Workspace not found")
     *         )
     *     )
     * )
     */
    public function store(Request $request, Organization $organization)
    {
        if ($request->contact_ids === null || $request->contact_ids === '' || $request->contact_ids === "[]") {
            $request->merge(['contact_ids' => []]);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'parent_id' => [
                    'nullable',
                    'uuid',
                    'exists:lists,id',
                ],
                'contact_ids' => ['nullable', 'array', 'min:0'],
                'contact_ids.*' => ['nullable', 'string', 'exists:contacts,id'],
                'single_phone_number' => 'nullable|string',
                'phone_numbers_text' => 'nullable|string',
                'phone_numbers_file' => 'nullable|file|mimes:xlsx,xls',
            ]
        );

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        // Check if we have phone numbers to process
        $hasPhoneNumbers = $request->has('single_phone_number') ||
            $request->has('phone_numbers_text') ||
            $request->hasFile('phone_numbers_file');

        // Count records if we have phone numbers
        $recordCount = 0;
        $shouldUseJob = false;
        if ($hasPhoneNumbers) {
            $recordCount = $this->countRecordsInInput($request);
            $shouldUseJob = $recordCount > 50; // Use job for more than 50 records
        }
        
        // Create the list
        $list = IAMList::create([
            'organization_id' => $organization->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'parent_id' => $request->input('parent_id'),
            'status' => $shouldUseJob ? IAMList::STATUS_PENDING : IAMList::STATUS_ACTIVE,
        ]);

        // Handle existing contact_ids (immediate attachment)
        if ($request->has('contact_ids')) {
            $list->contacts()->attach($request->input('contact_ids'));
        }

        // Handle phone numbers
        if ($hasPhoneNumbers) {
            if ($shouldUseJob) {
                // Extract raw phone numbers and dispatch job
                $rawPhoneNumbers = $this->extractRawPhoneNumbers($request);
                $chunkSize = 1000;
                $chunks = array_chunk($rawPhoneNumbers, $chunkSize);
                $list->markAsPending(count($chunks));
                foreach ($chunks as $index => $chunk) {
                    ProcessListContactsJob::dispatch(
                        $list->id,
                        $organization->id,
                        $chunk,
                        auth()->id(),
                        $index
                    )->onQueue('imports');
                }
         

                return response()->json([
                    'message' => 'List created successfully. Contacts are being processed in the background.',
                    'list' => $list,
                    'status' => 'pending',
                    'total_contacts_to_process' => $recordCount
                ], 201);
            } else {
                // Process immediately for small lists
                try {
                    // Extract raw phone numbers and process them
                    $rawPhoneNumbers = $this->extractRawPhoneNumbers($request);
                    $processedResults = $this->processPhoneNumbersArray($rawPhoneNumbers, $organization->id);

                    $contactIds = [];
                    $createdContacts = 0;

                    foreach ($processedResults as $result) {
                        if ($result['is_valid']) {
                            $contact = $result['contact'];

                            // If contact doesn't exist, create it using the same logic as the job
                            if (!$contact) {
                                $contact = $this->createContactFromPhoneData($result, $organization->id);
                            }

                            if ($contact) {
                                $contactIds[] = $contact->id;
                                $createdContacts++;
                            }
                        }
                    }

                    if (!empty($contactIds)) {
                        $list->contacts()->attach($contactIds);
                    }

                    $list->update([
                        'processed_contacts' => $createdContacts,
                        'total_contacts' => $createdContacts
                    ]);

                } catch (\Exception $e) {
                    $list->markAsFailed($e->getMessage());
                    return response()->json([
                        'message' => 'List created but failed to process contacts: ' . $e->getMessage(),
                        'list' => $list
                    ], 422);
                }
            }
        }

        return response()->json(['message' => 'List created successfully', 'list' => $list], 201);
    }

    /**
     * Display the specified list.
     *
     * @OA\Get(
     *     path="/api/workspaces/{workspaceId}/lists/{listId}",
     *     summary="Show a list",
     *     description="Display a list by ID",
     *     operationId="showList",
     *     tags={"Lists"},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         description="ID of the workspace",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="listId",
     *         in="path",
     *         description="ID of the list to show",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List found",
     *         @OA\JsonContent(ref="#/components/schemas/IAMList")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="List not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="List not found")
     *         )
     *     )
     * )
     */
    public function show(Organization $organization, IAMList $list)
    {
        $list = IAMList::where('organization_id', '=', $organization->id)->withCount('contacts')->with(['parent', 'children'])->find($list->id);
        if (!$list) {
            return $this->response(false, 'List not found', null, 404);
        }
        return $this->response(true, 'List found', $list, 200);
    }

    public function showWithoutRelations(Organization $organization, IAMList $list)
    {
        $list = IAMList::where('organization_id', '=', $organization->id)->withCount('contacts')->find($list->id);
        if (!$list) {
            return $this->response(false, 'List not found', null, 404);
        }
        return $this->response(true, 'List found', $list, 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization, IAMList $list)
    {
        IAMList::where('organization_id', $organization->id)
            ->where('parent_id', $list->id)->update(['parent_id' => $list->parent_id]);
        $list->contacts()->detach();
        $list->delete();
        return response()->json(['message' => 'List and its contacts deleted successfully'], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/workspaces/{workspaceId}/lists/{id}",
     *     summary="Update a list",
     *     description="Update a list by ID",
     *     operationId="updateList",
     *     tags={"Lists"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the list to update",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/IAMList")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/IAMList")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="List not found",
     *
     *     )
     * )
     */
    public function update(Request $request, Organization $organization, IAMList $list)
    {
        // Handle empty contact_ids like in store function
        if ($request->contact_ids === null || $request->contact_ids === '' || $request->contact_ids === "[]") {

            $request->merge(['contact_ids' => []]);
        }
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'parent_id' => 'nullable|uuid|exists:lists,id|not_in:' . $list->id,
                'contact_ids' => ['nullable', 'array', 'min:0'],
                'contact_ids.*' => ['nullable', 'string', 'exists:contacts,id'],
                // Add phone number attributes (same as store function)
                'single_phone_number' => 'nullable|string',
                'phone_numbers_text' => 'nullable|string',
                'phone_numbers_file' => 'nullable|file|mimes:xlsx,xls',
            ]
        );

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        // Update basic list information
        $list->update([
            'name' => $request->name,
            'description' => $request->description,
            'parent_id' => $request->input('parent_id'),
        ]);

        // Handle contact_ids sync (existing functionality)
        if ($request->has('contact_ids') && !empty($request->contact_ids)) {
            \Log::info($request->contact_ids);
            $list->contacts()->sync($request->contact_ids);
        }

        // Check if we have phone numbers to process (same logic as store)
        $hasPhoneNumbers = $request->has('single_phone_number') ||
            $request->has('phone_numbers_text') ||
            $request->hasFile('phone_numbers_file');

        if ($hasPhoneNumbers) {
            // Count records if we have phone numbers
            $recordCount = 0;
            $shouldUseJob = false;

            if ($request->has('single_phone_number')) {
                $recordCount = 1;
            } elseif ($request->has('phone_numbers_text') || $request->hasFile('phone_numbers_file')) {
                try {
                    $recordCount = $this->countRecordsInInput($request);
                    $shouldUseJob = $recordCount > 50; // Use job for large datasets
                } catch (\Exception $e) {
                    return $this->response(false, 'Error counting records: ' . $e->getMessage(), null, 400);
                }
            }

            if ($shouldUseJob) {
                // Process in background for large datasets
                try {
                    $rawPhoneNumbers = $this->extractRawPhoneNumbers($request);

                    // Set list status to pending
                    $list->markAsPending($recordCount);

                    // Dispatch job
                    ProcessListContactsJob::dispatch(
                        $list->id,
                        $organization->id,
                        $rawPhoneNumbers,
                        auth()->id()
                    )->onQueue('imports');

                    return $this->response(true, 'List updated successfully. Contacts are being processed in the background.', [
                        'list' => $list->fresh(),
                        'status' => 'pending',
                        'total_contacts_to_process' => $recordCount
                    ], 200);
                } catch (\Exception $e) {
                    return $this->response(false, 'Error processing phone numbers: ' . $e->getMessage(), null, 500);
                }
            } else {
                // Process immediately for small datasets
                try {
                    $rawPhoneNumbers = $this->extractRawPhoneNumbers($request);
                    $processedContacts = $this->processPhoneNumbersArray($rawPhoneNumbers, $organization->id);

                    $created = [];
                    foreach ($processedContacts as $contactData) {
                        if ($contactData['is_valid']) {
                            $contact = $contactData['contact'];

                            // If contact doesn't exist, create it
                            if (!$contact) {
                                $contact = $this->createContactFromPhoneData($contactData, $organization->id);
                            }

                            if ($contact) {
                                $created[] = $contact;
                                // Add contact to the list (don't sync, just add)
                                $list->contacts()->syncWithoutDetaching([$contact->id]);
                            }
                        }
                    }

                    // Update list totals
                    $list->update([
                        'total_contacts' => $list->contacts()->count(),
                        'processed_contacts' => $list->contacts()->count(),
                    ]);

                    return $this->response(true, 'List updated successfully', [
                        'list' => $list->fresh(),
                        'contacts_added' => count($created)
                    ], 200);
                } catch (\Exception $e) {
                    return $this->response(false, 'Error processing phone numbers: ' . $e->getMessage(), null, 500);
                }
            }
        }

        return $this->response(true, 'List updated successfully', $list->fresh(), 200);
    }

    /**
     * @OA\Get(
     *     path="/api/workspaces/{workspaceId}/lists/{listId}/contacts",
     *     summary="Display a listing of the contacts by list ID.",
     *     description="Retrieve a list of contacts associated with a specific list.",
     *     operationId="viewContactsByListId",
     *     tags={"Lists"},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="listId",
     *         in="path",
     *         required=true,
     *         description="ID of the list",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of records per page",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contacts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Contact")
     *             ),
     *             @OA\Property(property="pagination", type="object", description="Pagination details"),
     *             @OA\Property(property="message", type="string", description="Success message")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Workspace or List not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Workspace or List not found")
     *         )
     *     )
     * )
     */
    public function viewContactsByListId(Request $request, Organization $organization, IAMList $list)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $contacts = $list->contacts()->paginate($perPage, ['*'], 'page', $page);
        $response = $contacts->getCollection()->map(function ($contact) {
            return new Contact($contact);
        });
        $contacts->setCollection($response);
        return $this->paginateResponse(true, 'Contacts retrieved successfully', $contacts);
    }

    public function getAllListsWithFilteredRelations(Organization $organization)
    {
        if (!$organization) {
            return $this->response(false, 'Organization not found', null, 404);
        }
        $lists = IAMList::where('organization_id', $organization->id)
            ->with([
                'childrenWithContacts.allContacts.identifiers' => function ($query) {
                    $query->select('id', 'contact_id', 'key', 'value')
                        ->where('key', '!=', 'email-address')
                        ->where('key', '!=', 'fingerprint');
                },
                'allContacts.identifiers' => function ($query) {
                    $query->select('id', 'contact_id', 'key', 'value')
                        ->where('key', '!=', 'email-address')
                        ->where('key', '!=', 'fingerprint');
                },
            ])
            ->get(['id', 'name', 'organization_id', 'type', 'description', 'created_at', 'updated_at']);

        $response = $lists->map(fn($list) => $this->transformList($list));

        return $this->response(true, 'Lists', $response, 200);
    }

    private function transformList(IAMList $list): array
    {
        // Transform contacts and exclude children without valid contacts
        $contacts = $this->transformContacts($list->allContacts);
        $children = $list->childrenWithContacts
            ->filter(function ($child) {
                // Only include children that have valid contacts after filtering
                return $this->transformContacts($child->allContacts);
            })
            ->map(fn($child) => [
                'id' => $child->id,
                'name' => $child->name,
                'organization_id' => $child->organization_id,
                'type' => $child->type,
                'description' => $child->description,
                'created_at' => $child->created_at,
                'updated_at' => $child->updated_at,
                'contacts' => $this->transformContacts($child->allContacts),
            ])
            ->toArray();

        return [
            'id' => $list->id,
            'name' => $list->name,
            'organization_id' => $list->organization_id,
            'type' => $list->type,
            'description' => $list->description,
            'created_at' => $list->created_at,
            'updated_at' => $list->updated_at,
            'contacts' => $contacts,
            'children' => $children,
        ];
    }


    private function transformContacts($contacts): array
    {
        return $contacts->filter(function ($contact) {
            // Only include contacts that have at least one identifier
            return $contact->identifiers->isNotEmpty();
        })->map(fn($contact) => [
                'id' => $contact->id,
                'organization_id' => $contact->organization_id,
                'created_at' => $contact->created_at,
                'updated_at' => $contact->updated_at,
                'identifiers' => $contact->identifiers->map(fn($identifier) => [
                    'id' => $identifier->id,
                    'key' => $identifier->key,
                    'value' => $identifier->value,
                ])->toArray(),
            ])->toArray();
    }

    /**
     * @OA\Delete(
     *     path="/api/workspaces/{workspaceId}/lists/{listId}/contacts/{contactId}",
     *     summary="Detach a contact from a list",
     *     description="Remove the association between a contact and a list",
     *     operationId="detachContact",
     *     tags={"Lists"},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="listId",
     *         in="path",
     *         required=true,
     *         description="ID of the list",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="contactId",
     *         in="path",
     *         required=true,
     *         description="ID of the contact to detach",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contact detached successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Contact detached from list successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Contact not found in list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Contact does not belong to this list")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="List or Contact not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="List or Contact not found")
     *         )
     *     )
     * )
     */
    public function detachContact(Organization $organization, IAMList $list, ContactEntity $contact)
    {
        // Check if list belongs to organization
        if ($list->organization_id !== $organization->id) {
            return $this->response(false, 'List not found in this organization', null, 404);
        }

        // Check if contact belongs to organization
        if ($contact->organization_id !== $organization->id) {
            return $this->response(false, 'Contact not found in this organization', null, 404);
        }

        // Check if contact is attached to the list
        if (!$list->contacts()->where('contacts.id', $contact->id)->exists()) {
            return $this->response(false, 'Contact does not belong to this list', null, 400);
        }

        // Detach the contact from the list
        $list->contacts()->detach($contact->id);

        // Update list totals
        $list->update([
            'total_contacts' => $list->contacts()->count(),
            'processed_contacts' => $list->contacts()->count(),
        ]);

        return $this->response(true, 'Contact detached from list successfully');
    }
}

