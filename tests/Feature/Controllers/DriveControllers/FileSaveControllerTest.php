<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\BaseFeatureTest;

class FileSaveControllerTest extends BaseFeatureTest
{
    use RefreshDatabase;

    public function test_update_fails_not_editable_type()
    {
        $fileName = 'note.jpg';
        $file = UploadedFile::fake()->image($fileName);
        $this->postUpload([$file], '');
        $file = LocalFile::where('filename', $fileName)->first();
        $response = $this->postSave($file->id, 'New content');

        $response->assertExactJson(
            [
            'status' => false,
            'message' => 'File is not a text file',
            ]
        );
    }

    private function postSave(string $id, string $content): TestResponse
    {
        $response = $this->post(
            route('drive.save-file'), [
            '_token' => csrf_token(),
            'id' => $id,
            'content' => $content,
            ]
        );
        return $response;
    }

    public function test_update_fails_when_file_not_found()
    {
        $response = $this->postSave((string) Str::ulid(), 'Original content');

        $response->assertExactJson(
            [
            'status' => false,
            'message' => 'Could not find file',
            ]
        );
    }

    public function test_update_succeeds_for_text_file()
    {
        $fileName = 'note.txt';
        $this->uploadFile('', $fileName, 1);
        $file = LocalFile::where('filename', $fileName)->first();
        $privatePathFile = $file->getPrivatePathNameForFile();
        $this->assertEquals('', file_get_contents($privatePathFile));
        $response = $this->postSave($file->id, 'New content');
        $response->assertExactJson(
            [
            'status' => true,
            'message' => 'File saved successfully',
            ]
        );
        $this->assertEquals('New content', file_get_contents($privatePathFile));
    }

    public function test_update_succeeds_for_empty_file()
    {
        $fileName = 'empty.txt';
        $this->uploadFile('', $fileName, 0);
        $file = LocalFile::where('filename', $fileName)->first();
        $file->file_type = 'empty';
        $file->save();

        $response = $this->postJson(
            route('drive.save-file'), [
            '_token' => csrf_token(),
            'id' => (string) $file->id,
            'content' => 'Initial content',
            ]
        );

        $response->assertJson(
            [
            'status' => true,
            'message' => 'File saved successfully',
            ]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
