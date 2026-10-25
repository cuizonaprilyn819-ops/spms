<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\PDOException $exception, \Illuminate\Http\Request $request) {
            $code = (int) ($exception->errorInfo[1] ?? $exception->getCode());
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
            if (! in_array($code, [2002, 2003], true) && ! str_starts_with($sqlState, '08')) {
                return null;
            }

            $message = 'The database is temporarily unreachable. Please try again shortly.';
            $headers = ['Retry-After' => '30', 'Cache-Control' => 'no-store'];

            return $request->is('api/*') || $request->expectsJson()
                ? response()->json(['message' => $message], 503, $headers)
                : response()->view('errors.database-unavailable', ['message' => $message], 503, $headers);
        });
        $exceptions->shouldRenderJsonWhen(fn (\Illuminate\Http\Request $request, \Throwable $exception) =>
            $request->is('api/*') || $request->expectsJson()
        );
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            if ($response->getStatusCode() !== 419 || request()->is('api/*') || request()->expectsJson()) {
                return $response;
            }
            if (request()->is('login') && request()->isMethod('post')) {
                return redirect()->route('login')
                    ->with('status', 'Your sign-in page expired. Please enter your credentials and try again.')
                    ->header('Cache-Control', 'no-store, private');
            }
            if ($response->getStatusCode() === 419 && request()->routeIs('logout')) {
                return redirect()->route('logout.recover');
            }
            return $response;
        });
    })->create();
