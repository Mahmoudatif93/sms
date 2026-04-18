<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\User;
use App\Models\WorkspaceUser;
use App\Traits\UserTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Traits\WalletManager;
use App\Models\Service as MService;
use App\Enums\Service as EnumService;

class Me extends DataInterface
{
    use UserTrait, WalletManager;

    public array $user;
    public Collection $wallets;
    public ?Collection $ownedOrganizations;
    public ?Collection $organizations;
    public ?Collection $workspaces;
    public ?Collection $channels;
    public Collection $whatsapp;
    public JsonResponse $permissions;
    public string $organization_status;
    public bool $is_inbox_agent;

    public function __construct(User $user, Request $request)
    {
        $this->user = $this->format($user);
        // Fetch owned organizations
        $this->ownedOrganizations = $user->ownedOrganizations ? $user->ownedOrganizations->map(function ($organization) {
            return new Organization($organization);
        }) : collect();

        // Fetch organizations from memberships
        $ownedOrgs = $user->ownedOrganizations;
        $memberOrgs = $user->organizationMemberships;
        $currentOrganization = null;
        $currentWorkspace = null;
        $currentOrganizationId = null;
        $this->organization_status = $this->checkOrganizationStatus($user);
        if ($user->workspace_id) {
            $currentOrganizationId = \App\Models\Workspace::where('id', $user->workspace_id)->first()->organization_id;
        }

        $this->organizations = collect([])
            ->merge($ownedOrgs->map(function ($org) use ($currentOrganizationId, &$currentOrganization) {
                if ($org->id == $currentOrganizationId) {
                    $currentOrganization = $org;
                }
                return (new Organization($org))->setIsOwner(true)->setIsCurrent($org->id === $currentOrganizationId);
            }))
            ->merge($memberOrgs->map(function ($org) use ($currentOrganizationId) {
                return (new Organization($org))->setIsOwner(false)->setIsCurrent($org->id === $currentOrganizationId);
            }));

        // Fetch workspaces for owned organizations
        $ownedWorkspaces = $user->ownedOrganizations()
            ->with('workspaces') // Eager load workspaces and channels
            ->get()
            ->flatMap(function ($organization) use ($currentOrganizationId) {
                return $organization->id === $currentOrganizationId
                    ? $organization->workspaces
                    : collect();// Consolidate all workspaces
            });

        // Fetch workspaces for membership organizations
        $membershipWorkspaces = $user->organizationMemberships()
            ->with([
                'workspaces' => function ($query) use ($user) {
                    $query->whereHas('users', function ($q) use ($user) {
                        $q->where('user.id', $user->id)
                            ->where('workspace_users.status', WorkspaceUser::STATUS_ACTIVE);
                    });
                }
            ])
            ->get()
            ->flatMap(function ($organization) use ($currentOrganizationId) {
                return $organization->id === $currentOrganizationId
                    ? $organization->workspaces
                    : collect();
            });

        // Merge and map workspaces
        $allWorkspaces = $ownedWorkspaces->merge($membershipWorkspaces);
        $this->workspaces = $allWorkspaces->map(function ($workspace) use ($user, &$currentWorkspace) {
            $isCurrent = isset($user->workspace_id) && $workspace->id === $user->workspace_id;
            if ($isCurrent) {
                $currentWorkspace = $workspace;
            }
            return new Workspace($workspace, $isCurrent); // Map to the response class
        });

        if ($currentOrganization) {
            $mainWallet = $this->getObjectWallet($currentOrganization, MService::where('name', EnumService::OTHER)->value('id'));
            $smsWallet = $this->getObjectWallet($currentOrganization, MService::where('name', EnumService::SMS)->value('id'));
            $wallets = collect([$mainWallet, $smsWallet])->filter();
            $this->wallets =$wallets->map(function ($wallet)  {
                return new Wallet($wallet);
            });
        } elseif ($currentWorkspace) {
            $mainWallet = $this->getObjectWallet($currentWorkspace, MService::where('name', EnumService::OTHER)->value('id'), $user->id);
            $smsWallet = $this->getObjectWallet($currentWorkspace, MService::where('name', EnumService::SMS)->value('id'));
            $wallets = collect([$mainWallet, $smsWallet])->filter();
            $this->wallets =$wallets->map(function ($wallet)  {
                return new Wallet($wallet);
            });

        } else {
            $this->wallets = collect([]);
        }

        $this->is_inbox_agent = (bool) $user->isInboxAgent();

    }
}
