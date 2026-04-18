<?php

namespace App\Http\Controllers;

use App\Http\Resources\WalletTranactionCollection;
use App\Models\Organization;
use App\Models\Wallet;
use App\Models\Workspace;
use App\Services\WalletAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Traits\WalletManager;
use App\Jobs\ExportWalletTransactionsJob;
use Illuminate\Http\JsonResponse;

class WalletController extends BaseApiController implements HasMiddleware
{
    use WalletManager;
    protected WalletAssignmentService $assignmentService;
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }

    public function __construct(WalletAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }
    /**
     * @OA\Get(
     *     path="/api/organizations/{organization}/wallets",
     *     summary="Get a list of wallet for the organization",
     *     tags={"Wallets"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of transactions per page",
     *         @OA\Schema(
     *             type="integer",
     *             example=15
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Operation successful"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=35),
     *                     @OA\Property(property="transaction_type", type="string", example="Usage transaction"),
     *                     @OA\Property(property="status", type="string", example="Pending"),
     *                     @OA\Property(property="amount", type="string", example="-100.00 SAR"),
     *                     @OA\Property(property="serivce_name", type="string", example="other"),
     *                     @OA\Property(property="description", type="string", example="Wallet recharge with -100 SAR"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-08-28T08:00:10.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(Request $request, Organization $organization)
    {
        $organizationWallets = $organization->wallets()->with(['service', 'wallettable'])->get();
        $workspaceWallets = $organization->workspaces()->with([
            'wallets' => function ($query) {
                $query->with(['service', 'wallettable']);
            }
        ])->get()->pluck('wallets')->flatten();
        $allWallets = $organizationWallets->concat($workspaceWallets);
        $wallets = $allWallets->map(function ($wallet) {
            return new \App\Http\Responses\Wallet($wallet);
        });
        return $this->response(
            true,
            trans('message.ok'),
            $wallets,
            200
        );
        // $walletTransactions = WalletTransaction::with(['wallet.service']) // Eager load wallet and service
        //     ->whereHas('wallet', function ($query) {
        //         $query->where('user_id', auth()->user()->id);
        //     })
        //     ->orderByDesc('id')
        //     ->paginate($request->get('per_page', 15));
        // return $this->response(true, trans('message.ok'), new WalletTranactionCollection($walletTransactions), 200);
    }

    /**
     * Display the specified wallet with its assignments
     * 
     * @OA\Get(
     *     path="/api/organizations/{organization}/wallets/{wallet}",
     *     summary="Get wallet details with assignments",
     *     description="Returns detailed information about a wallet including its assignments to workspaces and organization users",
     *     operationId="showWallet",
     *     tags={"Wallets"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="wallet",
     *         in="path",
     *         required=true,
     *         description="Wallet UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Wallet details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Wallet details retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="wallet",
     *                     type="object",
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="amount", type="string"),
     *                     @OA\Property(property="currency_code", type="string"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="system", type="string"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(
     *                     property="assignments",
     *                     type="object",
     *                     @OA\Property(
     *                         property="workspaces",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="string", format="uuid"),
     *                             @OA\Property(property="name", type="string"),
     *                             @OA\Property(property="description", type="string", nullable=true),
     *                             @OA\Property(property="assignment_id", type="string", format="uuid"),
     *                             @OA\Property(property="is_active", type="boolean")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="organization_users",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="string", format="uuid"),
     *                             @OA\Property(property="name", type="string"),
     *                             @OA\Property(property="email", type="string"),
     *                             @OA\Property(property="assignment_id", type="string", format="uuid"),
     *                             @OA\Property(property="is_active", type="boolean"),
     *                             @OA\Property(property="has_special_wallet", type="boolean")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="transactions",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="string", format="uuid"),
     *                         @OA\Property(property="amount", type="string"),
     *                         @OA\Property(property="type", type="string"),
     *                         @OA\Property(property="status", type="string"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Wallet or Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Not found"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */
    public function show(Organization $organization, Wallet $wallet): JsonResponse
    {
        // Verify the wallet belongs to the organization
        $walletBelongsToOrg = $wallet->wallettable()
            ->where(function ($query) use ($organization) {
                $query->where('organization_id', $organization->id)
                    ->orWhereHasMorph('wallettable', '*', function ($query) use ($organization) {
                        $query->where('organization_id', $organization->id);
                    });
            })
            ->exists();

        if (!$walletBelongsToOrg) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'data' => null
            ], 403);
        }

        // Load wallet with its relationships
        $wallet->load([
            'transactions' => function ($query) {
                $query->latest()->limit(10); // Get last 10 transactions
            },
            'assignments.assignable'
        ]);

        // Organize assignments by type
        $assignments = [
            'workspaces' => [],
            'organization_users' => []
        ];

        foreach ($wallet->assignments as $assignment) {
            $assignable = $assignment->assignable;

            if ($assignable) {
                $assignmentData = [
                    'id' => $assignable->id,
                    'assignment_id' => $assignment->id,
                    'is_active' => $assignment->is_active
                ];

                if ($assignable instanceof \App\Models\Workspace) {
                    $assignments['workspaces'][] = array_merge($assignmentData, [
                        'name' => $assignable->name,
                        'description' => $assignable->description
                    ]);
                } elseif ($assignable instanceof \App\Models\OrganizationUser) {
                    $assignments['organization_users'][] = array_merge($assignmentData, [
                        'name' => $assignable->user->name ?? 'N/A',
                        'email' => $assignable->user->email ?? 'N/A',
                        'has_special_wallet' => $assignable->has_special_wallet
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Wallet details retrieved successfully',
            'data' => [
                'wallet' => [
                    'id' => $wallet->id,
                    'name' => $wallet->name,
                    'amount' => $wallet->amount,
                    'currency_code' => $wallet->currency_code,
                    'status' => $wallet->status,
                    'system' => $wallet->system,
                    'created_at' => $wallet->created_at,
                    'updated_at' => $wallet->updated_at
                ],
                'assignments' => $assignments,
                'transactions' => $wallet->transactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'amount' => $transaction->amount,
                        'type' => $transaction->type,
                        'status' => $transaction->status,
                        'created_at' => $transaction->created_at
                    ];
                })
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/organizations/{organization}/wallets/transfer",
     *     summary="Transfer amount between wallets within the same organization",
     *     tags={"Wallets"},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"from_wallet_id", "to_wallet_id", "service", "amount"},
     *             @OA\Property(
     *                 property="from_wallet_id",
     *                 type="integer",
     *                 example=1,
     *                 description="Source wallet ID"
     *             ),
     *             @OA\Property(
     *                 property="to_wallet_id",
     *                 type="integer",
     *                 example=2,
     *                 description="Destination wallet ID"
     *             ),
     *             @OA\Property(
     *                 property="service",
     *                 type="string",
     *                 enum={"sms", "other"},
     *                 example="sms",
     *                 description="Type of service"
     *             ),
     *             @OA\Property(
     *                 property="amount",
     *                 type="integer",
     *                 example=100,
     *                 description="Amount to transfer"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful transfer",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Transfer successful"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="null",
     *                 example=null
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or wallets from different organizations",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Invalid transfer between different organizations"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="null",
     *                 example=null
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Wallet not found"
     *     )
     * )
     *
     * @param Request $request
     * @param Organization $organization
     * @return \Illuminate\Http\JsonResponse
     */
    public function transfer(Request $request, Organization $organization)
    {
        $request->validate([
            'to_wallet_id' => 'required|exists:wallets,id|different:from_wallet_id',
            'amount' => 'required|integer|min:1',
            'operation' => 'required|in:ADD,RETRIEVE'

        ]);

        $toWallet = Wallet::findOrFail($request->input('to_wallet_id'));
        $organizationWallet = $this->getObjectWallet($organization, $toWallet->service_id);

        if (!$this->belongsToSameOrganization($organizationWallet, $toWallet)) {
            return $this->response(false, trans('message.invalid_transfer_different_organizations'), null, 422);
        }

        $response = \DB::transaction(function () use ($organizationWallet, $toWallet, $request) {
            if (!$this->transferWallet($organizationWallet, $toWallet, $request->input('amount'), $toWallet->service->name, $request->input('operation'))) {
                return false;
            }
            return true;
        });
        if (!$response) {
            return $this->response(false, trans('message.msg_error_insufficient_balance'), null, 200);
        }

        return $this->response(true, trans('message.transfer_successful'), null, 200);
    }

    /**
     * Get paginated wallet transactions
     * 
     * @OA\Get(
     *     path="/api/organizations/{organization}/wallets/{wallet}/transactions",
     *     summary="Get wallet transactions",
     *     description="Retrieves paginated list of wallet transactions ordered by ID descending",
     *     operationId="getWalletTransactions",
     *     tags={"Wallets"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="wallet",
     *         in="path",
     *         required=true,
     *         description="Wallet ID",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OK"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="first_page_url", type="string"),
     *                 @OA\Property(property="from", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="last_page_url", type="string"),
     *                 @OA\Property(property="next_page_url", type="string", nullable=true),
     *                 @OA\Property(property="path", type="string"),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="prev_page_url", type="string", nullable=true),
     *                 @OA\Property(property="to", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Wallet or Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Not found"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */
    public function transaction(Request $request, Organization $organization, Wallet $wallet)
    {
        $walletTransactions = $transactions = $wallet->transactions()
            ->orderBy('id', 'desc')
            ->paginate(15); // You can adjust the number per page as needed
        return $this->response(true, trans('message.ok'), new WalletTranactionCollection($walletTransactions), 200);
    }

    /**
     * Get paginated organization wallets transactions
     * 
     * @OA\Get(
     *     path="/api/organizations/{organization}/wallets/transactions",
     *     summary="Get wallet transactions",
     *     description="Retrieves paginated list of wallet transactions ordered by ID descending",
     *     operationId="getOrganizationWalletsTransactions",
     *     tags={"Wallets"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="wallet",
     *         in="path",
     *         required=true,
     *         description="Wallet ID",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OK"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="first_page_url", type="string"),
     *                 @OA\Property(property="from", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="last_page_url", type="string"),
     *                 @OA\Property(property="next_page_url", type="string", nullable=true),
     *                 @OA\Property(property="path", type="string"),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="prev_page_url", type="string", nullable=true),
     *                 @OA\Property(property="to", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Wallet or Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Not found"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */

    public function organizationWalletTransaction(Request $request, Organization $organization)
    {
        $transactions = $organization->allTransactions()
        ->whereHas('wallet', function($query) {
            $query->where('service_id', 2);
        })
        ->paginate(15);

        return $this->response(true, trans('message.ok'), new WalletTranactionCollection($transactions), 200);
    }

    /**
     * Get wallet transaction statistics for organization
     *
     * @OA\Get(
     *     path="/api/organizations/{organization}/wallets/transactions/statistics",
     *     summary="Get wallet transaction statistics",
     *     description="Retrieves statistics including counts by status, total recharged amount, and subscription deductions",
     *     operationId="getOrganizationWalletTransactionStatistics",
     *     tags={"Wallets"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OK"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_transactions", type="integer", example=100),
     *                 @OA\Property(property="pending_count", type="integer", example=10),
     *                 @OA\Property(property="active_count", type="integer", example=80),
     *                 @OA\Property(property="cancelled_count", type="integer", example=10),
     *                 @OA\Property(property="total_recharged", type="number", format="float", example=5000.00),
     *                 @OA\Property(property="total_subscription_deductions", type="number", format="float", example=1500.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found"
     *     )
     * )
     */
    public function organizationWalletTransactionStatistics(Request $request, Organization $organization)
    {
        $baseQuery = $organization->allTransactions()
            ->whereHas('wallet', function($query) {
                $query->where('service_id', 2);
            });

        $totalTransactions = (clone $baseQuery)->count();
        $pendingCount = (clone $baseQuery)->where('status', 'pending')->count();
        $activeCount = (clone $baseQuery)->where('status', 'active')->count();
        $cancelledCount = (clone $baseQuery)->where('status', 'canceled')->count();

        // Category-based statistics
        $membershipTotal = (clone $baseQuery)
            ->where('category', 'membership')
            ->where('status', 'active')
            ->sum('amount');

        $inboxAgentTotal = (clone $baseQuery)
            ->where('category', 'inbox_agent')
            ->where('status', 'active')
            ->sum('amount');

        $hostingTotal = (clone $baseQuery)
            ->where('category', 'hosting')
            ->where('status', 'active')
            ->sum('amount');

        $chatbotTotal = (clone $baseQuery)
            ->where('category', 'chatbot')
            ->where('status', 'active')
            ->sum('amount');

        $walletFundingTotal = (clone $baseQuery)
            ->where('category', 'wallet_funding')
            ->where('status', 'active')
            ->sum('amount');

        $pendingTotal = (clone $baseQuery)
            ->where('status', 'pending')
            ->where('amount','<',0)
            ->sum('amount');

        $activeTotal = (clone $baseQuery)
            ->where('status', 'active')
            ->whereIn('category',['whatsapp'])
            ->where('amount','<',0)
            ->sum('amount');

        $cancelledTotal = (clone $baseQuery)
            ->where('status', 'canceled')
             ->where('amount','<',0)
            ->sum('amount');

        return $this->response(true, trans('message.ok'), [
            'total_transactions' => $totalTransactions,
            'pending_count' => $pendingCount,
            'pending_total' => $pendingTotal,
            'active_count' => $activeCount,
            'active_total' => $activeTotal,
            'cancelled_count' => $cancelledCount,
            'cancelled_total' => $cancelledTotal,
            'wallet_funding_total' => $walletFundingTotal,
            'membership_total' => $membershipTotal,
            'inbox_agent_total' => $inboxAgentTotal,
            'hosting_total' => $hostingTotal,
            'chatbot_total' => $chatbotTotal,
        ], 200);
    }

    /**
     * Export wallet transactions to Excel
     *
     * @OA\Post(
     *     path="/api/organizations/{organization}/wallets/transactions/export",
     *     summary="Export wallet transactions to Excel (sent via email)",
     *     tags={"Wallets"},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="Active"),
     *             @OA\Property(property="category", type="string", example="membership"),
     *             @OA\Property(property="transaction_type", type="string", example="charge"),
     *             @OA\Property(property="from_date", type="string", format="date", example="2024-01-01"),
     *             @OA\Property(property="to_date", type="string", format="date", example="2024-12-31")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Export job queued successfully"
     *     )
     * )
     */
    public function exportWalletTransactions(Request $request, Organization $organization)
    {
        $filters = $request->only(['status', 'category', 'transaction_type', 'from_date', 'to_date']);
        $userEmail = auth()->user()->email;
        $locale = app()->getLocale();

        ExportWalletTransactionsJob::dispatch($organization->id, $userEmail, $filters, $locale);

        return $this->response(true, trans('message.export_queued'), null, 200);
    }

    /**
     * @OA\Post(
     *     path="/api/organizations/{organization}/wallets",
     *     summary="Create a new wallet for the organization",
     *     tags={"Wallets"},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 example=1,
     *                 description="Wallet Name"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Wallet created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Wallet created successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="service_id", type="integer", example=1),
     *                 @OA\Property(property="balance", type="number", format="float", example=100.00),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-08-28T08:00:10.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-08-28T08:00:10.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Validation error"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="null",
     *                 example=null
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function store(Request $request, Organization $organization)
    {
        $request->validate([
            'name' => 'required',
            'service_id' => 'required|exists:services,id'
        ]);
        $wallet = $organization->wallets()->create([
            'name' => $request->name,
            'service_id' => $request->service_id,
            'user_id' => $organization->owner_id,
            'amount ' => 0,
            'sms_point' => 0,
            'currency_code' => 'SAR',
            'status' => 'Active',
            'type' => 'secondary'
        ]);
        return $this->response(true, trans('message.wallet_created_successfully'), $wallet, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/organizations/{organization}/wallets/{wallet}",
     *     summary="Update wallet details",
     *     tags={"Wallets"},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="wallet",
     *         in="path",
     *         required=true,
     *         description="Wallet ID",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 example="Updated Wallet Name",
     *                 description="Wallet Name"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="Active",
     *                 description="Wallet Status"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Wallet updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Wallet updated successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Updated Wallet Name"),
     *                 @OA\Property(property="status", type="string", example="Active"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-08-28T08:00:10.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Validation error"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="null",
     *                 example=null
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function update(Request $request, Organization $organization, Wallet $wallet)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'sometimes|string|in:Active,Inactive'
        ]);

        $wallet->update($request->only('name'));

        return $this->response(true, trans('message.wallet_updated_successfully'), $wallet, 200);
    }

    /**
     * Assign a wallet to an entity
     * 
     * @OA\Post(
     *     path="/api/organizations/{organization}/wallets/{wallet}/assignment",
     *     summary="Assign wallet to an entity (workspace or organization user)",
     *     description="Assigns the specified wallet to either a workspace or organization user",
     *     operationId="assignWallet",
     *     tags={"Wallets"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="wallet",
     *         in="path",
     *         required=true,
     *         description="Wallet UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"assignable_type", "assignable_id"},
     *             @OA\Property(
     *                 property="assignable_type",
     *                 type="string",
     *                 enum={"organization_user", "workspace"},
     *                 description="Type of entity to assign the wallet to"
     *             ),
     *             @OA\Property(
     *                 property="assignable_id",
     *                 type="string",
     *                 format="uuid",
     *                 description="ID of the entity to assign the wallet to"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Wallet assigned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Wallet assigned successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="wallet_id", type="string", format="uuid"),
     *                 @OA\Property(property="assignable_type", type="string"),
     *                 @OA\Property(property="assignable_id", type="string", format="uuid"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or invalid argument",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error"),
     *             @OA\Property(property="data", type="string", example="Invalid assignable type")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization or Wallet not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Not Found"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @param Organization $organization
     * @param Wallet $wallet
     * @return JsonResponse
     */
    public function assignment(Request $request, Organization $organization, Wallet $wallet)
    {
        $request->validate([
            'assignable_type' => 'required|in:organization_user,workspace',
            'assignable_id' => 'required'
        ]);

        try {
            $assignment = $this->assignmentService->assignWallet(
                $wallet,  // 'primary' or 'secondary'
                $request->assignable_type,  // 'organization_user' or 'workspace'
                $request->assignable_id
            );

            return $this->response(true, trans('message.wallet_assigned_successfully'), $assignment);
            // return response()->json(['message' => 'Wallet assigned successfully', 'data' => $assignment]);
        } catch (\InvalidArgumentException $e) {
            return $this->response(false, trans('message.error'), $e->getMessage(), 422);
            // return response()->json(['error' => $e->getMessage()], 422);
        }

    }

    /**
     * Get all assignments for a wallet
     * 
     * @OA\Get(
     *     path="/api/organizations/{organization}/wallets/{wallet}/assignments",
     *     summary="Get all possible assignments for a wallet",
     *     description="Retrieves all workspaces and organization users with their wallet assignment status",
     *     operationId="getWalletAssignments",
     *     tags={"Wallets"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="wallet",
     *         in="path",
     *         required=true,
     *         description="Wallet UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved assignments",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Assignments retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="workspaces",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="string", format="uuid"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="type", type="string", example="workspace"),
     *                         @OA\Property(property="is_assigned", type="boolean"),
     *                         @OA\Property(
     *                             property="assignment",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="string", format="uuid"),
     *                             @OA\Property(property="wallet_id", type="string", format="uuid"),
     *                             @OA\Property(property="is_active", type="boolean")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="organization_users",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="string", format="uuid"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="email", type="string"),
     *                         @OA\Property(property="type", type="string", example="organization_user"),
     *                         @OA\Property(property="is_assigned", type="boolean"),
     *                         @OA\Property(property="has_special_wallet", type="boolean"),
     *                         @OA\Property(
     *                             property="assignment",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="string", format="uuid"),
     *                             @OA\Property(property="wallet_id", type="string", format="uuid"),
     *                             @OA\Property(property="is_active", type="boolean")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Wallet does not belong to this organization",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Wallet does not belong to this organization")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization or Wallet not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error retrieving assignments")
     *         )
     *     )
     * )
     */
    public function getAssignments(Request $request, Organization $organization, Wallet $wallet)
    {
        // Verify the wallet belongs to the organization
        $walletBelongsToOrg = $wallet->wallettable()
            ->where(function ($query) use ($organization) {
                $query->where('organization_id', $organization->id)  // For direct organization relationships
                    ->orWhereHasMorph('wallettable', '*', function ($query) use ($organization) {
                        $query->where('organization_id', $organization->id);
                    });
            })
            ->exists();
        if (!$walletBelongsToOrg) {
            return response()->json(['error' => 'Wallet does not belong to this organization'], 403);
        }

        $assignments = $this->assignmentService->getAssignments($wallet, $organization->id);

        return response()->json([
            'message' => 'Assignments retrieved successfully',
            'data' => $assignments
        ]);
    }

    /**
     * Remove a wallet assignment
     * 
     * @OA\Delete(
     *     path="/api/organizations/{organization}/wallets/{wallet}/assignments",
     *     summary="Remove a wallet assignment",
     *     description="Removes the assignment of a wallet from a workspace or organization user",
     *     operationId="removeWalletAssignment",
     *     tags={"Wallets"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="wallet",
     *         in="path",
     *         required=true,
     *         description="Wallet UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"assignable_type", "assignable_id"},
     *             @OA\Property(
     *                 property="assignable_type",
     *                 type="string",
     *                 enum={"organization_user", "workspace"},
     *                 description="Type of entity to remove the wallet assignment from"
     *             ),
     *             @OA\Property(
     *                 property="assignable_id",
     *                 type="string",
     *                 format="uuid",
     *                 description="ID of the entity to remove the wallet assignment from"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Assignment removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Wallet assignment removed successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Assignment not found",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Wallet assignment not found"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="error",
     *                         type="string",
     *                         example="Invalid argument"
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="assignable_type",
     *                         type="array",
     *                         @OA\Items(type="string"),
     *                         example={"The assignable type field is required."}
     *                     ),
     *                     @OA\Property(
     *                         property="assignable_id",
     *                         type="array",
     *                         @OA\Items(type="string"),
     *                         example={"The assignable id field is required."}
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @param Organization $organization
     * @param Wallet $wallet
     * @return JsonResponse
     */
    public function removeAssignment(Request $request, Organization $organization, Wallet $wallet)
    {
        $request->validate([
            'assignable_type' => 'required|in:organization_user,workspace',
            'assignable_id' => 'required'
        ]);

        try {
            $removed = $this->assignmentService->removeAssignment(
                $wallet,
                $request->assignable_type,
                $request->assignable_id
            );

            if ($removed) {
                return response()->json([
                    'message' => 'Wallet assignment removed successfully'
                ]);
            }

            return response()->json([
                'error' => 'Wallet assignment not found'
            ], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

}
