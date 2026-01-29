<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Http\Controllers\DriveControllers\FileFetchController;
use App\Models\LocalFile;
use App\Services\LocalFileStatsService;
use App\Services\ThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\Feature\BaseFeatureTest;

class FileFetchControllerTest extends BaseFeatureTest
{
    use RefreshDatabase;
    private $textFile = 'pic.png';



    public function test_index_streams_text_file()
    {
        $this->uploadFile('', 'sample.txt');
        $file = LocalFile::getByName('sample.txt');
        $file->file_type = 'text';
        $file->save();

        $response = $this->get(route('drive.fetch-file', ['id' => $file->id]));
        $response->assertOk();
    }

    public function test_index_fail()
    {
        $response = $this->get(route('drive.fetch-file', ['id' => (string)Str::ulid()]));
        $response->assertRedirect(
            route(
                'rejected', [
                'message' => 'Could not find file to send'
                ]
            )
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
