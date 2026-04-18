<?php

namespace App\Http\Controllers;

use App\Http\Responses\ValidatorErrorResponse;
use App\Models\Organization;
use App\Models\Segment;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Validator;

class SegmentController extends BaseApiController
{
    /**
     * Display a listing of segments in a workspace.
     *
     * @OA\Get(
     *     path="api/workspaces/{workspaceId}/segments",
     *     summary="Display a listing of segments in a workspace.",
     *     operationId="listSegments",
     *     tags={"Segments"},
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
     *         description="Filter segments by name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
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
     *         description="Successful operation"
     *     )
     * )
     */
    public function index(Request $request, Organization $organization)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $name = $request->get('name');

        $query = Segment::where('organization_id', $organization->id);

        if ($name) {
            $query->where('name', 'like', '%' . $name . '%');
        }

        $segments = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $segments->items(),
            'pagination' => [
                'total' => $segments->total(),
                'per_page' => $segments->perPage(),
                'current_page' => $segments->currentPage(),
                'last_page' => $segments->lastPage(),
                'from' => $segments->firstItem(),
                'to' => $segments->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created segment in storage.
     *
     * @OA\Post(
     *     path="/api/workspaces/{workspaceId}/segments",
     *     summary="Store a newly created segment",
     *     operationId="storeSegment",
     *     tags={"Segments"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Segment data",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Active Users"),
     *             @OA\Property(property="rules", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="attribute_definition_id", type="string"),
     *                 @OA\Property(property="operator", type="string"),
     *                 @OA\Property(property="value", type="string", nullable=true)
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Segment created successfully")
     * )
     */
    public function store(Request $request, Organization $organization)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'rules' => 'required|array',
            'rules.*.attribute_definition_id' => 'required|uuid|exists:attribute_definitions,id',
            'rules.*.operator' => 'required|string',
            'rules.*.value' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $segment = Segment::create([
            'organization_id' => $organization->id,
            'name' => $request->input('name'),
            'description' => $request->input('description')
        ]);

        foreach ($request->input('rules') as $rule) {
            $segment->rules()->create($rule);
        }

        return $this->response(true, 'Segment created successfully', $segment, 201);
    }


    public function show(Organization $organization, Segment $segment)
    {
        if ($segment->organization_id !== $organization->id) {
            return response()->json(['message' => 'Segment not found'], 404);
        }

        return response()->json($segment->load('rules'));
    }

    /**
     * Update the specified segment.
     *
     * @OA\Put(
     *     path="/api/workspaces/{workspaceId}/segments/{segmentId}",
     *     summary="Update a segment",
     *     operationId="updateSegment",
     *     tags={"Segments"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Segment data to update",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Segment Name"),
     *             @OA\Property(property="rules", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="attribute_definition_id", type="string"),
     *                 @OA\Property(property="operator", type="string"),
     *                 @OA\Property(property="value", type="string", nullable=true)
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Segment updated successfully"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="Segment not found")
     * )
     */
    public function update(Request $request, Organization $organization, Segment $segment)
    {
        if ($segment->organization_id !== $organization->id) {
            return response()->json(['message' => 'Segment not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'rules' => 'nullable|array',
            'rules.*.id' => 'nullable|exists:segment_rules,id',
            'rules.*.attribute_definition_id' => 'required|uuid|exists:attribute_definitions,id',
            'rules.*.operator' => 'required|string',
            'rules.*.value' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Update the segment name
        $segment->update([
            'name' => $request->input('name'),
            'description' => $request->input('description')
        ]);

        // Handle rules if provided
        if ($request->has('rules')) {
            $rules = $request->input('rules');

            // Keep track of rule IDs
            $existingRuleIds = collect($rules)->pluck('id')->filter()->toArray();

            foreach ($rules as $ruleData) {
                if (isset($ruleData['id'])) {
                    // Update existing rule
                    $segment->rules()->find($ruleData['id'])->update($ruleData);
                } else {
                    // Add new rule
                    $segment->rules()->create($ruleData);
                }
            }

            // Remove rules not included in the update
            $segment->rules()->whereNotIn('id', $existingRuleIds)->delete();
        }

        return response()->json(['message' => 'Segment updated successfully', 'segment' => $segment->load('rules')], 200);
    }



    public function destroy(Organization $organization, Segment $segment)
    {
        if ($segment->organization_id !== $organization->id) {
            return $this->response(false,'Segment not found in this Workspace', null,404);
        }

        $segment->rules()->delete();
        $segment->contacts()->detach();
        $segment->delete();

        return response()->json(['message' => 'Segment deleted successfully']);
    }
}
