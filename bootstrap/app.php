<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AllowIframe;
use App\Http\Middleware\AuthenticateUserOrAccessKey;
use App\Http\Middleware\CheckActiveOrganizationMembershipPlan;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureInboxAgentIsPaidOrOwner;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: ''
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'active' => EnsureUserIsActive::class,
            'lang' => SetLocale::class,
            'check.admin' => AdminMiddleware::class,
            'admin.active' => EnsureAdminIsActive::class,
            'auth.access' => AuthenticateUserOrAccessKey::class,
            'check.active.membership' => CheckActiveOrganizationMembershipPlan::class,
            'allowiframe' => AllowIframe::class,
            'access.conversations' => EnsureInboxAgentIsPaidOrOwner::class
        ]);
    })
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })->create();
