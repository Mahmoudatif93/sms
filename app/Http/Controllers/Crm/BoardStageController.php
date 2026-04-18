<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\BaseApiController;
use App\Models\BoardStage;
use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Pagination\Paginator;

class BoardStageController extends BaseApiController
{
    public function index(Request $request, $boardId)
    {
        $search  = $request->query('search');
        $perPage = $request->query('per_page', 15);
        $page    = $request->query('page', 1);

        Paginator::currentPageResolver(fn() => $page);

        $stages = BoardStage::where('board_id', $boardId)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('color', 'like', "%{$search}%")
                        ->orWhere('position', $search);
                });
            })
            ->orderBy('position')
            ->paginate($perPage);

        return $this->response(true, 'Board stages retrieved successfully', [
            'data'       => $stages->items(),
            'pagination' => [
                'total'        => $stages->total(),
                'per_page'     => $stages->perPage(),
                'current_page' => $stages->currentPage(),
                'last_page'    => $stages->lastPage(),
                'from'         => $stages->firstItem(),
                'to'           => $stages->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'board_id' => 'required|uuid|exists:boards,id',
            'position'           => 'nullable|integer',
            'name'     => 'required|string|max:255',
            'color'    => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'data'    => $validator->errors()
            ], 400);
        }

        try {
            $stage = BoardStage::create([
                'id'       => (string) Str::uuid(),
                'board_id' => $request->board_id,
                'name'     => $request->name,
                'color'    => $request->color,
            ]);

            return $this->response(true, 'Board stage created successfully', $stage, 201);
        } catch (\Exception $e) {
            return $this->response(false, 'Error creating board stage', ['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, BoardStage $boardStage)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'sometimes|string|max:255',
            'position'           => 'nullable|integer',
            'color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'data'    => $validator->errors()
            ], 400);
        }

        try {
            $boardStage->update($validator->validated());
            return $this->response(true, 'Board stage updated successfully', $boardStage, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error updating board stage', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(BoardStage $boardStage)
    {
        try {
            $boardStage->delete();
            return $this->response(true, 'Board stage deleted successfully', null, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error deleting board stage', ['error' => $e->getMessage()], 500);
        }
    }
}
