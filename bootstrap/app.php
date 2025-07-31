<?php

use App\Exceptions\PersonalDriveExceptions\FetchFileException;
use App\Exceptions\PersonalDriveExceptions\PersonalDriveException;
use App\Exceptions\PersonalDriveExceptions\ThrottleException;
use App\Exceptions\PersonalDriveExceptions\ThumbnailException;
use App\Http\Middleware\CheckSetup;
use App\Http\Middleware\HandleInertiaMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\ViteException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Validation\ValidationException;
use Illuminate\View\ViewException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('login');
        $middleware->web(append: [
            HandleInertiaMiddleware::class,
            AddLinkHeadersForPreloadedAssets::class,
        ], prepend: [
            CheckSetup::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e) {
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
                session()->flash('message', 'Something went wrong!'.$e->getMessage());
                session()->flash('status', false);
            }
            if (str_contains($e->getMessage(), 'readonly database') || str_contains($e->getMessage(), 'open database')) {
                return redirect()->route('rejected', 'database is readonly ! Make sure database/db/database.sqlite file has write permissions');
            }
        });
    })->create();
