<?php

use App\Http\Controllers\Admin\AdminMessageController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ChannelController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\plansController;
use App\Http\Controllers\Admin\RevisionMessageController;
use App\Http\Controllers\Admin\SupervisorController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\DefaultDreamsWhatsappRateController;
use App\Http\Controllers\IAMPolicyController;
use App\Http\Controllers\IAMPolicyDefinitionController;
use App\Http\Controllers\IAMRoleController;
use App\Http\Controllers\MetaWebhookLogsController;
use App\Http\Controllers\OrganizationMembershipPlanController;
use App\Http\Controllers\OrganizationsController;
use App\Http\Controllers\OrganizationWhatsappExtraController;
use App\Http\Controllers\OrganizationWhatsappRateController;
use App\Http\Controllers\OrganizationWhatsappRateLineController;
use App\Http\Controllers\OrganizationWhatsappSettingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ResourceGroupsController;
use App\Http\Controllers\ResourcesController;
use App\Http\Controllers\UserMembershipPlanController;
use App\Http\Controllers\WhatsappMessagesController;
use App\Http\Controllers\WhatsappRateController;
use App\Http\Controllers\WhatsappRateLinesController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;


Route::middleware(['lang'])
    ->controller(AuthController::class)
    ->prefix('admin/auth')
    ->group(function () {
        Route::post('login', 'login');
        Route::get('logout', 'logout');
        Route::post('verifyotpcode', 'verifyotpcode');
        Route::post('ResendOtp', 'ResendOtp');
        Route::get('me', 'me')->middleware('admin.active');
    });

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin')
    ->group(function () {
        Route::apiResource('supervisors', SupervisorController::class);
    });

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin')
    ->group(function () {
        Route::apiResource('users', UserController::class);
        Route::get('/user_sender/{id}', [UserController::class, 'showUserSenders'])->name('/user_sender');
        Route::get('/balance_logs/user/{id}', [UserController::class, 'viewbalancelogs'])->name('/balance_logs/user');
        Route::get('/user/{id}/token', [UserController::class, 'getUserWithToken']);
        Route::get('/UnSuspendUser/user/{id}', [UserController::class, 'UnSuspendUser'])->name('/UnSuspendUser/user');
        // IAM Roles
        Route::post('/users/{user}/attach-role', [UserController::class, 'attachRole'])->name('users.attachRole');
        Route::post('/users/{user}/detach-role', [UserController::class, 'detachRole'])->name('users.detachRole');
        Route::apiResource('user-membership-plans', UserMembershipPlanController::class);
        Route::get('/user-membership-plans/user/{id}', [UserMembershipPlanController::class, 'getUserMembershipPlans'])
            ->name('user-membership-plans.user'); // Fetch membership plans for a specific user

        Route::get('/user/subAccounts/{id}', [UserController::class, 'subaccounts'])->name('user.subAccounts');

    });

//Resources
Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/resources')
    ->controller(ResourcesController::class)
    ->group(function () {
        Route::get('/', 'index'); // View all resources
        Route::get('refresh', 'refresh'); // Refresh resources
        Route::get('{resource}', 'show'); // ✅ Get a single resource by ID
        Route::patch('{resource}/toggle', 'toggle'); // Toggle active status
    });

// Resource Groups
Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/resource-groups')
    ->controller(ResourceGroupsController::class)
    ->group(function () {
        Route::get('/', 'index')->name('resource-groups.index'); // Get all resource groups
        Route::get('{resourceGroup}', 'show')->name('resource-groups.show'); // Get details of a specific resource group
        Route::post('/', 'store')->name('resource-groups.store'); // Create a resource group
        Route::post('{resourceGroup}/resources', 'attachResource')->name('resource-groups.add-resource'); // Add a resource
        Route::delete('{resourceGroup}/resources/{resource}', 'detachResource')->name('resource-groups.remove-resource'); // Remove a resource
        Route::delete('{resourceGroup}', 'destroy')->name('resource-groups.destroy'); // Delete a resource group
    });

