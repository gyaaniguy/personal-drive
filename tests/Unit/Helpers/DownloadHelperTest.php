<?php

namespace Tests\Unit\Helpers;

use App\Exceptions\PersonalDriveExceptions\FetchFileException;
use App\Helpers\DownloadHelper;
use App\Models\LocalFile;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;
use ZipArchive;

class DownloadHelperTest extends TestCase
{
    private DownloadHelper $downloadHelper;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->downloadHelper = new DownloadHelper();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'download_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        if (file_exists($this->tempDir)) {
            $this->deleteDir($this->tempDir);
        }
        parent::tearDown();
    }

    private function deleteDir(string $dir): void
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->deleteDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function test_create_zip_archive_with_single_file()
    {
        $filePath = $this->tempDir . DIRECTORY_SEPARATOR . 'test_file.txt';
        file_put_contents($filePath, 'Hello, world!');

        $mockLocalFile = Mockery::mock(LocalFile::class);
        $mockLocalFile->shouldReceive('getPrivatePathNameForFile')
            ->andReturn($filePath);

        $localFiles = new Collection([$mockLocalFile]);
        $outputZipPath = $this->tempDir . DIRECTORY_SEPARATOR . 'output.zip';

        $this->downloadHelper->createZipArchive($localFiles, $outputZipPath);

        $this->assertFileExists($outputZipPath);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($outputZipPath));
        $this->assertEquals(1, $zip->numFiles);
        $this->assertEquals('test_file.txt', $zip->getNameIndex(0));
        $this->assertEquals('Hello, world!', $zip->getFromIndex(0));
        $zip->close();
    }

    public function test_create_zip_archive_with_directory()
    {
        $dirPath = $this->tempDir . DIRECTORY_SEPARATOR . 'test_dir';
        mkdir($dirPath, 0777, true);
        file_put_contents($dirPath . DIRECTORY_SEPARATOR . 'file1.txt', 'Content 1');
        file_put_contents($dirPath . DIRECTORY_SEPARATOR . 'file2.txt', 'Content 2');
        mkdir($dirPath . DIRECTORY_SEPARATOR . 'subdir', 0777, true);
        file_put_contents($dirPath . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file3.txt', 'Content 3');

        $mockLocalFile = Mockery::mock(LocalFile::class);
        $mockLocalFile->shouldReceive('getPrivatePathNameForFile')
            ->andReturn($dirPath);
        $mockLocalFile->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('test_dir');

        $localFiles = new Collection([$mockLocalFile]);
        $outputZipPath = $this->tempDir . DIRECTORY_SEPARATOR . 'output_dir.zip';

        $this->downloadHelper->createZipArchive($localFiles, $outputZipPath);

        $this->assertFileExists($outputZipPath);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($outputZipPath));
        $this->assertEquals(3, $zip->numFiles);

        $fileNames = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileNames[] = $zip->getNameIndex($i);
        }

        $this->assertContains('test_dir/file1.txt', $fileNames);
        $this->assertContains('test_dir/file2.txt', $fileNames);
        $this->assertContains('test_dir/subdir/file3.txt', $fileNames);
        $zip->close();
    }

    public function test_create_zip_archive_throws_exception_on_zip_open_failure()
    {
        $nonWritableDir = $this->tempDir . DIRECTORY_SEPARATOR . 'non_writable_dir';
        mkdir($nonWritableDir, 0444, true); // Create a non-writable directory

        $mockLocalFile = Mockery::mock(LocalFile::class);
        $mockLocalFile->shouldReceive('getPrivatePathNameForFile')
            ->andReturn($this->tempDir . DIRECTORY_SEPARATOR . 'non_existent_file.txt');

        $localFiles = new Collection([$mockLocalFile]);
        $outputZipPath = $nonWritableDir . DIRECTORY_SEPARATOR . 'output.zip';

        $this->expectException(FetchFileException::class);
        $this->expectExceptionMessage('Could not generate zip to download. Too large or empty folders ?');

        $this->downloadHelper->createZipArchive($localFiles, $outputZipPath);
    }

    public function test_create_zip_archive_skips_non_existent_files()
    {
        $filePath = $this->tempDir . DIRECTORY_SEPARATOR . 'existing_file.txt';
        file_put_contents($filePath, 'Existing content');

        $mockLocalFile1 = Mockery::mock(LocalFile::class);
        $mockLocalFile1->shouldReceive('getPrivatePathNameForFile')
            ->andReturn($filePath);

        $mockLocalFile2 = Mockery::mock(LocalFile::class);
        $mockLocalFile2->shouldReceive('getPrivatePathNameForFile')
            ->andReturn($this->tempDir . DIRECTORY_SEPARATOR . 'non_existent_file.txt');

        $localFiles = new Collection([$mockLocalFile1, $mockLocalFile2]);
        $outputZipPath = $this->tempDir . DIRECTORY_SEPARATOR . 'output_skip.zip';

        $this->downloadHelper->createZipArchive($localFiles, $outputZipPath);

        $this->assertFileExists($outputZipPath);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($outputZipPath));
        $this->assertEquals(1, $zip->numFiles);
        $this->assertEquals('existing_file.txt', $zip->getNameIndex(0));
        $zip->close();
    }
}
