<?php

namespace App\Providers;

use App\Exceptions\PersonalDriveExceptions\ThrottleException;
use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use PDOException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceScheme('http');

        if (config('app.env') === 'production' && !config('app.disable_https')) {
            URL::forceScheme('https');
        }
        try {
            if (!Schema::hasTable('sessions')) {
                config(['session.driver' => 'file']);
            }
        } catch (QueryException | PDOException $e) {
            if (str_contains($e->getMessage(), 'readonly database') || str_contains(
                $e->getMessage(),
                'open database'
            )
            ) {
                http_response_code(500);
                echo 'Database error: check permissions on database.sqlite';
                exit;
            }
        }
        RateLimiter::for(
            'login', function (Request $request) {
                return Limit::perMinute(7)
                    ->by($request->ip())
                    ->response(
                        function () {
                            throw ThrottleException::tooMany();
                        }
                    );
            }
        );

        RateLimiter::for(
            'shared', function (Request $request) {
                return Limit::perMinute(20)
                    ->by($request->ip())
                    ->response(
                        function () {
                            throw ThrottleException::tooMany();
                        }
                    );
            }
        );
    }
}
