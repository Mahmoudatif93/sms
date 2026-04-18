<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseApiController;
use App\Repositories\WhitelistipRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use App\Models\Whitelistip;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use App\Models\Organization;

class WhitelistIpController  extends BaseApiController implements HasMiddleware

{


    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }

    protected $WhitelistipRepository;
    public function __construct(WhitelistipRepositoryInterface $WhitelistipRepository)
    {
        $this->WhitelistipRepository = $WhitelistipRepository;
    }

    /**
     * @OA\Get(
     *     path="/api/organizations/{organization}/whitelist-ip",
     *     summary="Get whitelist IP addresses",
     *     description="Retrieve a list of whitelist IP addresses, with optional pagination and search",
     *     tags={"Whitelist IP"},
     *     @OA\Parameter(
     *         name="all",
     *         in="query",
     *         description="Retrieve all records without pagination",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for filtering results",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page (default: 15)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="from", type="integer"),
     *                 @OA\Property(property="to", type="integer")
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index(Request $request, Organization $organization)
    {
           $search = $request->search ?? null;
            $perPage = $request->get('per_page', 15); // Default to 15 if not provided
            $page = $request->get('page', 1);
            // Set the current page for the paginator
            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });
            // Fetch paginated dataa
            $items = $this->WhitelistipRepository->findall($organization->id, $perPage, $search);
            // Customize the response
            return response()->json([
                'data' => $items->items(),
                'pagination' => [
                    'total' => $items->total(),
                    'per_page' => $items->perPage(),
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'from' => $items->firstItem(),
                    'to' => $items->lastItem(),
                ],
            ]);

    }


    public function store(Request $request, Organization $organization)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'ip' => 'required|ip'
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }
        $data = $request->all();
        $data['organization_id'] =  $organization->id;
        $Whitelistip = $this->WhitelistipRepository->create($data);
        return $this->response(true, 'Whitelistip', $Whitelistip);
    }


    /**
     * @OA\Delete(
     *     path="/api/organizations/{organization}/whitelist-ip/{whitelistIp}",
     *     summary="Delete a specific Whitelist IP entry",
     *     tags={"Whitelist IP"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the Whitelist IP entry to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Row deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Whitelist IP entry not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Whitelist IP entry not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */

    public function destroy(Organization $organization,Whitelistip $whitelistIp )
    {
        if ($whitelistIp->organization_id !== $organization->id) {
            return $this->response(false, 'errors', ['whielistip' => 'Unauthorized access'], 403);
        }
        $whitelistIp->delete();
        return $this->response(true, __('message.msg_delete_row'));
    }


    
    /**
     * @OA\Post(
     *     path="/api/organizations/{organization}/whitelist-ip/bulk-delete",
     *     summary="Delete multiple Whitelist IP entries",
     *     tags={"Whitelist IP"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"),
     *                 description="Array of Whitelist IP entry IDs to delete"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Rows deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="errors"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function deleteSelected(Request $request,Organization $organization)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:whitelist_ip,id', // Ensure this matches your table name
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }
        $ids = $request->input('ids');
        // Delete the records
        Whitelistip::whereIn('id', $ids)->delete();
        return $this->response(true,  __('message.msg_delete_row'));
    }
}
