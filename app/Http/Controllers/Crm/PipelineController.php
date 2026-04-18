<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\BaseApiController;
use App\Models\Pipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Pagination\Paginator;
use Auth;
use Illuminate\Support\Facades\Validator;


class PipelineController extends BaseApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth:api')];
    }

    public function index(Request $request)
    {
        $search  = $request->query('search', null);
        $perPage = $request->query('per_page', 15);
        $page    = $request->query('page', 1);

        Paginator::currentPageResolver(fn() => $page);

        $pipelines = Pipeline::with(['tabs', 'stages', 'assignedUser'])
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhereHas('tabs', fn($tabs) => $tabs->where('name', 'like', "%$search%"))
                    ->orWhereHas('stages', fn($stages) => $stages->where('name', 'like', "%$search%"));
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->response(true, 'Pipelines retrieved successfully', [
            'data'       => $pipelines->items(),
            'pagination' => [
                'total'        => $pipelines->total(),
                'per_page'     => $pipelines->perPage(),
                'current_page' => $pipelines->currentPage(),
                'last_page'    => $pipelines->lastPage(),
                'from'         => $pipelines->firstItem(),
                'to'           => $pipelines->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            //'status'      => 'required|exists:pipeline_stages,name', // Ensure status exists in PipelineStage
            'assigned_to' => 'nullable|exists:user,id', // Optional, defaults to auth user
            'color' => 'string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'data'    => $validator->errors(),
            ], 500);
        }

        $validated = $validator->validated();

        try {
            $pipeline = Pipeline::create([
                'id'          => Str::uuid(),
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status'      => $validated['status'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? auth()->id(),
                'color' => $validated['color'] ?? null,
            ]);

            return $this->response(true, 'Pipeline created successfully', $pipeline, 200);
        } catch (\Exception $e) {

            return $this->response(false, 'Error creating pipeline', ['error' => $e->getMessage()], 500);
        }
    }



    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'sometimes|exists:pipeline_stages,name', // Ensure status exists in PipelineStage
            'assigned_to' => 'nullable|exists:user,id',
            'color' => 'string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'data'    => $validator->errors(),
            ], 400);
        }

        try {
            $pipeline = Pipeline::findOrFail($id);
            $pipeline->update($validator->validated());

            return $this->response(true, 'Pipeline updated successfully', $pipeline, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error updating pipeline', ['error' => $e->getMessage()], 500);
        }
    }


    public function destroy(Pipeline $pipeline)
    {
        try {
            $pipeline->delete();
            return $this->response(true, 'Pipeline deleted successfully', null, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error deleting pipeline', ['error' => $e->getMessage()], 500);
        }
    }

    public function assignPipeline(Request $request, Pipeline $pipeline)
    {
        // Validate the assigned user exists
        $validated = Validator::make($request->all(), [
            'assigned_to' => 'required|exists:user,id',
        ], [
            'assigned_to.exists' => 'User not found.'
        ]);

        if ($validated->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'data' => $validated->errors(),
            ], 500);
        }

        try {
            $pipeline->update(['assigned_to' => $request->assigned_to]);

            return $this->response(true, 'Pipeline assigned successfully', $pipeline, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error assigning Pipeline', ['error' => $e->getMessage()], 500);
        }
    }
}
