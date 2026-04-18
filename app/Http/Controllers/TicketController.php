<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseApiController;
use App\Repositories\TicketsRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use Illuminate\Pagination\Paginator;
use App\Models\Organization;

class TicketController  extends BaseApiController implements HasMiddleware

{


    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }

    protected $TicketsRepository;
    public function __construct(TicketsRepositoryInterface $TicketsRepository)
    {
        $this->TicketsRepository = $TicketsRepository;
    }

    /**
     * @OA\Get(
     *     path="/api/SmsUsers/Ticketsuser",
     *     summary="View tickets",
     *     tags={"Tickets"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="pagination", type="object",
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
    public function index(Request $request, Organization $organization)
    {


        $organizationId = $organization->id;
        $perPage = $request->get('per_page', 15); // Default to 15 if not provided
        $page = $request->get('page', 1);
        // Set the current page for the paginator
        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });
        $search = $request->search ?? null;

        $items = $this->TicketsRepository->findall($perPage, $organizationId, $search);
        // Fetch paginated dataa
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
    /**
     * @OA\Get(
     *     path="/api/SmsUsers/Tickets/{id}",
     *     summary="Get details of a specific ticket",
     *     tags={"Tickets"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the ticket",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="tickets"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Ticket not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function show(Organization $organization, $id)
    {
        $tickets = $this->TicketsRepository->find($id);
        return $this->response(true, 'tickets', $tickets);
    }
    /**
     * @OA\Post(
     *     path="/api/SmsUsers/Tickets",
     *     summary="Create a new ticket",
     *     tags={"Tickets"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","content"},
     *             @OA\Property(property="title", type="string", maxLength=255, example="Issue with SMS delivery"),
     *             @OA\Property(property="content", type="string", maxLength=65535, example="I'm experiencing problems with SMS delivery. Messages are not being sent.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="ticket"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="errors"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function store(Request $request, Organization $organization)
    {
        //required|
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:65535',
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }
        $data = $request->all();
        $data['date'] = now();
        $data['user_id'] = Auth::id();  // Update with the authenticated user ID
        $data['organization_id'] =  $organization->id;
        $ticket = $this->TicketsRepository->create($data);
        return $this->response(true, 'ticket', $ticket);
    }


    /**
     * @OA\Put(
     *     path="/api/SmsUsers/Tickets/{id}",
     *     summary="Update a ticket",
     *     tags={"Tickets"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the ticket to update",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255, example="Updated ticket title"),
     *             @OA\Property(property="content", type="string", maxLength=65535, example="Updated ticket content")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="ticket"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
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
     *         description="Ticket not found"
     *     )
     * )
     */

    public function update(Request $request, Organization $organization, $id)
    {
        $data['user_id'] = Auth::id();  // Update with the authenticated user ID
        $data['organization_id'] =  $organization->id;
        $ticket = $this->TicketsRepository->update($id, $data);
        return $this->response(true, 'ticket', $ticket);
    }
    /**
     * @OA\Post(
     *     path="/api/SmsUsers/replay/{id}",
     *     summary="Add a reply to a ticket",
     *     tags={"Tickets"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the ticket to reply to",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="content", type="string", maxLength=65535, example="This is a reply to the ticket")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="tickets_replay"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error or Ticket not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="errors"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */

    public function replay(Request $request, Organization $organization, $id)
    {
        //required|
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:65535',
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }
        $ticket = $this->TicketsRepository->find($id);
        if (empty($ticket)) {
            return $this->response(false, 'errors', __('message.msg_error_proccess') , 400);
        }
        $data = $request->all();
        $data['date'] = now();
        $data['ticket_id'] = $id;
        $tickets_replay = $this->TicketsRepository->createTicketReply($data);
        $data2['update_date'] = now();
        $this->TicketsRepository->update($id, $data2);
        return $this->response(true, 'tickets_replay', $tickets_replay);
    }
}
