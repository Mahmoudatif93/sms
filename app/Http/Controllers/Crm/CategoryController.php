<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\BaseApiController;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Pagination\Paginator;
use App\Http\Responses\ValidatorErrorResponse;
use Illuminate\Support\Facades\Validator;

class CategoryController extends BaseApiController implements HasMiddleware
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

        $categories = Category::with('children')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%$search%");
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->response(true, 'Categories retrieved successfully', [
            'data'       => $categories->items(),
            'pagination' => [
                'total'        => $categories->total(),
                'per_page'     => $categories->perPage(),
                'current_page' => $categories->currentPage(),
                'last_page'    => $categories->lastPage(),
                'from'         => $categories->firstItem(),
                'to'           => $categories->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);


        $validator = Validator::make($request->all(), [
            'name'      => 'required|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'data' => $validator->errors()
            ], 400);
        }
        try {
            $category = Category::create([
                'id'        => Str::uuid(),
                'name'      => $request->name,
                'parent_id' => $request->parent_id ?? null,
            ]);

            return $this->response(true, 'Category created successfully', $category, 201);
        } catch (\Exception $e) {
            return $this->response(false, 'Error creating category', ['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'      => 'required|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        try {
            $category->update([
                'name'      => $validated['name'],
                'parent_id' => $validated['parent_id'] ?? null,
            ]);

            return $this->response(true, 'Category updated successfully', $category, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error updating category', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Category $category)
    {
        try {
            $category->delete();
            return $this->response(true, 'Category deleted successfully', null, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error deleting category', ['error' => $e->getMessage()], 500);
        }
    }
}
