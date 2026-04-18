<?php

namespace App\Traits;

use App\Models\User as UserModel;

trait UserTrait
{
    /**
     * Format the user data to include all necessary fields.
     *
     * @param UserModel $user
     * @return array
     */
    private function format(UserModel $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'number' => $user->number,
            'country_id' => $user->country_id,
            'phone' => $user->phone,
            'address' => $user->address,
            'blocked' => $user->blocked,
            'active' => $user->active
        ];
    }

    
    private function checkOrganizationStatus(UserModel $user): string
    {
        // Check if user owns any organizations
        if ($user->ownedOrganizations()->exists()) {
            return 'owner';
        }

        // Check if user is a member of any organizations
        if (
            $user->organizationMemberships()
                ->wherePivot('status', 'active')
                ->exists()
        ) {
            return 'member';
        }

        // User has no organization association
        return 'needs_organization';
    }
}
