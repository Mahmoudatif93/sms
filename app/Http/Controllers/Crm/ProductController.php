<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\BaseApiController;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Pagination\Paginator;
use App\Http\Responses\ValidatorErrorResponse;
use Illuminate\Support\Facades\Validator;

class ProductController extends BaseApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth:api')];
    }

    public function index(Request $request)
    {
        $search     = $request->query('search', null);
        $categoryId = $request->query('category_id', null);
        $perPage    = $request->query('per_page', 15);
        $page       = $request->query('page', 1);

        Paginator::currentPageResolver(fn() => $page);

        $products = Product::with('category')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('sku', 'like', "%$search%");
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->response(true, 'Products retrieved successfully', [
            'data'       => $products->items(),
            'pagination' => [
                'total'        => $products->total(),
                'per_page'     => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'from'         => $products->firstItem(),
                'to'           => $products->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'name'        => 'required|string',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'sku'         => 'required|string|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'visibility' => 'boolean'

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $product = Product::create([
                'id'          => Str::uuid(),
                'name'        => $request->name,
                'description' => $request->description,
                'price'       => $request->price,
                'stock'       => $request->stock,
                'sku'         => $request->sku,
                'category_id' => $request->category_id,
            ]);

            return $this->response(true, 'Product created successfully', $product, 201);
        } catch (\Exception $e) {
            return $this->response(false, 'Error creating product', ['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'stock' => 'sometimes|integer',
            'sku' => 'sometimes|string|unique:products,sku,' . $product->id,
            'category_id' => 'sometimes|exists:categories,id',
            'visibility' => 'sometimes|boolean'

        ]);

        try {
            $product->update($validated);
            return $this->response(true, 'Product updated successfully', $product, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error updating product', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Product $product)
    {
        try {
            $product->delete();
            return $this->response(true, 'Product deleted successfully', null, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error deleting product', ['error' => $e->getMessage()], 500);
        }
    }
    public function toggleVisibility(Product $product)
    {
        $product->visibility = !$product->visibility;
        $product->save();

        return response()->json([
            'message' => 'Visibility updated successfully',
            'visibility' => $product->visibility
        ]);
    }

}
