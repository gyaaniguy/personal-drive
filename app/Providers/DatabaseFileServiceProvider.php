<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;

class DatabaseFileServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        if (config('database.default') === 'sqlite') {
            $databasePath = config('database.connections.sqlite.database');
            $databaseDir = dirname($databasePath);

            if (!File::exists($databaseDir)) {
                File::makeDirectory($databaseDir, 0777, true);
            }
            if (!File::exists($databasePath)) {
                File::put($databasePath, '');
                File::chmod($databasePath, 0666);
            }
        }
    }
}
