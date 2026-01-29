<?php

namespace Tests\Unit\Services;

use App\Services\PathService;
use PHPUnit\Framework\TestCase;

class PathServiceTest extends TestCase
{
    protected PathService $pathService;
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



    public function testGenPrivatePathFromPublicWithNonExistentPath()
    {
        $this->pathServiceMock->method('getStorageFolderPath')->willReturn('/test/storage/path/test-uuid');

        $result = $this->pathServiceMock->genPrivatePathFromPublic('/drive/nonexistent/path');
        $this->assertEquals('/test/storage/path/test-uuid/nonexistent/path/', $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pathService = new PathService();
        $this->pathServiceMock = $this->getMockBuilder(PathService::class)
            ->onlyMethods(['getStorageFolderPath'])
            ->getMock();
    }
}