// IAMPolicyDefinitions
Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/iam-policy-definitions')
    ->controller(IAMPolicyDefinitionController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.iam-policy-definitions.index'); // List all definitions
        Route::post('/', 'store')->name('admin.iam-policy-definitions.store'); // Create a new definition
        Route::get('/{definition}', 'show')->name('admin.iam-policy-definitions.show'); // Get a specific definition
        Route::patch('/{definition}', 'update')->name('admin.iam-policy-definitions.update'); // Update a definition
        Route::delete('/{definition}', 'destroy')->name('admin.iam-policy-definitions.destroy'); // Delete a definition
    });

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/iam-policies')
    ->controller(IAMPolicyController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.iam-policies.index'); // Get all policies with definitions
        Route::get('/{policy}', 'show')->name('admin.iam-policies.show'); // Get a specific policy with its definitions
        Route::post('/', 'store')->name('admin.iam-policies.store'); // Create a new IAM Polic
        Route::post('/{policy}/definitions', 'attachDefinitions')->name('admin.iam-policies.attach-definition'); // Attach a definition to a policy
        Route::delete('/{policy}/definitions', 'detachDefinitions')->name('admin.iam-policies.detach-definition'); // Detach a definition from a policy
        Route::delete('/{policy}', 'destroy')->name('admin.iam-policies.destroy'); // Delete an IAM Policy
    });

Route::middleware(['auth:admin', 'check.admin', 'lang'])
    ->prefix('admin/iam-roles')
    ->controller(IAMRoleController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.iam-roles.index'); // List all roles
        Route::get('/{role}', 'show')->name('admin.iam-roles.show'); // Get a specific role
        Route::post('/', 'store')->name('admin.iam-roles.store'); // Create a new role
        Route::patch('/{role}', 'update')->name('admin.iam-roles.update'); // Update a role
        Route::delete('/{role}', 'destroy')->name('admin.iam-roles.destroy'); // Delete a role

        // Attach/Detach policies
        Route::post('/{role}/attach-policy', 'attachPolicy')->name('admin.iam-roles.attach-policy');
        Route::post('/{role}/detach-policy', 'detachPolicy')->name('admin.iam-roles.detach-policy');
    });

// Organizations

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/organizations')
    ->controller(OrganizationsController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.organizations.index'); // Get all organizations
        Route::get('/{organization}', 'show')->name('admin.organizations.show'); // Get organization details
        Route::get('/{organization}/members', 'getMembers')->name('admin.organizations.get-members'); // List members of an organization
        Route::delete('/{organization}/members/{memberId}', 'deleteMember')->name('admin.organizations.delete-member'); // Remove a member
        //Route::post('/{organization}/members', 'addMember')->name('admin.organizations.add-member'); // Add a new member
        Route::post('{organization}/charge', 'charge')->name('admin.organizations.charge');
    });

// Workspaces

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/organizations/{organization}/workspaces')
    ->controller(WorkspaceController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.workspaces.index'); // Get all workspaces
        Route::get('/{workspace}', 'show')->name('admin.workspaces.show'); // Get workspace details
    });

// Channels
Route::middleware(['lang'])
    ->prefix('admin')
    ->controller(ChannelController::class)
    ->group(function () {
        Route::post('channels/{channel}/update', 'update');
        Route::resource('channels', ChannelController::class);
        Route::get('channels/{channel}/approve', 'approve');
        Route::get('channels/{channel}/reject', 'reject');
        Route::get('channels/{channel}/mark-as-waiting-payment', 'markAsWaitingPayment');
        Route::get('gateways', 'getGatewayNames');

    });

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/organizations/{organization}/workspaces')
    ->controller(ChannelController::class)
    ->group(function () {
        Route::get('/{workspace}/channels', 'getChannels')->name('admin.workspaces.get-channels');
    });

