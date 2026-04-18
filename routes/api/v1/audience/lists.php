<?php


use App\Http\Controllers\ListController;

Route::prefix('organizations/{organization}/lists')
    ->middleware(['api', 'auth:api'])
    ->controller(ListController::class)
    ->name('lists.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{list}', 'show')->name('show');
        Route::get('/{list}/without-relations', 'showWithoutRelations')->name('show-without-relations');
        Route::get('/{list}/contacts', 'viewContactsByListId')->name('contacts');
        Route::patch('/{list}', 'update')->name('update');
        Route::delete('/{list}', 'destroy')->name('destroy');
        Route::delete('/{list}/contacts/{contact}','detachContact');

    });

Route::middleware(['api', 'auth:api'])
    ->prefix('organizations/{organization}')
    ->controller(ListController::class)
    ->group(function () {
        Route::get('getAllListsWithFilteredRelations', 'getAllListsWithFilteredRelations')
            ->name('lists.filtered');
    });
