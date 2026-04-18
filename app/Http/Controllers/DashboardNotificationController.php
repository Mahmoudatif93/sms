<?php

namespace App\Http\Controllers;

use App\Events\DashboardNotificationsRefreshRequest;
use App\Http\Responses\DashboardNotificationResponse;
use App\Models\DashboardNotification;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Http\Request;

class DashboardNotificationController extends BaseApiController
{
    public function index(Request $request, Organization $organization, Workspace $workspace)
    {
        // 1. Pagination parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        // 2. Build a query with filters
        $query = DashboardNotification::with(['workspace', 'notifiable'])
            ->where('organization_id', $organization->id)
            ->where('workspace_id', $workspace->id);

        if ($type = $request->query('type')) {
            $query->where('notifiable_type', $type);
        }

        if ($id = $request->query('id')) {
            $query->where('notifiable_id', $id);
        }

        // 3. Filter by read status (optional)
        if ($request->query('read') === 'true') {
            $query->whereNotNull('read_at');
        } elseif ($request->query('read') === 'false') {
            $query->whereNull('read_at');
        }

        // 4. Get unread count separately
        $unreadCount = DashboardNotification::where('organization_id', $organization->id)
            ->where('workspace_id', $workspace->id)
            ->whereNull('read_at')
            ->count();


        // 3. Paginate
        $notifications = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        // 4. Transform collection
        $transformed = $notifications->getCollection()->map(function ($notification) {
            return new DashboardNotificationResponse($notification);
        });

        $notifications->setCollection($transformed);

        // 5. Return formatted paginated response
        return $this->paginateResponse(true, 'Required Actions retrieved successfully.', $notifications ,
        additional:  ['unread_count' => $unreadCount]);
    }

    public function update(Request $request, Organization $organization, Workspace $workspace, DashboardNotification $notification)
    {
        $status = $request->input('read', true); // true = mark as read, false = unread

        if ($notification->organization_id !== $organization->id || $notification->workspace_id !== $workspace->id) {
            return $this->response(success: false,message: 'Unauthorized access to notification.', statusCode: 403);
        }

        $notification->update([
            'read_at' => $status ? now() : null,
        ]);

        if (!$status) {
             event(new DashboardNotificationsRefreshRequest(
                $organization->id,
                $workspace->id
            ));
        }

        return $this->response(
            true,
            $status ? 'Notification marked as read.' : 'Notification marked as unread.',
            new DashboardNotificationResponse($notification)
        );
    }
}
