<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__ . '/../routes/console.php',
        using: function () {
            Route::middleware('web')
                ->namespace('App\Http\Controllers')
                ->group(base_path('routes/web.php'));

            Route::group([
                'prefix' => '/api/v1',
                'middleware' => 'api',
                'namespace' => 'App\Http\Controllers',
            ], function ($router) {
                foreach (glob(app_path('Http/Routes/V1') . '/*.php') as $file) {
                    app()->make('App\\Http\\Routes\\V1\\' . basename($file, '.php'))->map($router);
                }
            });

            Route::group([
                'prefix' => '/api/v2',
                'middleware' => 'api',
                'namespace' => 'App\Http\Controllers',
            ], function ($router) {
                foreach (glob(app_path('Http/Routes/V2') . '/*.php') as $file) {
                    app()->make('App\\Http\\Routes\\V2\\' . basename($file, '.php'))->map($router);
                }
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->replace(
            \Illuminate\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\TrustProxies::class
        );
        $middleware->replace(
            \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \App\Http\Middleware\CheckForMaintenanceMode::class
        );
        $middleware->replace(
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \App\Http\Middleware\TrimStrings::class
        );
        $middleware->remove(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->prepend([
            \App\Http\Middleware\CORS::class,
            \App\Http\Middleware\UAfilter::class,
        ]);

        $middleware->group('web', []);
        $middleware->group('api', [
            \App\Http\Middleware\ForceJson::class,
            \App\Http\Middleware\Language::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'user' => \App\Http\Middleware\User::class,
            'admin' => \App\Http\Middleware\Admin::class,
            'client' => \App\Http\Middleware\Client::class,
            'staff' => \App\Http\Middleware\Staff::class,
            'log' => \App\Http\Middleware\RequestLog::class,
        ]);

        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\Authenticate::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\View\ViewException $e, $request) {
            $message = '主题渲染失败。如更新主题，参数可能发生变化请重新配置主题后再试';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 500);
            }
            return response($message, 500);
        });
    })
    ->create();
