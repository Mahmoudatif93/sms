<?php

namespace App\Http\Controllers\SmsUsers;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Repositories\OutboxRepositoryInterface;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Pagination\Paginator;

/**
 * @OA\Tag(
 *     name="Outbox",
 *     description="Operations related to outbox messages"
 * )
 */

class OutboxController extends BaseApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:api'
        ];
    }
    protected $outboxRepository;
    public function __construct(OutboxRepositoryInterface $outboxRepository)
    {
        $this->outboxRepository = $outboxRepository;
    }

    /**
     * @OA\Get(
     *     path="/api/SmsUsers/outbox",
     *     summary="Get list of outbox messages",
     *     tags={"Outbox"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of results per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="code", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="null"),
     *             @OA\Property(property="code", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
            $search = $request->search ?? null;
            $perPage = $request->get('per_page', 15); // Default to 15 if not provided
            $page = $request->get('page', 1);
            // Set the current page for the paginator
            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });
            // Fetch paginated dataa
            $outboxs = $this->outboxRepository->findAll(auth()->id(), $perPage,$search);
            // Customize the response
            return response()->json([
                'data' => $outboxs->items(),
                'pagination' => [
                    'total' => $outboxs->total(),
                    'per_page' => $outboxs->perPage(),
                    'current_page' => $outboxs->currentPage(),
                    'last_page' => $outboxs->lastPage(),
                    'from' => $outboxs->firstItem(),
                    'to' => $outboxs->lastItem(),
                ],
            ]);

    }
}
