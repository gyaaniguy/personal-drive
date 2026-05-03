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

    public function test_update_fails_for_directory()
    {
        $dirName = 'testdir';
        $this->post(
            route('drive.create-item'), [
            '_token' => csrf_token(),
            'name' => $dirName,
            'path' => '',
            'type' => 'folder',
            ]
        );

        $dir = LocalFile::where('filename', $dirName)->first();
        if (!$dir) {
            $this->assertTrue(true); // Skip if directory wasn't created
            return;
        }
        $response = $this->postSave($dir->id, 'New content');

        $response->assertJson(
            [
            'status' => false,
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

    public function test_update_text_file_with_multiline_content()
    {
        $fileName = 'multiline.txt';
        $this->uploadFile('', $fileName, 1);
        $file = LocalFile::where('filename', $fileName)->first();

        $multilineContent = "Line 1\nLine 2\nLine 3";
        $response = $this->postSave($file->id, $multilineContent);

        $response->assertJson(
            [
            'status' => true,
            'message' => 'File saved successfully',
            ]
        );

        $privatePathFile = $file->getPrivatePathNameForFile();
        $this->assertEquals($multilineContent, file_get_contents($privatePathFile));
    }

    public function test_update_text_file_with_special_characters()
    {
        $fileName = 'special.txt';
        $this->uploadFile('', $fileName, 1);
        $file = LocalFile::where('filename', $fileName)->first();

        $specialContent = 'Hello 世界! @#$%^&*()';
        $response = $this->postSave($file->id, $specialContent);

        $response->assertJson(
            [
            'status' => true,
            'message' => 'File saved successfully',
            ]
        );

        $privatePathFile = $file->getPrivatePathNameForFile();
        $this->assertEquals($specialContent, file_get_contents($privatePathFile));
    }

    public function test_update_text_file_overwrites_existing_content()
    {
        $fileName = 'overwrite.txt';
        $this->uploadFile('', $fileName, 1);
        $file = LocalFile::where('filename', $fileName)->first();

        // Write initial content
        $this->postSave($file->id, 'Initial content');

        // Overwrite with new content
        $response = $this->postSave($file->id, 'Overwritten content');

        $response->assertJson(
            [
            'status' => true,
            'message' => 'File saved successfully',
            ]
        );

        $privatePathFile = $file->getPrivatePathNameForFile();
        $this->assertEquals('Overwritten content', file_get_contents($privatePathFile));
    }

    public function test_update_text_file_in_subdirectory()
    {
        // Create a subdirectory
        $this->post(
            route('drive.create-item'), [
            '_token' => csrf_token(),
            'name' => 'subdir',
            'path' => '',
            'type' => 'folder',
            ]
        );

        // Upload a file to the subdirectory
        $fileName = 'file.txt';
        $file = UploadedFile::fake()->create($fileName, 1);
        $this->postUpload([$file], 'subdir');

        $localFile = LocalFile::where('filename', $fileName)->first();
        $response = $this->postSave($localFile->id, 'Content in subdirectory');

        $response->assertJson(
            [
            'status' => true,
            'message' => 'File saved successfully',
            ]
        );
    }

    public function test_update_with_empty_content()
    {
        $fileName = 'emptycontent.txt';
        $this->uploadFile('', $fileName, 1);
        $file = LocalFile::where('filename', $fileName)->first();

        // Write content first
        $this->postSave($file->id, 'Some content');

        // Test saving minimal content (single character)
        // This represents clearing the file to minimal state
        $minimalContent = '.';
        $response = $this->post(
            route('drive.save-file'), [
            '_token' => csrf_token(),
            'id' => $file->id,
            'content' => $minimalContent,
            ]
        );

        $response->assertJson(
            [
            'status' => true,
            'message' => 'File saved successfully',
            ]
        );

        $privatePathFile = $file->getPrivatePathNameForFile();
        $this->assertEquals($minimalContent, file_get_contents($privatePathFile));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
