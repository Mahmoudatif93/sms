<?php

use Illuminate\Support\Facades\Route;

// v1.0
Route::prefix('api/v1.0')->group(function () {
    foreach (glob(__DIR__ . '/api/v1/admin.php') as $routeFile) {
        require $routeFile;
    }
});
