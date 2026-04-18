<?php

namespace App\Http\Controllers;

use App\Http\Responses\ValidatorErrorResponse;
use App\Models\AttributeDefinition;
use Illuminate\Http\Request;
use App\Http\Requests\StoreAttributeDefinitionRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class AttributeDefinitionController extends BaseApiController
{
    /**
     * GET /api/attribute-definitions
     * Show only global built-in attribute definitions
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $query = AttributeDefinition::builtin()->orderBy('created_at', 'desc');
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        $response = $paginated->getCollection()->map(
            fn($def) => new \App\Http\Responses\AttributeDefinition($def)
        );
        $paginated->setCollection($response);

        return $this->paginateResponse(true, 'Global Attribute Definitions retrieved successfully', $paginated);
    }

    /**
     * GET /api/organizations/{organization}/attribute-definitions
     * Show org-specific + global (for convenience)
     */
    public function indexForOrganization(Request $request, string $organizationId)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $query = AttributeDefinition::forOrgOrGlobal($organizationId)
            ->orderBy('created_at', 'desc');

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        $response = $paginated->getCollection()->map(
            fn($def) => new \App\Http\Responses\AttributeDefinition($def)
        );
        $paginated->setCollection($response);

        return $this->paginateResponse(true, 'Organization Attribute Definitions retrieved successfully', $paginated);
    }

    /**
     * POST /api/organizations/{organization}/attribute-definitions
     * Create a new org-specific definition
     */
    public function store(Request $request, string $organizationId)
    {

        $keyRule = Rule::unique('attribute_definitions', 'key')
            ->where(function ($q) use ($organizationId) {
                $q->whereNull('organization_id')
                    ->orWhere('organization_id', $organizationId);
            });

        $validator = Validator::make($request->all(), [
            'key' => [
                'required',
                'string',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $keyRule
            ],
            'display_name' => [
                'required',
                'string',
                'regex:/^[A-Z][A-Za-z0-9 ]*$/',
            ],
            'cardinality' => ['required', 'in:one,many'],
            'type' => ['required', 'in:boolean,datetime,number,string'],
            'pii' => ['required', 'boolean'],
            'read_only' => ['sometimes', 'boolean'],
            'builtin' => ['sometimes', 'boolean'],
        ], [
            'key.regex' => __('validation.custom.key.regex'),
            'display_name.regex' => __('validation.custom.display_name.regex'),
        ]);

        if ($validator->fails()) {
            return $this->response(
                false,
                'Validation Error(s)',
                new ValidatorErrorResponse($validator->errors()->toArray()),
                400
            );
        }

        $validated = $validator->validated();

        $validated['id'] = (string) Str::uuid();
        $validated['read_only'] = $validated['read_only'] ?? false;

        $definition = AttributeDefinition::create(
            [
                'id' => $validated['id'],
                'organization_id' => $organizationId,
                'key' => $validated['key'],
                'display_name' => $validated['display_name'],
                'cardinality' => $validated['cardinality'],
                'builtin' => false,
                'type' => $validated['type'],
                'pii' => $validated['pii'],
                'read_only' => $validated['read_only']
            ]
        );

        return $this->response(
            true,
            'Attribute Definition created successfully',
            new \App\Http\Responses\AttributeDefinition($definition)
        );
    }
}
