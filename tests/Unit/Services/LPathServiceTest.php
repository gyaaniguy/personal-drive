<?php

namespace Tests\Unit\Services;

use App\Services\PathService;
use App\Services\UUIDService;
use PHPUnit\Framework\TestCase;

class PathServiceTest extends TestCase
{
    protected PathService $pathService;
    protected UUIDService $uuidService;
    protected PathService $pathServiceMock;
    protected string $tempDir;

    public function testCleanDrivePublicPath()
    {
        $result = $this->pathService->cleanDrivePublicPath('this/folder/drive');
        self::assertEquals($result, 'this/folder/drive');

        $result = $this->pathService->cleanDrivePublicPath('/drive/my/drive');
        self::assertEquals($result, 'my/drive');

        $result = $this->pathService->cleanDrivePublicPath('/drive/');
        self::assertEquals($result, '');

        $result = $this->pathService->cleanDrivePublicPath('/drive');
        self::assertEquals($result, '');

        $result = $this->pathService->cleanDrivePublicPath('/drivemy/drive');
        self::assertEquals($result, '/drivemy/drive');
    }

    public function testGenPrivatePathFromPublicWithEmptyPublicPath()
    {
        $this->pathServiceMock->method('getStorageFolderPath')->willReturn('test/storage/path/test-uuid');

        $result = $this->pathServiceMock->genPrivatePathFromPublic('');
        $this->assertEquals('test/storage/path/test-uuid/', $result);
    }

    public function testGenPrivatePathFromPublicWithNonExistentPath()
    {
        $this->pathServiceMock->method('getStorageFolderPath')->willReturn('/test/storage/path/test-uuid');

        $result = $this->pathServiceMock->genPrivatePathFromPublic('/drive/nonexistent/path');
        $this->assertEquals('/test/storage/path/test-uuid/nonexistent/path/', $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->uuidService = $this->createMock(UUIDService::class);
        $this->pathService = new PathService($this->uuidService);
        $this->pathServiceMock = $this->getMockBuilder(PathService::class)
            ->setConstructorArgs([$this->uuidService])
            ->onlyMethods(['getStorageFolderPath'])
            ->getMock();
    }
}
