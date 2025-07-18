<?php

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
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
        $this->expectException(UploadFileException::class);
        $this->expectExceptionMessage('Could not create new folder');

        $existingPath = __DIR__ . '/existing_test_folder';
        // First call to create the folder
        UploadFileHelper::makeFolder($existingPath);
        // Second call should throw the exception
        UploadFileHelper::makeFolder($existingPath);
        rmdir($existingPath); // Cleanup
    }

    public function test_sanitize_path_throws_exception_for_directory_traversal()
    {
        $this->expectException(UploadFileException::class);
        $this->expectExceptionMessage('The upload path or dir contains invalid characters');

        // Use reflection to call the private static method
        $method = new ReflectionMethod(UploadFileHelper::class, 'sanitizePath');
        $method->setAccessible(true);
        $method->invoke(null, '../invalid/path');
    }

    public function test_make_file_creates_file_successfully()
    {
        $filePath = __DIR__ . '/test_file.txt';
        $result = UploadFileHelper::makeFile($filePath);

        $this->assertTrue($result);
        $this->assertFileExists($filePath);

        unlink($filePath); // Cleanup
    }

    public function test_delete_folder_deletes_folder_successfully()
    {
        $dirPath = __DIR__ . '/folder_to_delete';
        mkdir($dirPath, 0750, true);
        file_put_contents($dirPath . '/file.txt', 'test');

        $result = UploadFileHelper::deleteFolder($dirPath);

        $this->assertTrue($result);
        $this->assertDirectoryDoesNotExist($dirPath);
    }
}
