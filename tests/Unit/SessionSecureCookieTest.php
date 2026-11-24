<?php

namespace Tests\Unit;

use Tests\TestCase;

class SessionSecureCookieTest extends TestCase
{
    private string|false $originalDisableHttps;
    private string|false $originalSessionSecure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDisableHttps = getenv('DISABLE_HTTPS');
        $this->originalSessionSecure = getenv('SESSION_SECURE_COOKIE');
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('DISABLE_HTTPS', $this->originalDisableHttps);
        $this->restoreEnv('SESSION_SECURE_COOKIE', $this->originalSessionSecure);

        parent::tearDown();
    }

    public function test_secure_cookie_is_disabled_when_disable_https_is_true(): void
    {
        // Mirrors the Docker / .env.example setup where .env sets
        // SESSION_SECURE_COOKIE=true and the container sets DISABLE_HTTPS=true.
        $this->setEnv('DISABLE_HTTPS', 'true');
        $this->setEnv('SESSION_SECURE_COOKIE', 'true');

        $config = require __DIR__ . '/../../config/session.php';

        $this->assertFalse($config['secure']);
    }

    public function test_secure_cookie_remains_enabled_when_disable_https_is_false(): void
    {
        $this->setEnv('DISABLE_HTTPS', 'false');
        $this->setEnv('SESSION_SECURE_COOKIE', 'true');

        $config = require __DIR__ . '/../../config/session.php';

        $this->assertTrue($config['secure']);
    }

    public function test_secure_cookie_respects_explicit_false_when_https_is_enabled(): void
    {
        $this->setEnv('DISABLE_HTTPS', 'false');
        $this->setEnv('SESSION_SECURE_COOKIE', 'false');

        $config = require __DIR__ . '/../../config/session.php';

        $this->assertFalse($config['secure']);
    }

    public function test_secure_cookie_remains_enabled_when_disable_https_is_unset(): void
    {
        $this->unsetEnv('DISABLE_HTTPS');
        $this->setEnv('SESSION_SECURE_COOKIE', 'true');

        $config = require __DIR__ . '/../../config/session.php';

        $this->assertTrue($config['secure']);
    }

    private function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function unsetEnv(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    private function restoreEnv(string $key, string|false $original): void
    {
        if ($original === false) {
            $this->unsetEnv($key);
        } else {
            $this->setEnv($key, $original);
        }
    }
}
