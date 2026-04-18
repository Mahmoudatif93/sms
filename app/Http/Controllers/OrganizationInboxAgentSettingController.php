<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationInboxAgentSettingController extends BaseApiController
{
    public function show(Organization $organization)
    {
        $settings = $organization->getOrCreateInboxAgentSettings();
        return $this->response(
            data: $settings
        );
    }

    public function update(Request $request, Organization $organization)
    {
        $settings = $organization->getOrCreateInboxAgentSettings();

        $validated = $request->validate([
            'automation_technique' => 'sometimes|string|in:load_balancer,round_robin',
            'wait_time_idle' => 'sometimes|integer|min:60',
            'max_conversations_per_agent' => 'sometimes|integer|min:1',
            'available_to_away_time' => 'sometimes|integer|min:60',
            'away_to_office_time' => 'sometimes|integer|min:60',
            'default_availability' => 'sometimes|in:available,away,out_of_office',
            'enable_auto_assign' => 'sometimes|boolean',
            'auto_archive_delay' => 'sometimes|integer|min:0',
            'reassign_unresponsive_agents_after' => 'nullable|integer|min:60',
        ]);

        $settings->update($validated);

        return $this->response(

            message : 'Inbox agent settings updated successfully.',
            data: $settings,
        );
    }
}
