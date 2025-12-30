<?php

namespace Feature\Controllers\DriveControllers;

use App\Http\Controllers\DriveControllers\FileFetchController;
use App\Models\LocalFile;
use App\Services\DownloadService;
use App\Services\LocalFileStatsService;
use App\Services\ThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\Feature\BaseFeatureTest;

class ThumbnailControllerTest extends BaseFeatureTest
{
    use RefreshDatabase;

    private $fileName = 'pic.png';
    private $videoFileName = 'vid.mp4';

    public function test_update_thumb_fail()
    {
        $response = $this->post('/gen-thumbs', [
            '_token' => csrf_token(),
            'ids' => [(string) Str::ulid()],
        ]);
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'No thumbnails generated. No valid files found');
    }
    public function test_get_thumb_fail()
    {
        $response = $this->get(route('drive.get-thumb', ['id' => (string) Str::ulid()]));
        $response->assertStatus(302);
        $response->assertRedirect(route('rejected', [
            'message' => 'Could not find file to send'
        ]));
    }

    public function test_get_thumb_success()
    {
        $id = LocalFile::getByName($this->fileName)->id;
        $response = $this->post('/gen-thumbs', [
            '_token' => csrf_token(),
            'ids' => [$id],
        ]);
        $response->assertRedirect(route('drive'));
        $mock = Mockery::mock(FileFetchController::class . '[streamFile]', [
            app(LocalFileStatsService::class),
            app(ThumbnailService::class),
            app(DownloadService::class),
        ]);

        $mock->shouldReceive('streamFile')
            ->once()
            ->withAnyArgs();

        $this->app->instance(FileFetchController::class, $mock);

        $this->get(route('drive.get-thumb', ['id' => $id]));
    }

    public function test_get_thumb_no_has_thumbnail()
    {
        $res = $this->post('/resync', [
            '_token' => csrf_token(),
        ]);

        $response = $this->get(route('drive.get-thumb', ['id' => LocalFile::getByName($this->fileName)]));
        $response->assertRedirect(route('rejected', [
            'message' => 'Could not find thumbnail. Try Resync in settings'
        ]));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadImage($this->fileName);
    }
}
