<?php

namespace App\Http\Controllers;

use App\Models\IAMPolicy;
use DB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Validator;

class IAMPolicyController extends BaseApiController
{
    /**
     * Get all IAM Policies with their definitions.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $query = IAMPolicy::query();

        // Apply filters if any
        if ($request->has('name')) {
            $query->where('name', 'LIKE', '%' . $request->query('name') . '%');
        }

        $policies = $query->with('definitions')->paginate($perPage, ['*'], 'page', $page);

        return $this->paginateResponse(true, 'IAM Policies retrieved successfully', $policies);
    }

    /**
     * Get a specific IAM Policy and its definitions.
     */
    public function show(IAMPolicy $policy): JsonResponse
    {
        $policy->load('definitions');
        return $this->response(true, 'IAM Policy retrieved successfully', $policy);
    }

    /**
     * Create a new IAM Policy with at least one definition.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'definitions' => 'required|array|min:1',
            'definitions.*' => 'required|exists:iam_policy_definitions,id|distinct',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
        }

        // Begin transaction
        DB::beginTransaction();

        try {
            // Create the IAM Policy
            $policy = IAMPolicy::create([
                'name' => $request->input('name'),
                'description' => $request->input('description', ''),
                'type' => 'managed',
                'scope' => 'organization',
            ]);

            // Attach definitions
            $definitions = $request->input('definitions');
            foreach ($definitions as $definition) {
                $policy->definitions()->attach($definition);
            }

            // Commit transaction
            DB::commit();

            // Load the definitions for the response
            $policy->load('definitions');

            return $this->response(true, 'IAM Policy created successfully', $policy, 201);

        } catch (Exception $e) {
            // Rollback transaction in case of error
            DB::rollBack();
            return $this->response(false, 'Failed to create IAM Policy', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Attach a definition to a policy.
     */
    public function attachDefinitions(Request $request, IAMPolicy $policy): JsonResponse
    {
        $validated = $request->validate([
            'definition_ids' => 'required|array|min:1',
            'definition_ids.*' => 'exists:iam_policy_definitions,id|distinct',
        ]);

        $definitionIds = $validated['definition_ids'];

        // Check for already attached definitions
        $alreadyAttached = $policy->definitions()
            ->whereIn('iam_policy_definition_id', $definitionIds)
            ->pluck('iam_policy_definition_id')
            ->toArray();

        if (!empty($alreadyAttached)) {
            return $this->response(false, 'Some definitions are already attached to this policy.', [
                'already_attached' => $alreadyAttached,
            ], 400);
        }

        // Attach definitions
        $policy->definitions()->attach($definitionIds);

        return $this->response(true, 'Definitions attached successfully', $policy->load('definitions'));
    }

    /**
     * Detach a definition from a policy.
     */
    public function detachDefinitions(Request $request, IAMPolicy $policy): JsonResponse
    {
        $validated = $request->validate([
            'definition_ids' => 'required|array|min:1',
            'definition_ids.*' => 'exists:iam_policy_definitions,id|distinct',
        ]);

        $definitionIds = $validated['definition_ids'];

        // Check for definitions not attached
        $notAttached = array_diff($definitionIds, $policy->definitions()->pluck('iam_policy_definition_id')->toArray());

        if (!empty($notAttached)) {
            return $this->response(false, 'Some definitions are not attached to this policy.', [
                'not_attached' => $notAttached,
            ], 400);
        }

        // Detach definitions
        $policy->definitions()->detach($definitionIds);

        return $this->response(true, 'Definitions detached successfully', $policy->load('definitions'));
    }

    /**
     * Delete an IAM Policy.
     */
    public function destroy(IAMPolicy $policy): JsonResponse
    {
        // Detach all associated definitions
        $policy->definitions()->detach();

        // Delete the IAM Policy
        $policy->delete();

        return $this->response(true, 'IAM Policy deleted successfully');
    }
}
