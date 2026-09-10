<?php

use App\Exceptions\PersonalDriveExceptions\FetchFileException;
use App\Exceptions\PersonalDriveExceptions\PersonalDriveException;
use App\Exceptions\PersonalDriveExceptions\ThrottleException;
use App\Exceptions\PersonalDriveExceptions\ThumbnailException;
use App\Http\Middleware\CheckSetup;
use App\Http\Middleware\HandleInertiaMiddleware;
use App\Http\Middleware\OptionalAuth;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\View\ViewException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

if (!defined('CONTENT_SUBDIR')) {
    define('CONTENT_SUBDIR', 'storage_personaldrive');
}

if (!defined('THUMBS_SUBDIR')) {
    define('THUMBS_SUBDIR', 'thumbnails_directory');
}

if (!defined('TEMP_SUBDIR')) {
    define('TEMP_SUBDIR', 'temp_directory');
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('login');
        $middleware->replace(
            "Illuminate\Http\Middleware\TrustProxies",
            "App\Http\Middleware\TrustProxies"
        );
        $middleware->priority([
            OptionalAuth::class,
            Authenticate::class,
        ]);
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->web(append: [
            HandleInertiaMiddleware::class,
            AddLinkHeadersForPreloadedAssets::class,
        ], prepend: [
            CheckSetup::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Business exceptions are shown to the user (flash + redirect) below; they
        // are expected user-input outcomes, not server faults, so don't log them.
        $exceptions->dontReport(PersonalDriveException::class);
        $exceptions->render(function (Throwable $e) {
            // API routes return JSON errors
            if (request()->is('api/*')) {
                if ($e instanceof ValidationException) {
                    return response()->json([
                        'message' => 'Validation failed',
                        'errors' => $e->errors(),
                    ], 422);
                }
                if ($e instanceof AuthenticationException) {
                    return response()->json(['message' => 'Unauthenticated'], 401);
                }
                if ($e instanceof NotFoundHttpException) {
                    return response()->json(['message' => 'Not found'], 404);
                }
                if ($e instanceof ThrottleException) {
                    return response()->json(['message' => 'Too many requests'], 429);
                }
                if ($e instanceof PersonalDriveException) {
                    return response()->json(['message' => $e->getMessage()], 422);
                }
                if ($e instanceof AccessDeniedHttpException) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }
                if (str_contains($e->getMessage(), 'readonly database') || str_contains($e->getMessage(), 'open database')) {
                    return response()->json(['message' => 'Database error'], 500);
                }
                Log::error('API exception', ['exception' => $e]);

                return response()->json(['message' => 'Internal server error'], 500);
            }

            // Web error handling
            if (($e instanceof ViewException) && str_contains($e->getMessage(), 'Vite manifest not found')) {
                header(
                    'Location: /error?message=' .
                    urlencode('Frontend not built. Ensure node, npm are installed Run "npm install && npm run build"')
                );
                exit;
            }
            if ($e instanceof FetchFileException) {
                return redirect()->route('rejected', ['message' => $e->getMessage()]);
            }
            if ($e instanceof ThrottleException) {
                return redirect()->route('rejected', ['message' => $e->getMessage()]);
            }
            if ($e instanceof ThumbnailException) {
                session()->flash('message', $e->getMessage());
                session()->flash('status', false);
            }
            if ($e instanceof PersonalDriveException) {
                session()->flash('message', $e->getMessage());
                session()->flash('status', false);

                return redirect()->back();
            }
            if ($e instanceof ValidationException) {
                session()->flash('message', 'Please check the form for errors.');
                session()->flash('status', false);

                return redirect()->back()->withErrors($e->errors());
            }
            if ($e instanceof Exception && ! $e instanceof AuthenticationException) {
                Log::error('Unhandled application exception', ['exception' => $e]);
                session()->flash('message', 'Something went wrong');
                session()->flash('status', false);
            }
            if (str_contains($e->getMessage(), 'readonly database') || str_contains($e->getMessage(), 'open database')) {
                return redirect()->route('rejected', 'database is readonly ! Make sure database/db/database.sqlite file has write permissions');
            }
        });
    })->create();
