<?php

use App\Http\Controllers\Whatsapp\InteractiveMessageDraftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Interactive Message Drafts Routes
|--------------------------------------------------------------------------
|
| Routes for managing interactive message drafts (button/list).
| These drafts can be reused for sending interactive messages directly
| or as part of workflow actions.
|
*/

Route::prefix('workspaces/{workspace}/interactive-message-drafts')
    ->middleware(['api', 'auth:api'])
    ->controller(InteractiveMessageDraftController::class)
    ->name('interactive-message-drafts.')
    ->group(function () {
        // CRUD operations
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{draft}', 'show')->name('show');
        Route::put('/{draft}', 'update')->name('update');
        Route::delete('/{draft}', 'destroy')->name('destroy');

        // Send interactive message using draft
        Route::post('/{draft}/send', 'send')->name('send');
    });

