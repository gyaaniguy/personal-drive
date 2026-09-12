<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Http\Controllers\DriveControllers\FileFetchController;
use App\Models\LocalFile;
use App\Services\LocalFileStatsService;
use App\Services\ShareAuthorizationService;
use App\Services\ThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertNull($response->headers->get('Content-Disposition'));
    }

    public function test_index_streams_image_inline_with_nosniff()
    {
        $this->postUpload([UploadedFile::fake()->image('pic.png')], '');
        $file = LocalFile::getByName('pic.png');
        $this->assertSame('image', $file->file_type);

        $this->mockStreamFile();

        $response = $this->get(route('drive.fetch-file', ['id' => $file->id]));
        $response->assertOk();
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertNull($response->headers->get('Content-Disposition'));
    }

    public function test_index_forces_attachment_for_svg_image()
    {
        $file = UploadedFile::fake()->createWithContent(
            'evil.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );
        $this->postUpload([$file], '');
        $localFile = LocalFile::getByName('evil.svg');
        $this->assertSame('image', $localFile->file_type);

        $this->mockStreamFile();

        $response = $this->get(route('drive.fetch-file', ['id' => $localFile->id]));
        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_index_sandboxes_html_inline()
    {
        $file = UploadedFile::fake()->createWithContent(
            'evil.html',
            '<html><script>alert(1)</script></html>'
        );
        $this->postUpload([$file], '');
        $localFile = LocalFile::getByName('evil.html');
        $this->assertSame('html', $localFile->file_type);

        $this->mockStreamFile();

        $response = $this->get(route('drive.fetch-file', ['id' => $localFile->id]));
        $response->assertOk();
        $this->assertSame('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    /**
     * VideoStreamer::streamFile() exits the process after echoing, so the
     * real stream cannot run inside a test. Mock it; the controller still
     * sets its headers on the returned response before streaming.
     */
    private function mockStreamFile(): void
    {
        $mock = Mockery::mock(
            FileFetchController::class.'[streamFile]', [
                app(LocalFileStatsService::class),
                app(ThumbnailService::class),
                app(ShareAuthorizationService::class),
            ]
        );

        $mock->shouldReceive('streamFile')
            ->once()
            ->withAnyArgs();

        $this->app->instance(FileFetchController::class, $mock);
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

    public function test_index_fails_when_file_is_missing_from_storage()
    {
        $this->uploadFile('', 'sample.txt');
        $file = LocalFile::getByName('sample.txt');

        unlink($file->getPrivatePathNameForFile());

        $response = $this->get(route('drive.fetch-file', ['id' => $file->id]));

        $response->assertRedirect(
            route('rejected', ['message' => 'Could not find file to send'])
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
