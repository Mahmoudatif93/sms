<?php

namespace App\Http\Controllers\SmsUsers;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseApiController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use App\Models\Favorit;
use App\Models\Workspace;
use App\Exports\FavoriteMessagesExport;
use Maatwebsite\Excel\Facades\Excel;

use App\Services\FileUploadService;
use App\Services\SendLoginNotificationService;
class FavoritSmsController extends BaseApiController implements HasMiddleware
{


    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }
    protected $SendEmailNotification;
    protected $fileUploadService;
    public function __construct(
        FileUploadService $fileUploadService,
        SendLoginNotificationService $SendEmailNotification
    ) {
        $this->SendEmailNotification = $SendEmailNotification;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * @OA\Get(
     *     path="/api/workspaces/{workspaceId}/sms/messages/favorites",
     *     summary="Get favorite SMS messages",
     *     tags={"Favorit SMS"},
     *     @OA\Parameter(
     *         name="all",
     *         in="query",
     *         description="Get all items without pagination",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for filtering items",
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
    public function index(Request $request, Workspace $workspace)
    {
        $perPage = $request->get('per_page', 15); // Default to 15 if not provided
        $page = $request->get('page', 1);
        $items = Favorit::where('workspace_id', $workspace->id)
            ->filter($request->only('text'))
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
        return $this->paginateResponse(true, trans('message.ok'), $items);
    }

    /**
     * @OA\Get(
     *     path="/api/workspaces/{workspaceId}/sms/messages/favorites/{id}",
     *     summary="Get a specific favorite SMS",
     *     description="Retrieve details of a specific favorite SMS message",
     *     operationId="showFavoritSms",
     *     tags={"Favorit SMS"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the favorite SMS to retrieve",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="favorit_sms"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="text", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Favorite SMS not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Favorite SMS not found"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function show(Workspace $workspace, $id)
    {
        $favorit_sms = Favorit::where('workspace_id', $workspace->id)
            ->where('id', $id)
            ->first();

        if (!$favorit_sms) {
            return $this->response(false, __('message.favorite_sms_not_found'), null, 404);
        }

        return $this->response(true, 'favorit_sms', $favorit_sms);
    }

    /**
     * @OA\Post(
     *     path="/api/workspaces/{workspaceId}/sms/messages/favorites",
     *     summary="Create a new favorite SMS",
     *     description="Store a new favorite SMS for the authenticated user",
     *     operationId="storeFavoritSms",
     *     tags={"Favorit SMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"text"},
     *             @OA\Property(property="text", type="string", maxLength=1024, example="This is a favorite SMS message")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="favorit_sms"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="text", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
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
    public function store(Request $request, Workspace $workspace)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'text' => 'required|string|max:1024',
            ]
        );

        // Return validation errors if any
        if ($validator->fails()) {
            return $this->response(false, trans('message.validation_error'), $validator->errors(), 400);
        }

        // Prepare data for creation
        $data = [
            'text' => $request->input('text'),
            'user_id' => Auth::id(),
            'workspace_id' => $workspace->id
        ];

        try {
            // Create the favorite SMS
            $favorit_sms = Favorit::create([
                'text' => $request->input('text'),
                'user_id' => Auth::id(),
                'workspace_id' => $workspace->id
            ]);

            // Return success response
            return $this->response(true, trans('message.created_successfully'), $favorit_sms);
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return $this->response(false, trans('message.error_occurred'), $e->getMessage(), 500);
        }
    }


    /**
     * @OA\Put(
     *     path="/api/workspaces/{workspaceId}/sms/messages/favorites/{id}",
     *     summary="Update a favorite SMS",
     *     tags={"Favorit SMS"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the favorite SMS to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="text", type="string", maxLength=1024)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="favorit_sms"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="text", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
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
     *     @OA\Response(
     *         response=404,
     *         description="Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Favorite SMS not found"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */

    public function update(Request $request, Workspace $workspace, $id)
    {

         // Validate request data
    $validator = Validator::make($request->all(), [
        'text' => 'required|string|max:1024',
    ]);
    
    if ($validator->fails()) {
        return $this->response(false, trans('message.validation_error'), $validator->errors(), 400);
    }
    
    try {
        // Find the favorite SMS record
        $favorit_sms = Favorit::where('id', $id)
                              ->where('workspace_id', $workspace->id)
                              ->first();
        
        // Check if record exists
        if (!$favorit_sms) {
            return $this->response(false, trans('message.not_found'), null, 404);
        }
        
        // Update the favorite SMS
        $favorit_sms->text = $request->input('text');
        $favorit_sms->save();
        
        // Return success response
        return $this->response(true, trans('message.updated_successfully'), $favorit_sms);
    } catch (\Exception $e) {
        // Handle any unexpected errors
        return $this->response(false, trans('message.error_occurred'), $e->getMessage(), 500);
    }
    }

    /**
     * @OA\Delete(
     *     path="/api/workspaces/{workspaceId}/sms/messages/favorites/{id}",
     *     operationId="deleteFavoritSms",
     *     tags={"Favorit SMS"},
     *     summary="Delete a favorite SMS",
     *     description="Deletes a specific favorite SMS by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the favorite SMS to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Favorite SMS deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Favorite SMS not found"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */

    public function destroy(Workspace $workspace, $id)
    {
        try {
            // Find the favorite SMS record
            $favorit_sms = Favorit::where('id', $id)
                                  ->where('workspace_id', $workspace->id)
                                  ->first();
            
            // Check if record exists
            if (!$favorit_sms) {
                return $this->response(false, trans('message.not_found'), null, 404);
            }
            
            // Delete the favorite SMS
            $favorit_sms->delete();
            
            // Return success response
            return $this->response(true, trans('message.msg_delete_row'));
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return $this->response(false, trans('message.error_occurred'), $e->getMessage(), 500);
        }

      
    }
    /**
     * @OA\Delete(
     *     path="/api/workspaces/{workspaceId}/sms/messages/favorites/bulk-delete",
     *     operationId="deleteSelectedFavoritSms",
     *     tags={"Favorit SMS"},
     *     summary="Delete multiple favorite SMS messages",
     *     description="Deletes multiple favorite SMS messages by their IDs",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"),
     *                 description="Array of favorite SMS IDs to delete"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Selected favorite SMS messages deleted successfully")
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

    public function deleteSelected(Request $request,Workspace $workspace)
    {
       // Validate the request
    $validator = Validator::make($request->all(), [
        'ids' => 'required|array',
        'ids.*' => 'integer|exists:favorit,id',
    ]);

    if ($validator->fails()) {
        return $this->response(false, trans('message.validation_error'), $validator->errors(), 400);
    }

    try {
        $ids = $request->input('ids');
        
        // Find records belonging to the workspace and user
        $records = Favorit::whereIn('id', $ids)
                          ->where('workspace_id', $workspace->id)
                          ->get();
        
        // If no records found or some IDs don't belong to the workspace/user
        if ($records->count() !== count($ids)) {
            // Optional: You can include the IDs of records that were found
            $foundIds = $records->pluck('id')->toArray();
            $notFoundIds = array_diff($ids, $foundIds);
            
            return $this->response(
                false, 
                trans('message.records_not_found_or_unauthorized'), 
                ['not_found_or_unauthorized' => $notFoundIds], 
                404
            );
        }
        
        // Delete the records
        foreach ($records as $record) {
            $record->delete();
        }
        
        return $this->response(true, trans('message.msg_delete_row'));
    } catch (\Exception $e) {
        return $this->response(false, trans('message.error_occurred'), $e->getMessage(), 500);
    }
    }


    public function export(Request $request, $workspaceId)
    {

        $query = Favorit::where('workspace_id', $workspaceId)
            ->filter($request->only('text'));

        $totalRecords = $query->count();
        $fileName = 'favoriteMessage_export_' . time() . '.xlsx';
        $filePath = 'exports/' . $fileName;

        if ($totalRecords === 0) {
            return response()->json(['message' => 'No favorite messages found for export.'], 404);
        }
        $excelContent = Excel::raw(new FavoriteMessagesExport($query, 100000), \Maatwebsite\Excel\Excel::XLSX);
        $this->fileUploadService->uploadFromContent($excelContent, 'oss', $filePath);
        $fileUrl = $this->fileUploadService->getSignUrl($filePath, 3600);
        if ($totalRecords > 100000) {
            $body = 'Your Export is Ready: ' . $fileUrl;
            $this->SendEmailNotification->sendEmailNotification(
                Auth::user()->email,
                'Sms Export',
                'Dreams SMS',
                $body
            );
            return $this->response(true, 'Data exceeds 100,000 records. The file will be sent to your email..', 'email');

        }

        return Excel::download(new FavoriteMessagesExport($query, 100000), $fileName);

    }
}
