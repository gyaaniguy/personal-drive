<?php

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Helpers\UploadFileHelper;
use Tests\TestCase;

class UploadFileHelperTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        // Clear $_FILES superglobal before each test
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        // Clean up any created files or directories
        $this->cleanupDirectory(__DIR__ . '/test_folder');
        $this->cleanupDirectory(__DIR__ . '/existing_test_folder');
        $this->cleanupDirectory(__DIR__ . '/folder_to_delete');
        $this->cleanupFile(__DIR__ . '/test_file.txt');
        $_FILES = [];
    }

    private function cleanupDirectory(string $path): void
    {
        if (is_dir($path)) {
            exec(sprintf('rm -rf %s', escapeshellarg($path)));
        }
    }

    private function cleanupFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function test_returns_full_path_of_uploaded_file()
    {
        $_FILES['files'] = [
            'full_path' => [
                '/path/to/file.txt',
            ],
        ];
        $fullPath = UploadFileHelper::getUploadedFileFullPath(0);
        $this->assertEquals('/path/to/file.txt', $fullPath);
    }

    public function test_returns_normalized_relative_path_from_dot_slash()
    {
        $_FILES['files'] = [
            'full_path' => [
                './file.txt',
            ],
        ];
        $fullPath = UploadFileHelper::getUploadedFileFullPath(0);
        $this->assertEquals('/file.txt', $fullPath);
    }

    public function test_returns_relative_path_starting_with_slash()
    {
        $_FILES['files'] = [
            'full_path' => [
                '/file.txt',
            ],
        ];
        $fullPath = UploadFileHelper::getUploadedFileFullPath(0);
        $this->assertEquals('/file.txt', $fullPath);
    }

    public function test_get_uploaded_file_full_path_throws_exception_for_directory_traversal()
    {
        $this->expectException(UploadFileException::class);
        $this->expectExceptionMessage('The upload path or dir contains invalid characters');

        $_FILES['files'] = [
            'full_path' => [
                '/../invalid/path',
            ],
        ];
        UploadFileHelper::getUploadedFileFullPath(0);
    }

    public function test_make_file_creates_file_successfully()
    {
        $filePath = __DIR__ . '/test_file.txt';
        $result = UploadFileHelper::makeFile($filePath);

        $this->assertTrue($result);
        $this->assertFileExists($filePath);
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
