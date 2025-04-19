<?php

namespace Tests\Unit\Services;

use App\Services\LPathService;
use App\Services\UUIDService;
use PHPUnit\Framework\TestCase;

class LPathServiceTest extends TestCase
{
    protected LPathService $lPathService;
    protected UUIDService $uuidService;
    protected LPathService $lPathServiceMock;
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uuidService = $this->createMock(UUIDService::class);
        $this->lPathService = new LPathService($this->uuidService);
        $this->lPathServiceMock = $this->getMockBuilder(LPathService::class)
            ->setConstructorArgs([$this->uuidService])
            ->onlyMethods(['getStorageDirPath'])
            ->getMock();
    }

    public function testCleanDrivePublicPath()
    {
        $result = $this->lPathService->cleanDrivePublicPath('this/folder/drive');
        self::assertEquals($result, 'this/folder/drive');

        $result = $this->lPathService->cleanDrivePublicPath('/drive/my/drive');
        self::assertEquals($result, 'my/drive');

        $result = $this->lPathService->cleanDrivePublicPath('/drive/');
        self::assertEquals($result, '');

        $result = $this->lPathService->cleanDrivePublicPath('/drive');
        self::assertEquals($result, '');

        $result = $this->lPathService->cleanDrivePublicPath('/drivemy/drive');
        self::assertEquals($result, '/drivemy/drive');
    }

    public function testGenPrivatePathFromPublicWithEmptyPublicPath()
    {
        $this->lPathServiceMock->method('getStorageDirPath')->willReturn('test/storage/path/test-uuid');

        $result = $this->lPathServiceMock->genPrivatePathFromPublic('');
        $this->assertEquals('test/storage/path/test-uuid/', $result);
    }

    public function testGenPrivatePathFromPublicWithNonExistentPath()
    {

        $this->lPathServiceMock->method('getStorageDirPath')->willReturn('/test/storage/path/test-uuid');

        $result = $this->lPathServiceMock->genPrivatePathFromPublic('/drive/nonexistent/path');
        $this->assertEquals('/test/storage/path/test-uuid/nonexistent/path/', $result);
    }

}
