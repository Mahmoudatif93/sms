<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\SmsApiController;
use App\Models\BalanceLog;
use App\Models\ChargeRequestBank;
use App\Models\CreditDebit;
use App\Models\MessageDetails;
use App\Models\PaymentPlan;
use App\Models\Sender;
use App\Models\User;
use App\Models\wallet;
use App\Repositories\BalanceTransferRepositoryInterface;
use App\Services\FileUploadService;
use DB;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Log;
use Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserController extends SmsApiController implements HasMiddleware
{
    protected FileUploadService $fileUploadService;
    protected BalanceTransferRepositoryInterface $BalanceTransferRepository;

    public function __construct(BalanceTransferRepositoryInterface $BalanceTransferRepository, FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->BalanceTransferRepository = $BalanceTransferRepository;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('check.admin'),
        ];
    }

    /**
     * Retrieve all users.
     *
     * @param Request $request
     * @return JsonResponse
     */

    public function index(Request $request)
    {

        $perPage = $request->get('per_page', 15); // Default to 15 if not provided
        $page = $request->get('page', 1);
        // Set the current page for the paginator
        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });
        $search = $request->search ?? null;
        $outboxs = User::with('ownedOrganizations')
            ->when(!empty($search), function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('number', 'like', '%' . $search . '%')
                        ->orWhere('name', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('id', 'DESC')
            ->paginate($perPage);
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

    public function show($id)
    {

        try {
            $User = User::findOrFail($id);
            return $this->response(true, 'User', $User);
        } catch (ModelNotFoundException $e) {
            return $this->response(false, 'User not found.', null, 404);
        }
    }

    public function destroy($id)
    {
        try {
            $User = User::findOrFail($id);
            $User->delete();
            return $this->response(true, __('message.msg_delete_row'));
        } catch (ModelNotFoundException $e) {
            return $this->response(false, 'User not found.', null, 404);
        }
    }

    public function showUserSenders($user_id)
    {
        $senders = Sender::where('user_id', $user_id)->get();

        return $this->response(true, 'senders', $senders);
    }

    public function viewbalancelogs(Request $request, $user_id)
    {

        $perPage = $request->get('per_page', 15); // Default to 15 if not provided
        $page = $request->get('page', 1);
        // Set the current page for the paginator
        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });
        $search = $request->search ?? null;
        $items = $this->BalanceTransferRepository->findlogs($user_id, $perPage, $search);
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
     * Generate OAuth token for the user (Admin impersonation)
     * Allows admin to log in as a user without password
     */
    public function getUserWithToken($id)
    {
        try {
            // Verify admin is authenticated
            $admin = auth('admin')->user();
            if (!$admin) {
                return $this->response(false, 'Unauthorized admin access', null, 401);
            }

            // Retrieve the user by ID
            $user = User::findOrFail($id);

            // Check if user is blocked or suspended
            if ($user->blocked) {
                return $this->response(false, 'User is blocked and cannot be accessed', null, 403);
            }

            $tokenId = Str::random(40);
            $expiresIn = now()->addYear()->timestamp;
            $expiresAt = now()->addYear();

            DB::table('oauth_access_tokens')->insert([
                'id' => $tokenId,
                'user_id' => $user->id,
                'client_id' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_ID'), // استخدم Password Client ID
                'name' => null,
                'scopes' => '["*"]',
                'revoked' => 0,
                'created_at' => now(),
                'updated_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            // Log the admin impersonation for security audit
            Log::info('Admin impersonation', [
                'admin_id' => $admin->id,
                'admin_username' => $admin->username,
                'user_id' => $user->id,
                'user_username' => $user->username,
                'timestamp' => now(),
                'ip' => request()->ip()
            ]);

            // Return user data with token
            return response()->json([
                'success' => true,
                'message' => 'User token generated successfully',
                'data' => [
                    'user' => $user,
                    'token' => [
                        'access_token' => $tokenId,
                        'token_type' => 'Bearer',
                        'expires_in' => $expiresIn,
                        'expires_at' => $expiresAt,
                    ],
                    'admin_info' => [
                        'admin_username' => $admin->username,
                        'impersonated_at' => now()->toDateTimeString(),
                    ]
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->response(false, 'User not found.', null, 404);
        } catch (Exception $e) {
            Log::error('Error generating user token: ' . $e->getMessage(), [
                'user_id' => $id,
                'admin_id' => auth('admin')->id(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->response(false, 'Failed to generate token', ['error' => $e->getMessage()], 500);
        }
    }

    public function UnSuspendUser($user_id)
    {

        try {
            $User = User::findOrFail($user_id);
            $data = ['suspended_at' => null];
            $User->update($data);
            return $this->response(true, 'User', $User);
        } catch (ModelNotFoundException $e) {
            return $this->response(false, 'User not found.', null, 404);
        }
    }

    public function update(Request $request, $id)
    {

        try {
            $User = User::findOrFail($id);
            $validated = $request->validate([
                //   'group_id' => 'exists:groups,id',
                'username' => 'string|unique:user,username,' . $User->id,
                'password' => 'string|min:8',
                'email' => 'email|unique:user,email,' . $User->id,
                'number' => 'string',
                'lang' => 'string',

            ]);

            if ($request->has('password')) {
                $validated['password'] = Hash::make($validated['password']);
            }

            $User->update($validated);

            return $this->response(true, 'User', $User);
        } catch (ModelNotFoundException $e) {
            return $this->response(false, 'User not found.', null, 404);
        }
    }

    /**
     * Attach a role to a user.
     */
    public function attachRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:iam_roles,id',
        ]);

        // Check if the role is already attached
        if ($user->IAMRoles()->where('iam_role_id', $validated['role_id'])->exists()) {
            return $this->response(false, 'Role already attached to the user.', null, 400);
        }

        // Attach the role
        $user->IAMRoles()->attach($validated['role_id']);

        return $this->response(true, 'Role attached successfully.', $user->load('IAMRoles'));
    }

    /**
     * Detach a role from a user.
     */
    public function detachRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:iam_roles,id',
        ]);

        // Check if the role is attached
        if (!$user->IAMRoles()->where('iam_role_id', $validated['role_id'])->exists()) {
            return $this->response(false, 'Role is not attached to the user.', null, 400);
        }

        // Detach the role
        $user->IAMRoles()->detach($validated['role_id']);

        return $this->response(true, 'Role detached successfully.', $user->load('IAMRoles'));
    }

    public function subaccounts($id)
    {
        try {
            $user = User::getAllSubaccounts($id);
            return $this->response(true, 'User', $user);
        } catch (ModelNotFoundException $e) {
            return $this->response(false, 'User not found.', null, 404);
        }
    }

}
