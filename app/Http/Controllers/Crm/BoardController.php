<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\BaseApiController;

use App\Http\Controllers\Controller;
use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BoardController  extends BaseApiController
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

        $boards = Board::with('assignedUser:id,name')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhereHas('assignedUser', function ($user) use ($search) {
                        $user->where('name', 'like', "%$search%");
                    });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->response(true, 'Boards retrieved successfully', [
            'data'       => $boards->items(),
            'pagination' => [
                'total'        => $boards->total(),
                'per_page'     => $boards->perPage(),
                'current_page' => $boards->currentPage(),
                'last_page'    => $boards->lastPage(),
                'from'         => $boards->firstItem(),
                'to'           => $boards->lastItem(),
            ],
        ]);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->response(false, "Validation Error(s)", $validator->errors(), 400);
        }

        $board = Board::create(array_merge($validator->validated(), ['id' => (string) Str::uuid()]));

        return response()->json(['success' => true, 'message' => 'Board created successfully', 'data' => $board], 201);
    }


    public function show($id)
    {
        $board = Board::with('assignedUser:id,name')->findOrFail($id);
        return $this->response(true, 'board retrieved successfully', $board, 200);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'color' => 'sometimes|string|max:7',
            'assigned_to' => 'sometimes|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->response(false, "Validation Error(s)", $validator->errors(), 400);
        }

        $board = Board::findOrFail($id);
        $board->update($validator->validated());

        return $this->response(true, 'board updated successfully', $board, 200);
    }

    public function destroy($id)
    {
        $board = Board::findOrFail($id);
        $board->delete();

        return $this->response(true, 'board deleted successfully', null, 200);
    }

    public function assignBoard(Request $request, Board $board)
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
            $board->update(['assigned_to' => $request->assigned_to]);

            return $this->response(true, 'board assigned successfully', $board, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error assigning board', ['error' => $e->getMessage()], 500);
        }
    }
}
