<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Route;

class ResourcesController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/resources",
     *     summary="Get all resources",
     *     tags={"Resources"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Response(
     *         response=200,
     *         description="A list of resources",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Resource"))
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Retrieve search parameters
        $version = $request->query('version');
        $method = $request->query('method');
        $uri = $request->query('uri');
        $isActive = $request->query('is_active');
        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);

        // Build the query dynamically
        $query = Resource::query();

        if (!is_null($version)) {
            $query->where('version', $version);
        }

        if (!is_null($method)) {
            $query->where('method', $method);
        }

        if (!is_null($uri)) {
            $query->where('uri', 'LIKE', "%$uri%");
        }

        if (!is_null($isActive)) {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        // Fetch paginated resources
        $resources = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform each resource into response format
        $response = $resources->getCollection()->map(function ($resource) {
            return new \App\Http\Responses\Resource($resource);
        });

        // Replace collection with transformed data
        $resources->setCollection($response);

        return $this->paginateResponse(true, 'Resources retrieved successfully', $resources);
    }


    public function refresh(): JsonResponse
    {
        // Get all API routes
        $routes = collect(Route::getRoutes())->filter(function ($route) {
            return in_array('api', $route->gatherMiddleware());
        })->map(function ($route) {
            return [
                'method' => implode('|', $route->methods()),
                'uri' => $route->uri(),
            ];
        });

        foreach ($routes as $route) {
            // Extract version from the URI (assumes versioning is in the format 'vX' at the start of the URI)
            preg_match('/^v\d+/', $route['uri'], $matches);
            $version = $matches[0] ?? 'v1'; // Default to 'v1' if no version is found

            // Check if the resource already exists
            $existing = Resource::where('method', $route['method'])
                ->where('uri', $route['uri'])
                ->where('version', $version)
                ->first();

            if (!$existing) {
                // Create a new resource for any missing route
                Resource::create([
                    'method' => $route['method'],
                    'uri' => $route['uri'],
                    'version' => $version,
                    'is_active' => false, // New routes are inactive by default
                ]);
            }
        }

        return $this->response(true, 'Resources refreshed successfully.');
    }

    public function show(Resource $resource): JsonResponse
    {
        $response = new \App\Http\Responses\Resource($resource);

        return $this->response(true, 'Resource retrieved successfully.', $response);
    }


    /**
     * Toggle the active status of a resource.
     */
    public function toggle(Resource $resource): JsonResponse
    {
        $resource->is_active = !$resource->is_active;
        $resource->save();

        return $this->response(true, 'Resource status toggled successfully.');
    }
}
