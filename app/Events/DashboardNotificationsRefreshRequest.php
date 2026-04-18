<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardNotificationsRefreshRequest implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $organizationId;
    public string $workspaceId;


    /**
     * Create a new event instance.
     */
    public function __construct(string $organizationId, string $workspaceId)
    {

        $this->organizationId = $organizationId;
        $this->workspaceId = $workspaceId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organization.{$this->organizationId}.workspace.{$this->workspaceId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.notifications.refresh';
    }

    public function broadcastWith(): array
    {
        return ['action' => 'refresh'];
    }
}
