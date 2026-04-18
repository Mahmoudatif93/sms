<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\RequiredAction;
use Illuminate\Http\Request;

class RequiredActionController extends BaseApiController
{
    public function index(Request $request, Organization $organization)
    {
        // 1. Pagination parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        // 2. Build query with filters
        $query = RequiredAction::with(['workspace', 'actionable'])
            ->where('organization_id', $organization->id);

        if ($status = $request->query('status')) {
            if ($status === 'pending') {
                $query->whereNull('completed_at')->whereNull('dismissed_at');
            } elseif ($status === 'completed') {
                $query->whereNotNull('completed_at');
            } elseif ($status === 'dismissed') {
                $query->whereNotNull('dismissed_at');
            }
        }

        if ($type = $request->query('type')) {
            $query->where('actionable_type', $type);
        }

        if ($id = $request->query('id')) {
            $query->where('actionable_id', $id);
        }

        // 3. Paginate
        $requiredActions = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        // 4. Transform collection
        $transformed = $requiredActions->getCollection()->map(function ($action) {
            return new \App\Http\Responses\RequiredActionResponse($action);
        });

        $requiredActions->setCollection($transformed);

        // 5. Return formatted paginated response
        return $this->paginateResponse(true, 'Required Actions retrieved successfully.', $requiredActions);
    }
}
