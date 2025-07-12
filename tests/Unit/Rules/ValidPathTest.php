<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidPath;
use PHPUnit\Framework\TestCase;

class ValidPathTest extends TestCase
{
    public function test_valid_path_passes()
    {
        $rule = new ValidPath();
        $this->assertNull($rule->validate('path', 'valid/path/to/file.txt', function ($message) {
            $this->fail('Validation failed unexpectedly: ' . $message);
        }));
    }

    public function test_path_with_directory_traversal_fails()
    {
        $rule = new ValidPath();
        $failCalled = false;
        $rule->validate('path', '../invalid/path', function ($message) use (&$failCalled) {
            $this->assertEquals('The :attribute contains directory traversal sequences.', $message);
            $failCalled = true;
        });
        $this->assertTrue($failCalled, 'Validation did not fail for directory traversal.');
    }

    public function test_path_with_backslash_directory_traversal_fails()
    {
        $rule = new ValidPath();
        $failCalled = false;
        $rule->validate('path', '..\\invalid\\path', function ($message) use (&$failCalled) {
            $this->assertEquals('The :attribute contains directory traversal sequences.', $message);
            $failCalled = true;
        });
        $this->assertTrue($failCalled, 'Validation did not fail for backslash directory traversal.');
    }

    public function test_path_with_invalid_characters_fails()
    {
        $rule = new ValidPath();
        $failCalled = false;
        $rule->validate('path', 'invalid*path', function ($message) use (&$failCalled) {
            $this->assertEquals('The :attribute contains invalid characters.', $message);
            $failCalled = true;
        });
        $this->assertTrue($failCalled, 'Validation did not fail for invalid characters.');
    }

    public function test_path_with_mixed_valid_and_invalid_characters_fails()
    {
        $rule = new ValidPath();
        $failCalled = false;
        $rule->validate('path', 'valid/path?with/invalid', function ($message) use (&$failCalled) {
            $this->assertEquals('The :attribute contains invalid characters.', $message);
            $failCalled = true;
        });
        $this->assertTrue($failCalled, 'Validation did not fail for mixed characters.');
    }

    public function test_root_path_passes()
    {
        $rule = new ValidPath();
        $this->assertNull($rule->validate('path', '/', function ($message) {
            $this->fail('Validation failed unexpectedly for root path: ' . $message);
        }));
    }

    public function test_path_with_spaces_passes()
    {
        $rule = new ValidPath();
        $this->assertNull($rule->validate('path', 'path with spaces', function ($message) {
            $this->fail('Validation failed unexpectedly for path with spaces: ' . $message);
        }));
    }

    public function test_path_with_colons_passes()
    {
        $rule = new ValidPath();
        $this->assertNull($rule->validate('path', 'C:/path/to/file', function ($message) {
            $this->fail('Validation failed unexpectedly for path with colons: ' . $message);
        }));
    }

    public function test_path_with_dots_passes()
    {
        $rule = new ValidPath();
        $this->assertNull($rule->validate('path', './current/directory', function ($message) {
            $this->fail('Validation failed unexpectedly for path with dots: ' . $message);
        }));
    }
}
