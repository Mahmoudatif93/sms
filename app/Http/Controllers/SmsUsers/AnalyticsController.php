<?php

namespace App\Http\Controllers\SmsUsers;

use App\Helpers\CountHelper;
use App\Http\Controllers\BaseApiController;
use App\Models\BalanceLog;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Message;
use App\Models\Sender;
use App\Models\User;
use App\Models\Wallet;
use App\Models\IAMList;
use App\Models\ContactEntity;
use App\Models\WhatsappMessage;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends BaseApiController implements HasMiddleware
{

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
        ];
    }
    /**
     * Handle user registration request.
     *
     * @param Request $request
     * @return JsonResponse
     */

    public function getMessageCount(): JsonResponse
    {
        // Initial condition
        $conditions = [
            ['user_id', Auth::id()],
        ];
        $count = CountHelper::getCount(Message::class);
        return $this->response(true, 'message_count', $count);
    }

    public function getSentMessageCount(): JsonResponse
    {
        // Initial condition
        $conditions = [
            ['user_id', Auth::id()],
        ];
        // Dynamically add more status
        $conditions[] = ['status', 2];
        $count = CountHelper::getCount(Message::class, $conditions);
        return $this->response(true, 'Sent_message_count', $count);
    }

    public function getNotSentMessageCount(): JsonResponse
    {

        // Initial condition
        $conditions = [
            ['user_id', Auth::id()],
        ];
        // Dynamically add more status
        $conditions[] = ['status', '!=', 2];

        $count = CountHelper::getCount(Message::class, $conditions);
        return $this->response(true, 'Not_Sent_message_count', $count);
    }

    public function getConsumedPointsCount(): JsonResponse
    {
        // Initial condition
        $conditions = [
            ['user_id', Auth::id()],
        ];

        // Dynamically add more conditions
        $conditions[] = ['points_cnt', '<', 0];
        $count = CountHelper::getCount(BalanceLog::class, $conditions);
        return $this->response(true, 'Consumed_Points_count', $count);
    }

    public function getActiveSendersCount(): JsonResponse
    {
        // Initial condition
        $conditions = [
            ['user_id', Auth::id()],
        ];
        // Dynamically add more conditions
        $conditions[] = ['status', 1];
        $count = CountHelper::getCount(Sender::class, $conditions);

        return $this->response(true, 'Active_Senders_count', $count);
    }
    public function getNotActiveSendersCount(): JsonResponse
    {
        // Initial condition
        $conditions = [
            ['user_id', Auth::id()],
        ];
        // Dynamically add more conditions
        $conditions[] = ['status', '!=', 1];
        $count = CountHelper::getCount(Sender::class, $conditions);

        return $this->response(true, 'Not_Active_Senders_count', $count);
    }

    public function getContactGroupsCount(): JsonResponse
    {
        // Initial condition
        $conditions = [
            ['user_id', Auth::id()],
        ];
        $count = CountHelper::getCount(ContactGroup::class, $conditions);
        return $this->response(true, 'Contact_Groups_count', $count);
    }

    public function getContactsCount(): JsonResponse
    {
        // Initial condition
        $conditions = [
            ['user_id', Auth::id()],
        ];
        $count = CountHelper::getCount(Contact::class, $conditions);
        return $this->response(true, 'Contact_count', $count);
    }

    public function getAllCounts(Request $request): JsonResponse
    {
        $workspace = Workspace::findOrFail($request->query('workspace_id'));
        $whatsappAnalytics = $this->getWhatsappAnalytics($workspace);
        // workspace
        $allCounts = [
            'message_count' => CountHelper::getCount(Message::class, [['user_id', Auth::id()]]) + $whatsappAnalytics['whatsapp_messages_count'],
            'sent_message_count' => CountHelper::getCount(Message::class, [['user_id', Auth::id()], ['status', 2]]) + $whatsappAnalytics['whatsapp_sent_message_count'],
            'not_sent_message_count' => CountHelper::getCount(Message::class, [['user_id', Auth::id()], ['status', '!=', 2]]) + $whatsappAnalytics['whatsapp_not_sent_message_count'],
            'consumed_points_count' => CountHelper::getCount(BalanceLog::class, [['user_id', Auth::id()], ['points_cnt', '<', 0]]),
            'active_senders_count' => CountHelper::getCount(Sender::class, [['user_id', Auth::id()], ['status', 1]]) + $whatsappAnalytics['whatsapp_active_channels_count'],
            'not_active_senders_count' => CountHelper::getCount(Sender::class, [['user_id', Auth::id()], ['status', '!=', 1]]) + $whatsappAnalytics['whatsapp_not_active_channels_count'],
            'contact_groups_count' => CountHelper::getCount(IAMList::class, [['workspace_id', $request->query('workspace_id')]]),
            'contacts_count' => CountHelper::getCount(ContactEntity::class, [['workspace_id', $request->query('workspace_id')]]),
        ];

        return $this->response(true, 'all_counts', $allCounts);
    }

    public function getLastLoggedInAccounts(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15); // Default to 15 if not provided
        $page = $request->get('page', 1);
        // Retrieve the data with a single query
        $items = User::query()
            ->whereNotNull('last_login_date') // Ensure last_login_date is not null
            ->where(function ($query) {
                $query->where('id', Auth::id()) // Include parent account
                    ->orWhere('parent_id', Auth::id()); // Include sub-accounts
            })
            ->orderBy('last_login_date', 'desc')
            ->select([
                'id',
                'username',
                'last_login_ip',
                'last_login_date',
                DB::raw("CASE WHEN active = 1 THEN 'active' ELSE 'not' END AS active_status"),
            ])
            ->paginate($perPage); // Set the current page for the paginator
        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });

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

    public function getSentMessagesOverTime(Request $request): JsonResponse
    {
        // Get the date range from the request (optional)
        $startDate = $request->input('start_date', now()->subYear()->startOfDay()); // Default: 1 year ago
        $endDate = $request->input('end_date', now()->endOfDay()); // Default: today

        // Query for sent messages (status = 2) within the date range
        $data = Message::query()
            ->where(array('status' => 2, 'user_id' => Auth::id()))
            ->whereBetween('creation_datetime', [$startDate, $endDate])
            ->selectRaw('DATE(creation_datetime) as x, COUNT(*) as y')
            ->groupBy('x')
            ->orderBy('x', 'asc')
            ->get();

        // Format the response
        return $this->response(true, 'Sent messages over time retrieved successfully', $data);
    }

    public function getConsumedPointsOverTime(Request $request): JsonResponse
    {
        // Get the date range from the request (optional)
        $startDate = $request->input('start_date', now()->subYear()->startOfDay()); // Default: 1 year ago
        $endDate = $request->input('end_date', now()->endOfDay()); // Default: today

        // Query for consumed points (points_cnt < 0) within the date range
        $data = BalanceLog::query()
            ->where('user_id', Auth::id()) // Filter by the current user's ID
            ->where('points_cnt', '<', 0) // Only consumed points
            ->whereBetween('date', [$startDate, $endDate]) // Use the date range
            ->selectRaw('DATE(date) as x, SUM(points_cnt) as y') // Group by date
            ->groupBy('x')
            ->orderBy('x', 'asc')
            ->get();

        // Format the response
        return $this->response(true, 'Consumed points over time retrieved successfully', $data);
    }

    public function getLatestMessages(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15); // Default to 15 if not provided
        $page = $request->get('page', 1);
        // Set the current page for the paginator
        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });
        $items = Message::query()
            ->where(array('status' => 2, 'user_id' => Auth::id()))
            ->orderBy('creation_datetime', 'desc')->paginate($perPage); // Order by latest messages
        // Paginate the query with default or user-provided page size

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

    public function getPointsByService(): JsonResponse
    {
        $data = Wallet::query()
            ->where(array('status' => 'active', 'user_id' => Auth::id()))
            ->join('services', 'wallets.service_id', '=', 'services.id') // Join with Service model
            ->select('services.name as service_name', DB::raw('SUM(wallets.amount) as total_points'))
            ->groupBy('wallets.service_id', 'services.name')
            ->orderBy('total_points', 'desc') // Optional: Order by points
            ->get();

        // Format response
        $response = $data->map(function ($item) {
            return [
                'service' => $item->service_name,
                'points' => $item->total_points,
            ];
        });

        return $this->response(true, 'Points by service retrieved successfully', $response);
    }

    /**
     * Get WhatsApp analytics for a given workspace.
     *
     * @param Workspace|null $workspace
     * @return array
     */
    private function getWhatsappAnalytics(?Workspace $workspace): array
    {
        if (!$workspace) {
            // Default counts if workspace is null
            return [
                'whatsapp_messages_count' => 0,
                'whatsapp_sent_message_count' => 0,
                'whatsapp_not_sent_message_count' => 0,
                'whatsapp_active_channels_count' => 0,
                'whatsapp_not_active_channels_count' => 0
            ];
        }

        $whatsapp_messages_count = WhatsappMessage::whereHas('whatsappPhoneNumber.whatsappConfiguration.connector.channel', function ($query) use ($workspace) {
            $query->where('platform', Channel::WHATSAPP_PLATFORM)
                ->whereHas('workspaces', function ($workspaceQuery) use ($workspace) {
                    $workspaceQuery->where('workspaces.id', $workspace->id); // Explicitly qualify 'id' column
                });
        })->count();


        // Fetch sent messages count
        $whatsapp_sent_message_count = WhatsappMessage::whereHas('whatsappPhoneNumber.whatsappConfiguration.connector.channel', function ($query) use ($workspace) {
            $query->where('platform', Channel::WHATSAPP_PLATFORM)
                ->whereHas('workspaces', function ($workspaceQuery) use ($workspace) {
                    $workspaceQuery->where('workspaces.id', $workspace->id); // Explicitly qualify 'id' column
                });
        })->whereIn('status', [
            WhatsappMessage::MESSAGE_STATUS_SENT,
            WhatsappMessage::MESSAGE_STATUS_DELIVERED,
            WhatsappMessage::MESSAGE_STATUS_READ
        ])->count();

// Fetch not sent messages count
        $whatsapp_not_sent_message_count = WhatsappMessage::whereHas('whatsappPhoneNumber.whatsappConfiguration.connector.channel', function ($query) use ($workspace) {
            $query->where('platform', Channel::WHATSAPP_PLATFORM)
                ->whereHas('workspaces', function ($workspaceQuery) use ($workspace) {
                    $workspaceQuery->where('workspaces.id', $workspace->id); // Explicitly qualify 'id' column
                });
        })->whereNotIn('status', [
            WhatsappMessage::MESSAGE_STATUS_SENT,
            WhatsappMessage::MESSAGE_STATUS_DELIVERED,
            WhatsappMessage::MESSAGE_STATUS_READ
        ])->count();

        // Fetch active WhatsApp channels for the workspace
        $whatsapp_active_channels_count = $workspace->channels()->where('status', '=', 'active')
            ->where('platform', Channel::WHATSAPP_PLATFORM)
            ->count();

        $whatsapp_not_active_channels_count = $workspace->channels()->whereNot('status', '=', 'active')
            ->where('platform', Channel::WHATSAPP_PLATFORM)
            ->count();

        return [
            'whatsapp_messages_count' => $whatsapp_messages_count,
            'whatsapp_sent_message_count' => $whatsapp_sent_message_count,
            'whatsapp_not_sent_message_count' => $whatsapp_not_sent_message_count,
            'whatsapp_active_channels_count' => $whatsapp_active_channels_count,
            'whatsapp_not_active_channels_count' => $whatsapp_not_active_channels_count
        ];
    }

}
