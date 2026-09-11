<?php

namespace Tests\Unit\Providers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use App\Providers\DatabaseFileServiceProvider;

class DatabaseFileServiceProviderTest extends TestCase
{
    public function test_boot_does_nothing_if_not_sqlite()
    {
        Config::set('database.default', 'mysql');

        File::shouldReceive('exists')->never();
        File::shouldReceive('makeDirectory')->never();
        File::shouldReceive('put')->never();
        File::shouldReceive('chmod')->never();

        (new DatabaseFileServiceProvider($this->app))->boot();
        $this->addToAssertionCount(1); // to avoid risky test
    }

    public function test_boot_creates_directory_and_file_if_not_exist()
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', '/fake/path/database.sqlite');

        File::shouldReceive('exists')
            ->with('/fake/path')
            ->once()
            ->andReturn(false);

        File::shouldReceive('makeDirectory')
            ->with('/fake/path', 0777, true)
            ->once();

        File::shouldReceive('exists')
            ->with('/fake/path/database.sqlite')
            ->once()
            ->andReturn(false);

        File::shouldReceive('put')
            ->with('/fake/path/database.sqlite', '')
            ->once();

        File::shouldReceive('chmod')
            ->with('/fake/path/database.sqlite', 0775)
            ->once();

        (new DatabaseFileServiceProvider($this->app))->boot();
        $this->addToAssertionCount(1);
    }

    public function test_boot_skips_creation_if_both_exist()
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', '/fake/path/database.sqlite');

        File::shouldReceive('exists')
            ->with('/fake/path')
            ->once()
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with('/fake/path/database.sqlite')
            ->once()
            ->andReturn(true);

        File::shouldReceive('makeDirectory')->never();
        File::shouldReceive('put')->never();
        File::shouldReceive('chmod')->never();

        (new DatabaseFileServiceProvider($this->app))->boot();
        $this->addToAssertionCount(1);
    }

    public function test_boot_really_creates_file_with_group_writable_permissions()
    {
        $dir = sys_get_temp_dir().'/pd-dbtest-'.uniqid();
        $path = $dir.'/database.sqlite';

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $path);

        (new DatabaseFileServiceProvider($this->app))->boot();

        $this->assertFileExists($path);
        $this->assertSame('0775', substr(sprintf('%o', fileperms($path)), -4));

        unlink($path);
        rmdir($dir);
    }
}
