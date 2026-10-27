<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AppDebugDefaultTest extends TestCase
{
    public function test_debug_defaults_to_off_when_env_is_missing(): void
    {
        // Re-read the config array with APP_DEBUG unset, as on a fresh
        // deployment that copied .env.example incompletely.
        putenv('APP_DEBUG');
        unset($_ENV['APP_DEBUG'], $_SERVER['APP_DEBUG']);

        $config = require __DIR__ . '/../../config/app.php';

        $this->assertFalse($config['debug']);
    }
}
