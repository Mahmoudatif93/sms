<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletAssignment;
use App\Models\OrganizationUser;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class WalletAssignmentService
{


    /**
     * Valid assignable types mapping
     */
    private const VALID_ASSIGNABLE_TYPES = [
        'organization' => Organization::class,
        'organization_user' => OrganizationUser::class,
        'workspace' => Workspace::class,
    ];

    /**
     * Assign a wallet to an entity with validation
     *
     * @param Wallet $wallet
     * @param string $assignableType
     * @param string|int $assignableId
     * @return WalletAssignment
     * @throws InvalidArgumentException
     */
    public function assignWallet(
        Wallet $wallet,
        string $assignableType,
        string|int $assignableId
    ): WalletAssignment {
        // Validate inputs
        $this->validateAssignment($wallet, $assignableType, $assignableId);

        try {
            return DB::transaction(function () use ($wallet, $assignableType, $assignableId) {

                // Get the organization ID based on assignable type
                $organizationId = $this->getOrganizationId($assignableType, $assignableId);

                // Deactivate previous assignments for this assignable in the same organization
                $this->deactivatePreviousAssignments($wallet->service_id, $assignableType, $assignableId);
                // Check for existing active assignments of the same type
                $existingAssignment = WalletAssignment::where([
                    'wallet_id' => $wallet->id,
                    'assignable_type' => self::VALID_ASSIGNABLE_TYPES[$assignableType],
                    'assignable_id' => $assignableId,
                    'is_active' => true
                ])->first();

                if ($existingAssignment) {
                    throw new InvalidArgumentException('An active assignment already exists for this combination.');
                }

                // Create new assignment
                return WalletAssignment::create([
                    'wallet_id' => $wallet->id,
                    'assignable_type' => self::VALID_ASSIGNABLE_TYPES[$assignableType],
                    'assignable_id' => $assignableId,
                    'is_active' => true
                ]);
            });
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Failed to create wallet assignment: ' . $e->getMessage());
        }
    }

    /**
     * Validate the assignment parameters
     *
     * @param Wallet $wallet
     * @param string $assignmentType
     * @param string $assignableType
     * @param string|int $assignableId
     * @throws InvalidArgumentException
     */
    private function validateAssignment(
        Wallet $wallet,
        string $assignableType,
        string|int $assignableId
    ): void {

        // Validate assignable type
        if (!array_key_exists($assignableType, self::VALID_ASSIGNABLE_TYPES)) {
            throw new InvalidArgumentException('Invalid assignable type.');
        }

        // Validate assignable exists
        $assignableModel = self::VALID_ASSIGNABLE_TYPES[$assignableType];
        $assignable = $assignableModel::find($assignableId);

        if (!$assignable) {
            throw new InvalidArgumentException("$assignableType with ID $assignableId not found.");
        }

        // Additional validation for OrganizationUser
        if ($assignableType === 'organization_user') {
            if (!$assignable->has_special_wallet && false) {
                throw new InvalidArgumentException('This organization user is not allowed to have a special wallet.');
            }
        }
        // Validate wallet status
        if (strtolower($wallet->status) !== 'active') {
            throw new InvalidArgumentException('Only active wallets can be assigned.');
        }
    }

    /**
     * Deactivate a wallet assignment
     *
     * @param WalletAssignment $assignment
     * @return bool
     */
    public function deactivateAssignment(WalletAssignment $assignment): bool
    {
        return $assignment->update(['is_active' => false]);
    }


    public function removeAssignmentV2(WalletAssignment $assignment): bool
    {
        $assignmentId = $assignment->id;
    
        try {
            return DB::transaction(function () use ($assignment, $assignmentId) {
                $deleted = $assignment->delete();
                
                return $deleted && !WalletAssignment::where('id', $assignmentId)->exists();
            });
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all active assignments for a wallet
     *
     * @param Wallet $wallet
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveAssignments(Wallet $wallet)
    {
        return $wallet->assignments()
            ->where('is_active', true)
            ->with('assignable')
            ->get();
    }

    /**
     * Get all workspaces and organization users with their wallet assignment status
     *
     * @param Wallet $wallet
     * @param string $organizationId
     * @return array
     */
    public function getAssignments(Wallet $wallet, string $organizationId): array
    {
        // Get all workspaces for the organization
        $workspaces = Workspace::where('organization_id', $organizationId)
        ->where('status', 'active')  // Only get active workspaces
            ->with([
                'walletAssignments' => function ($query) use ($wallet) {
                    $query->where('wallet_id', $wallet->id)
                    ->where('is_active', true);  // Only get active assignments;
                }
            ])
            ->get()
            ->map(function ($workspace) {
                return [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'type' => 'workspace',
                    'is_assigned' => $workspace->walletAssignments->isNotEmpty(),
                    'assignment' => $workspace->walletAssignments->first()
                ];
            });

        // Get all organization users
        $organizationUsers = OrganizationUser::where('organization_id', $organizationId)
        ->where('status', 'active')  // Only get active users
            ->with([
                'user',
                'walletAssignments' => function ($query) use ($wallet) {
                    $query->where('wallet_id', $wallet->id)
                    ->where('is_active', true);  // Only get active assignments
                }
            ])
            ->get()
            ->map(function ($orgUser) {
                return [
                    'id' => $orgUser->id,
                    'name' => $orgUser->user->name ?? 'N/A',
                    'email' => $orgUser->user->email ?? 'N/A',
                    'type' => 'organization_user',
                    'is_assigned' => $orgUser->walletAssignments->isNotEmpty(),
                    'assignment' => $orgUser->walletAssignments->first()
                ];
            });

        return [
            'workspaces' => $workspaces,
            'organization_users' => $organizationUsers
        ];
    }

    /**
     * Get the fully qualified class name for the assignable type
     *
     * @param string $type
     * @return string
     */
    private function getAssignableClass(string $type): string
    {
        return match ($type) {
            'organization_user' => OrganizationUser::class,
            'workspace' => Workspace::class,
            default => throw new InvalidArgumentException('Invalid assignable type')
        };
    }

    public function removeAssignment(Wallet $wallet, string $assignableType, string $assignableId): bool
    {
        // Validate assignable type
        if (!in_array($assignableType, ['organization_user', 'workspace'])) {
            throw new InvalidArgumentException('Invalid assignable type');
        }

        // Find and delete the assignment
        return WalletAssignment::where([
            'wallet_id' => $wallet->id,
            'assignable_type' => $this->getAssignableClass($assignableType),
            'assignable_id' => $assignableId
        ])->delete() > 0;
    }

    /**
     * Get the organization ID based on assignable type and ID
     */
    private function getOrganizationId(string $assignableType, string|int $assignableId): string
    {
        $model = self::VALID_ASSIGNABLE_TYPES[$assignableType]::findOrFail($assignableId);

        if ($model instanceof Organization) {
            return $model->id;
        } elseif ($model instanceof Workspace) {
            return $model->organization_id;
        } elseif ($model instanceof OrganizationUser) {
            return $model->organization_id;
        }

        throw new InvalidArgumentException('Unable to determine organization ID for the assignable.');
    }

    
    /**
     * Deactivate previous assignments for the assignable within the same organization
     */
    private function deactivatePreviousAssignments(int $serviceId, string $assignableType, string|int $assignableId): void
    {
        // Find all active assignments for this assignable with wallets of the same service
        $activeAssignments = WalletAssignment::where([
            'assignable_type' => self::VALID_ASSIGNABLE_TYPES[$assignableType],
            'assignable_id' => $assignableId,
            'is_active' => true
        ])
        ->whereHas('wallet', function ($query) use ($serviceId) {
            $query->where('service_id', $serviceId);
        })
        ->get();

        foreach ($activeAssignments as $assignment) {
            $this->removeAssignmentV2($assignment);
        }
    }
}