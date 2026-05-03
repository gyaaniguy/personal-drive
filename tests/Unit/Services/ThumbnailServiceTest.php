<?php

namespace Tests\Unit\Services;

use App\Models\LocalFile;
use App\Services\PathService;
use App\Services\ThumbnailService;
use App\Services\FileOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class ThumbnailServiceTest extends TestCase
{
    use RefreshDatabase;


    public function test_get_generatable_files_filters_by_type()
    {
        $image = LocalFile::factory()->create(['file_type' => 'image']);
        $video = LocalFile::factory()->create(['file_type' => 'video']);
        $doc = LocalFile::factory()->create(['file_type' => 'document']);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = new ThumbnailService($pathService, $fileOperations);

        $builder = $service->getGeneratableFiles([$image->id, $video->id, $doc->id]);

        $this->assertEqualsCanonicalizing(
            [$image->id, $video->id],
            $builder->pluck('id')->toArray()
        );
    }


    public function test_generate_thumbnails_for_image_returns_count()
    {
        $file = LocalFile::factory()->make(
            [
            'file_type' => 'image',
            ]
        );
        $file1 = LocalFile::factory()->make(
            [
            'file_type' => 'pdf',
            ]
        );
        $pathService = Mockery::mock(PathService::class);
        $uploadService = Mockery::mock(FileOperationsService::class);
        $service = Mockery::mock(ThumbnailService::class, [$pathService, $uploadService])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('generateThumbnail')->with($file)->andReturn(true);

        $result = $service->generateThumbnails(collect([$file, $file1]));

        $this->assertEquals(1, $result);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }


    public function test_ensure_image_driver_loaded_throws_exception_when_gd_not_loaded()
    {
        // Skip this test if GD is actually loaded
        if (extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is loaded, cannot test exception path');
        }

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = new ThumbnailService($pathService, $fileOperations);

        $this->expectException(\App\Exceptions\PersonalDriveExceptions\ImageRelatedException::class);
        $this->expectExceptionMessage('Could not generate thumbnail. Missing PHP extension: GD');

        $service->ensureImageDriverLoaded();
    }


    public function test_ensure_image_driver_loaded_succeeds_when_gd_is_loaded()
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = new ThumbnailService($pathService, $fileOperations);

        // Should not throw any exception
        $service->ensureImageDriverLoaded();

        $this->assertTrue(true);
    }


    public function test_generate_thumbnails_throws_exception_when_gd_not_loaded()
    {
        // Skip this test if GD is actually loaded
        if (extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is loaded, cannot test exception path');
        }

        $file = LocalFile::factory()->make(['file_type' => 'image']);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = new ThumbnailService($pathService, $fileOperations);

        $this->expectException(\App\Exceptions\PersonalDriveExceptions\ImageRelatedException::class);
        $this->expectExceptionMessage('Could not generate thumbnail. Missing PHP extension: GD');

        $service->generateThumbnails(collect([$file]));
    }


    public function test_generate_thumbnails_calls_ensure_image_driver_loaded()
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }

        $file = LocalFile::factory()->make(['file_type' => 'image']);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = Mockery::mock(ThumbnailService::class, [$pathService, $fileOperations])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Expect ensureImageDriverLoaded to be called
        $service->shouldReceive('ensureImageDriverLoaded')->once();
        $service->shouldReceive('generateThumbnail')->with($file)->andReturn(1);

        $result = $service->generateThumbnails(collect([$file]));

        $this->assertEquals(1, $result);
    }


    public function test_get_full_file_thumbnail_path_for_image_type()
    {
        $file = LocalFile::factory()->make([
            'file_type' => 'image',
            'filename' => 'test.jpg',
            'public_path' => 'folder/subfolder',
        ]);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);

        $expectedThumbnailDir = THUMBS_SUBDIR . DS . 'folder' . DS . 'subfolder' . DS;
        $expectedStoragePath = '/storage/path';

        $pathService->shouldReceive('getThumbnailAbsPath')->once()->andReturn($expectedStoragePath);
        $fileOperations->shouldReceive('directoryExists')->with($expectedThumbnailDir)->once()->andReturn(true);

        $service = new ThumbnailService($pathService, $fileOperations);
        $result = $service->getFullFileThumbnailPath($file);

        $expectedPath = $expectedStoragePath . DS . 'folder' . DS . 'subfolder' . DS . 'test.jpg';
        $this->assertEquals($expectedPath, $result);
    }


    public function test_get_full_file_thumbnail_path_for_video_type()
    {
        $file = LocalFile::factory()->make([
            'file_type' => 'video',
            'filename' => 'test.mp4',
            'public_path' => 'videos',
        ]);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);

        $expectedThumbnailDir = THUMBS_SUBDIR . DS . 'videos' . DS;
        $expectedStoragePath = '/storage/path';

        $pathService->shouldReceive('getThumbnailAbsPath')->once()->andReturn($expectedStoragePath);
        $fileOperations->shouldReceive('directoryExists')->with($expectedThumbnailDir)->once()->andReturn(false);
        $fileOperations->shouldReceive('makeFolder')->with($expectedThumbnailDir)->once()->andReturn(true);

        $service = new ThumbnailService($pathService, $fileOperations);
        $result = $service->getFullFileThumbnailPath($file);

        $expectedPath = $expectedStoragePath . DS . 'videos' . DS . 'test.mp4.jpeg';
        $this->assertEquals($expectedPath, $result);
    }


    public function test_get_full_file_thumbnail_path_creates_directory_if_not_exists()
    {
        $file = LocalFile::factory()->make([
            'file_type' => 'video',
            'filename' => 'video.mp4',
            'public_path' => 'test/path',
        ]);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);

        $expectedThumbnailDir = THUMBS_SUBDIR . DS . 'test' . DS . 'path' . DS;
        $expectedStoragePath = '/storage/path';

        $pathService->shouldReceive('getThumbnailAbsPath')->once()->andReturn($expectedStoragePath);
        $fileOperations->shouldReceive('directoryExists')->with($expectedThumbnailDir)->once()->andReturn(false);
        $fileOperations->shouldReceive('makeFolder')->with($expectedThumbnailDir)->once()->andReturn(true);

        $service = new ThumbnailService($pathService, $fileOperations);
        $result = $service->getFullFileThumbnailPath($file);

        $this->assertStringContainsString('.jpeg', $result);
    }


    public function test_handle_video_returns_false_when_file_not_exists()
    {
        $file = LocalFile::factory()->make([
            'file_type' => 'video',
            'filename' => 'nonexistent.mp4',
            'private_path' => '/nonexistent/path',
        ]);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = new ThumbnailService($pathService, $fileOperations);

        // Test through generateThumbnail which calls handleVideo
        $result = $service->generateThumbnail($file);

        $this->assertEquals(0, $result);
    }


    public function test_handle_image_returns_false_when_file_not_exists()
    {
        $file = LocalFile::factory()->make([
            'file_type' => 'image',
            'filename' => 'nonexistent.jpg',
            'private_path' => '/nonexistent/path',
        ]);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = Mockery::mock(ThumbnailService::class, [$pathService, $fileOperations])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $result = $service->handleImage($file);

        $this->assertFalse($result);
    }


    public function test_handle_image_returns_false_when_resize_image_fails()
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }

        // Create a temporary file with invalid image content
        $tempFile = tempnam(sys_get_temp_dir(), 'test_image_');
        file_put_contents($tempFile, 'not a valid image');

        $file = LocalFile::factory()->make([
            'file_type' => 'image',
            'filename' => basename($tempFile),
            'private_path' => dirname($tempFile),
        ]);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);

        $expectedThumbnailDir = THUMBS_SUBDIR . DS;
        $pathService->shouldReceive('getThumbnailAbsPath')->andReturn('/storage/path');
        $fileOperations->shouldReceive('directoryExists')->andReturn(true);

        $service = new ThumbnailService($pathService, $fileOperations);

        // Test through generateThumbnail which calls handleImage
        $result = $service->generateThumbnail($file);

        // resizeImage will fail with invalid image content
        $this->assertEquals(0, $result);
    }


    public function test_handle_image_returns_true_when_resize_image_succeeds()
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }

        // Create a temp directory for our test
        $tempDir = sys_get_temp_dir() . '/thumb_test_' . uniqid();
        mkdir($tempDir);

        // Create a valid JPEG file using GD
        $tempFile = $tempDir . '/test_image.jpg';
        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $tempFile);
        imagedestroy($image);

        $file = LocalFile::factory()->make([
            'file_type' => 'image',
            'filename' => 'test_image.jpg',
            'private_path' => $tempDir,
            'public_path' => '',
        ]);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);

        $pathService->shouldReceive('getThumbnailAbsPath')->andReturn($tempDir);
        $fileOperations->shouldReceive('directoryExists')->andReturn(true);

        $service = new ThumbnailService($pathService, $fileOperations);

        // Test through generateThumbnail which calls handleImage
        $result = $service->generateThumbnail($file);

        $this->assertEquals(1, $result);
    }


    public function test_generate_thumbnail_returns_zero_for_unknown_file_type()
    {
        $file = LocalFile::factory()->make(['file_type' => 'document']);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = new ThumbnailService($pathService, $fileOperations);

        $result = $service->generateThumbnail($file);

        $this->assertEquals(0, $result);
    }


    public function test_gen_thumbnails_for_file_ids_returns_count()
    {
        $image = LocalFile::factory()->create(['file_type' => 'image']);
        $video = LocalFile::factory()->create(['file_type' => 'video']);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = Mockery::mock(ThumbnailService::class, [$pathService, $fileOperations])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Mock generateThumbnails instead of generateThumbnail
        $service->shouldReceive('generateThumbnails')->andReturn(2);

        $result = $service->genThumbnailsForFileIds([$image->id, $video->id]);

        $this->assertEquals(2, $result);
    }


    public function test_resize_image_returns_false_on_exception()
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }

        // Create a file with invalid image content
        $tempInput = tempnam(sys_get_temp_dir(), 'test_input_');
        file_put_contents($tempInput, 'not a valid image');

        $tempOutput = tempnam(sys_get_temp_dir(), 'test_output_');

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = new ThumbnailService($pathService, $fileOperations);

        // Use reflection to test the private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resizeImage');
        $method->setAccessible(true);

        $result = $method->invoke($service, $tempInput, $tempOutput);

        $this->assertFalse($result);
    }


    public function test_resize_image_returns_true_on_success()
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }

        // Create a valid JPEG file using GD
        $tempInput = tempnam(sys_get_temp_dir(), 'test_input_') . '.jpg';
        $tempOutput = tempnam(sys_get_temp_dir(), 'test_output_') . '.jpg';
        
        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $tempInput);
        imagedestroy($image);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = new ThumbnailService($pathService, $fileOperations);

        // Use reflection to test the private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resizeImage');
        $method->setAccessible(true);

        $result = $method->invoke($service, $tempInput, $tempOutput);

        // Clean up is handled by OS temp directory
        $this->assertTrue($result);
    }
}