// WhatsappMessages

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/whatsapp-messages')
    ->controller(WhatsappMessagesController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.whatsapp-messages.index'); // Get all WhatsApp messages
        Route::get('/{whatsappMessage}', 'show')->name('admin.whatsapp-messages.show'); // Get details of a specific message
    });

// Settings

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin')
    ->group(function () {
        Route::apiResource('/settings', AdminSettingsController::class);
    });

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/whatsapp-rates')
    ->controller(WhatsappRateController::class)
    ->group(function () {
        Route::get('/', 'index'); // View all WhatsApp rates
        Route::get('{rate}', 'show'); // View a specific WhatsApp rate
        Route::post('/', 'store'); // Create a new WhatsApp rate
        Route::patch('{rate}', 'update'); // Update an existing WhatsApp rate
        Route::delete('{rate}', 'destroy'); // Delete a WhatsApp rate
        Route::post('/upload', 'upload');

        Route::get('/country/{country_id}', 'getByCountry'); // Get WhatsApp rates by country_id
    });

//////////plans
Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin')
    ->group(function () {
        Route::apiResource('plans', plansController::class);
        Route::get('/plan/export', [plansController::class, 'exportPlan'])->name('plans.export');

    });

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/{organization}/whatsapp-rates')
    ->controller(OrganizationWhatsappRateController::class)
    ->group(function () {
        Route::get('/', 'index'); // Get all rates
        Route::get('/{id}', 'show'); // Get a specific rate
        Route::post('/', 'store'); // Create a new rate
        Route::patch('/{id}', 'update'); // Update an existing rate
        Route::delete('/{id}', 'destroy'); // Delete a rate
    });

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/{organization}/membership-plans')
    ->controller(OrganizationMembershipPlanController::class)
    ->group(function () {
        Route::get('/', 'index'); // Get all membership plans
        Route::get('/{id}', 'show'); // Get a specific membership plan
        Route::post('/', 'store'); // Create a new membership plan
        Route::patch('/{id}', 'update'); // Update an existing membership plan
        Route::delete('/{id}', 'destroy'); // Delete a membership plan
    });

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/dreams-whatsapp-rates')
    ->controller(DefaultDreamsWhatsappRateController::class)
    ->group(function () {
        Route::get('/', 'index'); // Get all default WhatsApp rates
        Route::get('/{id}', 'show'); // Get a specific default rate
        Route::post('/', 'store'); // Create a new default rate
        Route::patch('/{id}', 'update'); // Update an existing default rate
        Route::delete('/{id}', 'destroy'); // Delete a default rate
    });

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/organizations/{organization}/whatsapp-extras')
    ->controller(OrganizationWhatsappExtraController::class)
    ->group(function () {
        Route::get('/', 'index'); // Get all extras for a specific organization
        Route::get('/{id}', 'show'); // Get a specific extra for the organization
        Route::post('/', 'store'); // Create a new extra for the organization
        Route::patch('/{id}', 'update'); // Update an existing extra for the organization
        Route::delete('/{id}', 'destroy'); // Delete an extra for the organization
    });


//Payments
Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin/payments')
    ->controller(PaymentController::class)
    ->group(function () {
        Route::get('', 'index')->name('admin.payments.index');
        Route::post('{payment}/upload-invoice-file', 'uploadInvoiceFile')->name('admin.payments.uploadInvoiceFile');
        Route::post('{payment}/process-status', 'processPaymentStatus')->name('admin.payments.processStatus');
        Route::post('organizations/{organization}/charge', 'organizationCharge')->name('admin.payments.organizationCharge');
    });


///Users Messages
Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->controller(MessageController::class)
    ->prefix('admin')
    ->group(function () {
        Route::resource('sms/messages', MessageController::class);
        Route::delete('/sms/message/bulk-delete', [MessageController::class, 'deleteSelected'])->name('sms.messages.bulkDelete');
        Route::get('/sms/message/export/{messageId?}', [MessageController::class, 'exportMessages'])->name('admin.sms.messages.export');


    });

