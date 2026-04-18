<?php

//Resources

use App\Http\Controllers\ResourcesController;

Route::middleware(['api', 'auth:api'])->group(function () {
    Route::get('/resources', [ResourcesController::class, 'index'])->name('resources.index');
    Route::get('{resource}',[ResourcesController::class, 'index']); // ✅ Get a single resource by ID
});
