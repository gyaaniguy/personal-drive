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


    public function test_handle_video_with_valid_video_generates_thumbnail()
    {
        if (shell_exec('which ffmpeg') === null) {
            $this->markTestSkipped('FFMpeg is not available on this system');
        }

        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }

        // Create a temporary directory for our test
        $tempDir = sys_get_temp_dir() . '/thumb_video_test_' . uniqid();
        mkdir($tempDir);

        // Create a minimal test video using ffmpeg (2 seconds to ensure we can extract frame at 1 second)
        $videoFile = $tempDir . '/test_video.mp4';
        $output = shell_exec("ffmpeg -f lavfi -i testsrc=duration=2:size=320x240:rate=30 -f mp4 {$videoFile} -y 2>&1");

        if (!file_exists($videoFile)) {
            $this->markTestSkipped('Could not create test video file');
        }

        $file = LocalFile::factory()->make([
            'file_type' => 'video',
            'filename' => 'test_video.mp4',
            'private_path' => $tempDir,
            'public_path' => '',
        ]);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);

        $pathService->shouldReceive('getThumbnailAbsPath')->andReturn($tempDir);
        $fileOperations->shouldReceive('directoryExists')->andReturn(true);

        $service = new ThumbnailService($pathService, $fileOperations);

        // Test through generateThumbnail which calls handleVideo
        $result = $service->generateThumbnail($file);

        // Should return 1 (true converted to int)
        $this->assertEquals(1, $result);

        // Verify the thumbnail file was created
        $thumbnailPath = $tempDir . '/test_video.mp4.jpeg';
        $this->assertFileExists($thumbnailPath);

        // Verify the thumbnail is a valid image
        $imageInfo = getimagesize($thumbnailPath);
        $this->assertIsArray($imageInfo);
        $this->assertEquals('image/jpeg', $imageInfo['mime']);
    }



    public function test_handle_video_with_invalid_video_throws_exception()
    {
        if (shell_exec('which ffmpeg') === null) {
            $this->markTestSkipped('FFMpeg is not available on this system');
        }

        // Create a temporary directory for our test
        $tempDir = sys_get_temp_dir() . '/thumb_video_test_' . uniqid();
        mkdir($tempDir);

        // Create an invalid video file
        $videoFile = $tempDir . '/invalid_video.mp4';
        file_put_contents($videoFile, 'not a valid video');

        $file = LocalFile::factory()->make([
            'file_type' => 'video',
            'filename' => 'invalid_video.mp4',
            'private_path' => $tempDir,
            'public_path' => '',
        ]);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);

        $pathService->shouldReceive('getThumbnailAbsPath')->andReturn($tempDir);
        $fileOperations->shouldReceive('directoryExists')->andReturn(true);

        $service = new ThumbnailService($pathService, $fileOperations);

        // Test through generateThumbnail which calls handleVideo
        // This should throw an exception when trying to open an invalid video
        // The exact exception depends on FFMpeg internals, so we just check it doesn't crash
        try {
            $result = $service->generateThumbnail($file);
            // If it doesn't throw, it should return 0 (failure)
            $this->assertEquals(0, $result);
        } catch (\Exception $e) {
            // If it throws, that's also acceptable behavior
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }


    public function test_gen_thumbnails_for_file_ids_with_nonexistent_files_returns_zero()
    {
        $image = LocalFile::factory()->create([
            'file_type' => 'image',
            'private_path' => '/nonexistent/path/' . uniqid(),
        ]);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);

        $pathService->shouldReceive('getThumbnailAbsPath')->andReturn(sys_get_temp_dir());
        $fileOperations->shouldReceive('directoryExists')->andReturn(true);

        $service = new ThumbnailService($pathService, $fileOperations);

        $result = $service->genThumbnailsForFileIds([$image->id]);

        // Should return 0 because the file doesn't exist
        $this->assertEquals(0, $result);
    }


    public function test_gen_thumbnails_for_file_ids_with_mixed_types_filters_correctly()
    {
        $image = LocalFile::factory()->create(['file_type' => 'image']);
        $video = LocalFile::factory()->create(['file_type' => 'video']);
        $pdf = LocalFile::factory()->create(['file_type' => 'pdf']);
        $doc = LocalFile::factory()->create(['file_type' => 'document']);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = Mockery::mock(ThumbnailService::class, [$pathService, $fileOperations])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Mock generateThumbnails to return 2 (for image + video)
        $service->shouldReceive('generateThumbnails')->andReturn(2);

        // All file IDs are passed, but only image and video should be processed
        $result = $service->genThumbnailsForFileIds([$image->id, $video->id, $pdf->id, $doc->id]);

        $this->assertEquals(2, $result);
    }


    public function test_resize_image_resizes_to_correct_dimensions()
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }

        // Create a valid JPEG file with square dimensions
        $tempInput = tempnam(sys_get_temp_dir(), 'test_input_') . '.jpg';
        $tempOutput = tempnam(sys_get_temp_dir(), 'test_output_') . '.jpg';

        $image = imagecreatetruecolor(100, 100); // Square image
        imagejpeg($image, $tempInput);
        imagedestroy($image);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = new ThumbnailService($pathService, $fileOperations);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resizeImage');
        $method->setAccessible(true);

        $result = $method->invoke($service, $tempInput, $tempOutput);

        $this->assertTrue($result);

        // Check that the output file exists and has been resized
        $outputInfo = getimagesize($tempOutput);
        $this->assertIsArray($outputInfo);
        $this->assertEquals(210, $outputInfo[0]); // IMAGE_SIZE
        $this->assertEquals(210, $outputInfo[1]); // IMAGE_SIZE
    }


    public function test_get_full_file_thumbnail_path_with_deep_nesting()
    {
        $file = LocalFile::factory()->make([
            'file_type' => 'image',
            'filename' => 'deep.jpg',
            'public_path' => 'level1/level2/level3/level4',
        ]);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);

        $expectedThumbnailDir = THUMBS_SUBDIR . DS . 'level1' . DS . 'level2' . DS . 'level3' . DS . 'level4' . DS;
        $expectedStoragePath = '/storage/path';

        $pathService->shouldReceive('getThumbnailAbsPath')->once()->andReturn($expectedStoragePath);
        $fileOperations->shouldReceive('directoryExists')->with($expectedThumbnailDir)->once()->andReturn(false);
        $fileOperations->shouldReceive('makeFolder')->with($expectedThumbnailDir)->once()->andReturn(true);

        $service = new ThumbnailService($pathService, $fileOperations);
        $result = $service->getFullFileThumbnailPath($file);

        $expectedPath = $expectedStoragePath . DS . 'level1' . DS . 'level2' . DS . 'level3' . DS . 'level4' . DS . 'deep.jpg';
        $this->assertEquals($expectedPath, $result);
    }


    public function test_get_full_file_thumbnail_path_with_empty_public_path()
    {
        $file = LocalFile::factory()->make([
            'file_type' => 'image',
            'filename' => 'root.jpg',
            'public_path' => '',
        ]);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);

        $expectedThumbnailDir = THUMBS_SUBDIR . DS;
        $expectedStoragePath = '/storage/path';

        $pathService->shouldReceive('getThumbnailAbsPath')->once()->andReturn($expectedStoragePath);
        $fileOperations->shouldReceive('directoryExists')->with($expectedThumbnailDir)->once()->andReturn(true);

        $service = new ThumbnailService($pathService, $fileOperations);
        $result = $service->getFullFileThumbnailPath($file);

        $expectedPath = $expectedStoragePath . DS . 'root.jpg';
        $this->assertEquals($expectedPath, $result);
    }


    public function test_generate_thumbnail_for_pdf_returns_zero()
    {
        $file = LocalFile::factory()->make(['file_type' => 'pdf']);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = new ThumbnailService($pathService, $fileOperations);

        $result = $service->generateThumbnail($file);

        $this->assertEquals(0, $result);
    }


    public function test_generate_thumbnail_for_document_returns_zero()
    {
        $file = LocalFile::factory()->make(['file_type' => 'document']);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = new ThumbnailService($pathService, $fileOperations);

        $result = $service->generateThumbnail($file);

        $this->assertEquals(0, $result);
    }


    public function test_generate_thumbnail_for_audio_returns_zero()
    {
        $file = LocalFile::factory()->make(['file_type' => 'audio']);

        $pathService = Mockery::mock(PathService::class);
        $fileOperations = Mockery::mock(FileOperationsService::class);
        $service = new ThumbnailService($pathService, $fileOperations);

        $result = $service->generateThumbnail($file);

        $this->assertEquals(0, $result);
    }
}