///Admin Messages
Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->controller(AdminMessageController::class)
    ->prefix('admin')
    ->group(function () {
        Route::resource('sms/AdminMessages', AdminMessageController::class);
        Route::delete('sms/AdminMessage/bulk-delete', [AdminMessageController::class, 'deleteSelected'])->name('sms.AdminMessages.bulkDelete');
        Route::get('/sms/AdminMessage/export/{messageId?}', [AdminMessageController::class, 'exportMessages'])->name('sms.AdminMessages.export');


    });

// Tags
Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->controller(TagController::class)
    ->prefix('admin')
    ->group(function () {
        Route::get('tags/parent', [TagController::class, 'getParentTagsWithChildren']);
        Route::get('tags/{tag}/children', [TagController::class, 'getChildren']);
        Route::get('tags/{tag}/organizations', [TagController::class, 'getOrganizations']);
        Route::post('tags/{tag}/organizations/{organization}', [TagController::class, 'attachOrganization']);
        Route::delete('tags/{tag}/organizations/{organization}', [TagController::class, 'detachOrganization']);
        Route::resource('tags', TagController::class);
    });

Route::middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->prefix('admin')
    ->as('admin.sms.revision')
    ->group(function () {
        Route::resource('sms/revision/messages', RevisionMessageController::class);
        Route::get('sms/revision/message/accept/{id}', [RevisionMessageController::class, 'accept'])->name('sms.revision.message.accept');
        Route::get('sms/revision/message/reject/{id}', [RevisionMessageController::class, 'reject'])->name('sms.revision.message.reject');
        Route::get('/sms/revision/message/export/{messageId?}', [RevisionMessageController::class, 'exportMessages'])->name('sms.revision.message.export');


    });

Route::get('/clear-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    return response()->json(['message' => 'Cache cleared successfully']);
});

Route::prefix('admin/whatsapp-rate-lines')
//    ->middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->name('admin.whatsapp-rate-lines.')
    ->controller(WhatsappRateLinesController::class)
    ->group(function () {
        // List all WhatsApp rate lines with filtering and pagination
        Route::get('/', 'index')->name('index');

        // Show details for a single WhatsApp rate line
        Route::get('{whatsappRateLine}', 'show')->name('show');

        // Create a new WhatsApp rate line
        Route::post('/', 'store')->name('store');

        // Update an existing WhatsApp rate line
        Route::patch('{whatsappRateLine}', 'update')->name('update');

        // Delete a WhatsApp rate line
        Route::delete('{whatsappRateLine}', 'destroy')->name('destroy');
    });

Route::prefix('admin/meta-webhook-logs')
//     ->middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->name('admin.meta-webhook-logs.')
    ->controller(MetaWebhookLogsController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{metaWebhookLog}', 'show')->name('show');
        Route::patch('{metaWebhookLog}', 'update')->name('update');
    });

Route::prefix('admin/organizations/{organization}/whatsapp-rate-lines')
//    ->middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->name('admin.organization-whatsapp-rate-lines.')
    ->controller(OrganizationWhatsappRateLineController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');                    // List for organization
        Route::post('/', 'store')->name('store');                   // Create for organization
        Route::patch('{organizationWhatsappRateLine}', 'update')->name('update'); // Update
        Route::delete('{organizationWhatsappRateLine}', 'destroy')->name('destroy'); // Delete
    });


Route::prefix('admin/organizations/{organization}/whatsapp-settings')
//    ->middleware(['auth:admin', 'check.admin', 'lang', 'admin.active'])
    ->name('admin.organization.whatsapp-settings.')
    ->controller(OrganizationWhatsappSettingController::class)
    ->group(function () {
        Route::get('/', 'show')->name('show');     // Fetch current settings
        Route::patch('/', 'update')->name('update'); // Update
    });


