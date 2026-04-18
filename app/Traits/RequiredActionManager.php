<?php

namespace App\Traits;

use App\Models\RequiredAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

trait RequiredActionManager
{
    /**
     * Add a new required action to this model.
     */
    public function addRequiredAction(string $type, array $metadata = [], ?Carbon $dueAt = null,  ?string $workspaceId = null): RequiredAction
    {
        $workspace = null;
        $organization = null;

        // Try to resolve via workspace_id if passed
        if ($workspaceId && method_exists($this, 'workspaces')) {
            $workspace = $this->workspaces()->where('id', $workspaceId)->first();
            $organization = $workspace?->organization;
        }

        // If the model itself has an organization_id directly
        if (!$organization && property_exists($this, 'organization_id')) {
            $organization = $this->organization ?? null;
        }

        return $this->requiredActions()->create([
            'action_type' => $type,
            'metadata' => $metadata,
            'due_at' => $dueAt,
            'organization_id' => $organization?->id,
            'workspace_id' => $workspace?->id,
        ]);
    }

    /**
     * Get all required actions for this model.
     */
    public function requiredActions(): MorphMany
    {
        return $this->morphMany(RequiredAction::class, 'actionable');
    }

    /**
     * Mark a specific required action as completed.
     */
    public function completeRequiredAction(string $type): void
    {
        $this->requiredActions()
            ->where('action_type', $type)
            ->whereNull('completed_at')
            ->whereNull('dismissed_at')
            ->update(['completed_at' => now()]);
    }

    /**
     * Dismiss a required action.
     */
    public function dismissRequiredAction(string $type): void
    {
        $this->requiredActions()
            ->where('action_type', $type)
            ->whereNull('completed_at')
            ->whereNull('dismissed_at')
            ->update(['dismissed_at' => now()]);
    }

    /**
     * Get only pending required actions.
     */
    public function pendingRequiredActions(): Collection
    {
        return $this->requiredActions()
            ->whereNull('completed_at')
            ->whereNull('dismissed_at')
            ->get();
    }
}
