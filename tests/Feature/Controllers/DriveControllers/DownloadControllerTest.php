<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use Tests\Feature\BaseFeatureTest;

class DownloadControllerTest extends BaseFeatureTest
{
    public $fileNames = [
        'ace.txt', 'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/bar/1.txt'
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadMultipleFiles('');
    }

    public function test_index_downloads_single_file_successfully(): void
    {
        $firstFile = LocalFile::getByName('ace.txt')->firstOrFail();

        $response = $this->post('/download-files', [
            'fileList' => [$firstFile->id],
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=ace.txt');
    }

    public function test_index_fails_with_non_existent_id(): void
    {
        $firstFile = LocalFile::getByName('ace.txt')->firstOrFail();

        $response = $this->post('/download-files', [
            'fileList' => ["01kd2195rfbxe1pbavxwefk9wt"],
        ]);
        $response->assertJson([
            'status' => false,
            'message' => 'Could not find files to download',
        ]);
    }

    public function test_index_downloads_multiple_files_as_zip(): void
    {
        $fileIds = LocalFile::all()->slice(0,2)->pluck('id')->toArray();

        $response = $this->post('/download-files', [
            'fileList' => $fileIds,
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('.zip', $response->headers->get('Content-Disposition'));
    }
}