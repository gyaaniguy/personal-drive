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
        $file = LocalFile::factory()->make([
            'file_type' => 'image',
        ]);
        $file1 = LocalFile::factory()->make([
            'file_type' => 'pdf',
        ]);
        $pathService = Mockery::mock(PathService::class);
        $uploadService = Mockery::mock(FileOperationsService::class);
        $service = Mockery::mock(ThumbnailService::class, [$pathService, $uploadService])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('generateImageThumbnail')->with($file)->andReturn(true);

        $result = $service->generateThumbnailsForFiles(collect([$file, $file1]));

        $this->assertEquals(1, $result);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
