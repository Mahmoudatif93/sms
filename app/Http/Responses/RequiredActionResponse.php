<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\RequiredAction;

class RequiredActionResponse extends DataInterface
{
    public int $id;
    public string $action_type;
    public string $status;
    public ?string $title;
    public ?string $message;
    public ?string $workspace_id;
    public ?string $organization_id;
    public ?string $actionable_type;
    public ?string $actionable_id;
    public ?int $due_at;
    public ?int $completed_at;
    public ?int $dismissed_at;

    public function __construct(RequiredAction $action)
    {
        $this->id = $action->id;
        $this->action_type = $action->action_type;
        $this->status = $action->status;


        $this->title = $action->metadata['title'] ?? null;
        $this->message = $action->metadata['message'] ?? null;

        $this->workspace_id = $action->workspace_id;
        $this->organization_id = $action->organization_id;
        $this->actionable_type = $action->actionable_type;
        $this->actionable_id = $action->actionable_id;

        $this->due_at = optional($action->due_at)?->toIso8601String();
        $this->completed_at = optional($action->completed_at)?->toIso8601String();
        $this->dismissed_at = optional($action->dismissed_at)?->toIso8601String();
    }
}
