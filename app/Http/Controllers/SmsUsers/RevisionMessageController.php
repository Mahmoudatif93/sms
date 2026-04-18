<?php

namespace App\Http\Controllers\SmsUsers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use App\Rules\ValidSender;
use App\Models\Workspace;
use App\Models\MessageDetails;
use App\Models\Message;
use App\Models\User;
use App\Services\FileUploadService;
use App\Http\Responses\ValidatorErrorResponse;
use App\Models\MessageStatistic;
use App\Helpers\Sms\MessageValidationHelper;
use Illuminate\Validation\ValidationException;
use App\Class\SmsProcessorFactory;
use App\Helpers\Sms\MessageHelper;
use App\Http\Controllers\SmsApiController;
use App\Services\Sms;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;

class RevisionMessageController  extends SmsApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/SmsUsers/RevisionMessage",
     *     summary="Get revision messages",
     *     description="Retrieve a list of revision messages with optional search and pagination",
     *     tags={"Revision Messages"},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for filtering messages",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="all",
     *         in="query",
     *         description="If true, returns all messages without pagination",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page (default: 15)",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number (default: 1)",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index(Request $request,Workspace $workspace)
    {
            $search = $request->search ?? null;
            $perPage = $request->get('per_page', 15); // Default to 15 if not provided
            $page = $request->get('page', 1);
            // Set the current page for the paginator
            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });
            $items = Message::where([
                'workspace_id'=>$workspace->id,
                'advertising' => 1,
                'status' => 2
            ]) ->orderBy('id','DESC')->paginate($perPage, ['*'], 'page', $page);
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

    /**
     * @OA\Get(
     *     path="/api/SmsUsers/message/reject/{id}",
     *     summary="Reject a revision message",
     *     description="Reject a revision message and delete it",
     *     operationId="rejectRevisionMessage",
     *     tags={"Revision Messages"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the message to reject",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Message deleted"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Message not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This Message Not Avaliable"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */


    public function reject(Workspace $workspace,$id)
    {
        $message = Message::where('workspace_id',$workspace->id)->find($id);
        if (!empty($message)) {
            Message::where('id', $id)->delete();
            return $this->response(true, 'Message deleted');
        }
        return $this->response(false, 'errors', 'This Message Not Avaliable', 404);
    }



    /**
     * @OA\Get(
     *     path="/api/SmsUsers/message/accept/{id}",
     *     summary="Accept a revision message",
     *     description="Accept a revision message and process it accordingly",
     *     operationId="acceptRevisionMessage",
     *     tags={"Revision Messages"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the message to accept",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Message Sent For Admin Review"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Message not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This Message Not Available"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function accept(Workspace $workspace,$id)
    {
        $message = Message::where('workspace_id',$workspace->id)->find($id);
        if (!empty($message)) {
            $user = auth()->user();
            $isReviewMessage = MessageHelper::isReviewMessage(
                $message->message,
                $message->sender_name,
                $user->isAllowUrl(),
                $user->isAllowSendBlock(),
                $message->count
            );
            if ($isReviewMessage) {
                $data['advertising'] = 1;
                Message::where('id', $id)->update($data);
                MessageHelper::SendReviewMessage();
                return $this->response(true, 'Message Sent For Admin Review');
            } else {
                $data['advertising'] = 0;
                Message::where('id', $id)->update($data);
                $processor = SmsProcessorFactory::createProcessor($message->count);
                $processor->sendMessage($message->id);
                return $this->response(true, 'Message Sent');
            }
        }
        return $this->response(false, 'errors', 'This Message Not Avaliable', 404);
    }


    /**
     * @OA\Get(
     *     path="/api/SmsUsers/message/numbers/{message_id}",
     *     summary="Get numbers for a specific message",
     *     description="Retrieves all phone numbers associated with a given message ID",
     *     operationId="getMessageNumbers",
     *     tags={"Revision Messages"},
     *     @OA\Parameter(
     *         name="message_id",
     *         in="path",
     *         required=true,
     *         description="ID of the message to get numbers for",
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
     *             @OA\Property(property="message", type="string", example="items"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="number", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Message not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Message not found"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */

     public function getMessageNumbers(Workspace $workspace,$message_id,Request $request)
     {


        $search = $request->search ?? null;

            $perPage = $request->get('per_page', 15); // Default to 15 if not provided
            $page = $request->get('page', 1);
            // Set the current page for the paginator
            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });
            $items =  Message::RevisionMessageDetails($message_id, $perPage, $search);
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


}
