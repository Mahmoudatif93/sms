<?php

use App\Http\Controllers\OrganizationInboxAgentSettingController;
use App\Http\Controllers\OrganizationsController;

// Routes that don't require organization access
Route::middleware(['api', 'auth:api'])
    ->controller(OrganizationsController::class)
    ->prefix('organizations')
    ->group(function () {
        Route::post('/', 'store')->name('organizations.store');
    });

// Routes that require organization access
Route::middleware(['api', 'auth.access'])
    ->controller(OrganizationsController::class)
    ->prefix('organizations')
    ->group(function () {
        Route::get('/{organization}', 'show')->name('organizations.show');
        Route::post('/{organization}', 'update')->name('organizations.update');
        Route::post('/{organization}/upload-avatar', 'uploadAvatar')->name('organizations.upload.avatar');
        Route::get('/{organization}/members', 'getMembers')->name('organizations.get.members');
        Route::get('/{organization}/members/{memberId}', 'getMember')->name('organizations.get.member');
        Route::delete('/{organization}/members/{memberId}', 'deleteMember')->name('organizations.delete.member');
        Route::post('/{organization}/members/{memberId}/activate', 'activateMember')->name('organizations.activate.member');
        Route::patch('/{organization}/members/{memberId}', 'updateMember')->name('organizations.update.member');
    });



Route::prefix('organizations/{organization}/inbox-agent-settings')
    ->name('organization.inbox-agent-settings.')
    ->controller(OrganizationInboxAgentSettingController::class)
    ->group(function () {
        Route::get('/', 'show')->name('show');      // GET -> fetch settings
        Route::patch('/', 'update')->name('update'); // PATCH -> update settings
    });
