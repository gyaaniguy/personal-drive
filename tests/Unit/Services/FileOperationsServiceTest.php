<?php

namespace Tests\Unit\Services;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Services\FileOperationsService;
use App\Services\LPathService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Mockery;
use Tests\TestCase;

class FileOperationsServiceTest extends TestCase
{
    use RefreshDatabase;

    private $fs;
    private FileOperationsService $service;

    public function testMakeFolderThrowsIfExists()
    {
        $this->fs->shouldReceive('directoryExists')
            ->with('dir')->andReturn(true);
        $this->expectException(UploadFileException::class);
        $this->service->makeFolder('dir');
    }

    public function testMakeFolderReturnsFalseOnFailure()
    {
        $this->fs->shouldReceive('directoryExists')
            ->once()->with('dir')->andReturn(false);

        $this->fs->shouldReceive('createDirectory')
            ->once()->with('dir', ['visibility' => 'private'])
            ->andThrow(new Exception());

        $this->assertFalse($this->service->makeFolder('dir'));
    }

    public function testMakeFolderReturnsTrueOnSuccess()
    {
        $this->fs->shouldReceive('directoryExists')->with('dir')->andReturn(false);

        $this->fs->shouldReceive('createDirectory')
            ->with('dir', ['visibility' => 'private']);

        $this->assertTrue($this->service->makeFolder('dir'));
    }

    public function testMakeFolderThrowsCreateFails()
    {
        $this->fs->shouldReceive('directoryExists')->with('dir')->andReturn(false);

        $this->fs->shouldReceive('createDirectory')
            ->with('dir', ['visibility' => 'private'])->andThrow(new Exception());

        $this->assertFalse($this->service->makeFolder('dir'));
    }


    protected function setUp(): void
    {
        $adapter = new InMemoryFilesystemAdapter();
//        $this->fs = new Filesystem($adapter);

//
//        $mockPathService->shouldReceive('getStorageFolderPath')->andReturn('ignored');

        $this->fs = Mockery::mock(Filesystem::class);

        $pathService = Mockery::mock(LPathService::class);
        $pathService->shouldReceive('getStorageFolderPath')->andReturn('');

        $this->service = new FileOperationsService($pathService);
        $this->service->setFilesystem($this->fs);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
