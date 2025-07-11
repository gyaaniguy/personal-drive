<?php

use App\Helpers\UploadFileHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UploadFileHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_full_path_of_uploaded_file()
    {
        $_FILES['files']['full_path'][0] = '/path/to/file.txt';
        $fullPath = UploadFileHelper::getUploadedFileFullPath(0);
        $this->assertEquals('/path/to/file.txt', $fullPath);
    }

    public function test_returns_normalized_relative_path_from_dot_slash()
    {
        $_FILES['files']['full_path'][0] = './file.txt';
        $fullPath = UploadFileHelper::getUploadedFileFullPath(0);
        $this->assertEquals('/file.txt', $fullPath);
    }

    public function test_returns_relative_path_starting_with_slash()
    {
        $_FILES['files']['full_path'][0] = '/file.txt';
        $fullPath = UploadFileHelper::getUploadedFileFullPath(0);
        $this->assertEquals('/file.txt', $fullPath);
    }

    public function test_creates_folder_with_specified_permissions()
    {
        $path = __DIR__ . '/test_folder';
        $result = UploadFileHelper::makeFolder($path, 0750);

        $this->assertTrue($result);
        $this->assertTrue(is_dir($path));
        $this->assertEquals('750', decoct(fileperms($path) & 0777));

        rmdir($path); // Cleanup
    }

    public function test_throws_exception_if_folder_already_exists()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not create new folder');

        $existingPath = __DIR__;
        UploadFileHelper::makeFolder($existingPath);
    }
}
