<?php

namespace App\Providers;

use App\Exceptions\PersonalDriveExceptions\ThrottleException;
use App\Models\Setting;
use App\Services\UUIDService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(UUIDService::class, function () {
            $setting = $this->app->make(Setting::class);
            return new UUIDService($setting);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceScheme('http');

        if (config('app.env') === 'production' && !env('DISABLE_HTTPS')) {
            URL::forceScheme('https');
        }
        try {
            if (!Schema::hasTable('sessions')) {
                config(['session.driver' => 'file']);
            }
        } catch (SQLiteDatabaseDoesNotExistException $e) {
            header('Location: /error?message=' . urlencode('Frontend not built. Ensure node, npm are installed Run "npm install && npm run build"'));
            exit;
        } catch (QueryException | \PDOException $e) {
            if (str_contains($e->getMessage(), 'readonly database') || str_contains($e->getMessage(), 'open database')) {
                http_response_code(500);
                echo 'Database error: check permissions on database.sqlite';
                exit;
            }
        }
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(7)
                ->by($request->ip())
                ->response(function () {
                    throw ThrottleException::toomany();
                });
        });

        RateLimiter::for('shared', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    throw ThrottleException::toomany();
                });
        });
    }
}
