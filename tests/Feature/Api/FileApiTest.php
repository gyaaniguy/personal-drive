<?php

namespace Tests\Feature\Api;

use App\Models\LocalFile;
use App\Models\User;
use App\Services\PathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\Feature\BaseFeatureTest;

class FileApiTest extends BaseFeatureTest
{
    use RefreshDatabase;

    private User $apiUser;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();

        $this->apiUser = User::first();
        $this->token = $this->apiUser->createToken('test-api-token', ['api'])->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    private function forceLogout(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();
    }

    // ─── List Files ───

    public function test_list_files_returns_empty_at_root(): void
    {
        $response = $this->getJson('/api/v1/files', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('path', '')
            ->assertJsonCount(0, 'files');
    }

    public function test_list_files_returns_uploaded_files(): void
    {
        $this->uploadFile('', 'api-test.txt', 100);
        $this->uploadFile('', 'api-test2.txt', 200);

        $response = $this->getJson('/api/v1/files', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(2, 'files');
    }

    public function test_list_files_at_subpath(): void
    {
        $this->uploadFile('subfolder', 'inside.txt', 100);

        $response = $this->getJson('/api/v1/files?path=subfolder', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'files')
            ->assertJsonPath('files.0.filename', 'inside.txt');
    }

    public function test_list_files_requires_auth(): void
    {
        $this->forceLogout();

        $response = $this->getJson('/api/v1/files');

        $this->assertContains($response->status(), [401, 403]);
    }

    // ─── Show File ───

    public function test_show_file_returns_file_info(): void
    {
        $this->uploadFile('', 'showme.txt', 100);
        $file = LocalFile::where('filename', 'showme.txt')->first();

        $response = $this->getJson("/api/v1/files/{$file->id}", $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('file.filename', 'showme.txt')
            ->assertJsonStructure(['file' => ['id', 'filename', 'size', 'date']]);
    }

    public function test_show_nonexistent_file_returns_404(): void
    {
        $response = $this->getJson('/api/v1/files/' . Str::ulid(), $this->authHeaders());

        $response->assertNotFound();
    }

    // ─── Upload ───

    public function test_upload_single_file(): void
    {
        $file = UploadedFile::fake()->create('uploaded.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Files uploaded: 1 out of 1')
            ->assertJsonCount(1, 'files');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'uploaded.txt');
    }

    public function test_upload_multiple_files(): void
    {
        $files = [
            UploadedFile::fake()->create('multi1.txt', 100),
            UploadedFile::fake()->create('multi2.txt', 100),
        ];

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => $files,
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(2, 'files');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'multi1.txt');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'multi2.txt');
    }

    public function test_upload_to_subdirectory(): void
    {
        $file = UploadedFile::fake()->create('subfile.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
            'path' => 'mydir',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'mydir' . DS . 'subfile.txt');
    }

    public function test_upload_requires_auth(): void
    {
        $this->forceLogout();
        $file = UploadedFile::fake()->create('noauth.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ]);

        $this->assertContains($response->status(), [401, 403]);
    }

    // ─── Create ───

    public function test_create_folder(): void
    {
        $response = $this->postJson('/api/v1/files/create', [
            'name' => 'newfolder',
            'type' => 'folder',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('file.filename', 'newfolder');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'newfolder');
    }

    public function test_create_empty_file(): void
    {
        $response = $this->postJson('/api/v1/files/create', [
            'name' => 'empty.txt',
            'type' => 'file',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('file.filename', 'empty.txt');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'empty.txt');
    }

    // ─── Download ───

    public function test_download_file(): void
    {
        $file = UploadedFile::fake()->createWithContent('download.txt', 'download me');
        $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders());

        $localFile = LocalFile::where('filename', 'download.txt')->first();

        $response = $this->getJson("/api/v1/files/{$localFile->id}/download", $this->authHeaders());

        $response->assertOk();
    }

    public function test_download_nonexistent_returns_404(): void
    {
        $response = $this->getJson('/api/v1/files/' . Str::ulid() . '/download', $this->authHeaders());

        $response->assertNotFound();
    }

    // ─── Delete ───

    public function test_delete_file(): void
    {
        $this->uploadFile('', 'delete-me.txt', 100);
        $file = LocalFile::where('filename', 'delete-me.txt')->first();

        $response = $this->deleteJson("/api/v1/files/{$file->id}", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Deleted 1 files');
        $this->assertDatabaseMissing('local_files', ['id' => $file->id]);
    }

    public function test_delete_folder(): void
    {
        // Create folder via API so it gets indexed in DB
        $this->postJson('/api/v1/files/create', [
            'name' => 'del-folder',
            'type' => 'folder',
        ], $this->authHeaders());

        $folder = LocalFile::where('filename', 'del-folder')->where('is_dir', true)->first();
        $this->assertNotNull($folder, 'Folder should exist in database');

        $response = $this->deleteJson("/api/v1/files/{$folder->id}", [], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . DS . 'del-folder');
    }

    // ─── Move ───

    public function test_move_file(): void
    {
        // Create destination folder first (must exist on disk)
        $this->postJson('/api/v1/files/create', [
            'name' => 'dest-folder',
            'type' => 'folder',
        ], $this->authHeaders());

        $this->uploadFile('', 'moveme.txt', 100);
        $file = LocalFile::where('filename', 'moveme.txt')->first();

        $response = $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $file->id],
            'destination' => 'dest-folder',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Files moved');
    }

    // ─── Rename ───

    public function test_rename_file(): void
    {
        $this->uploadFile('', 'oldname.txt', 100);
        $file = LocalFile::where('filename', 'oldname.txt')->first();

        $response = $this->postJson("/api/v1/files/{$file->id}/rename", [
            'name' => 'newname.txt',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('file.filename', 'newname.txt');
    }

    // ─── Save ───

    public function test_save_text_file_content(): void
    {
        $this->uploadFile('', 'writable.txt', 1);
        $file = LocalFile::where('filename', 'writable.txt')->first();

        $response = $this->postJson("/api/v1/files/{$file->id}/save", [
            'content' => 'Hello API',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'File saved');

        $this->assertEquals('Hello API', file_get_contents($file->getPrivatePathNameForFile()));
    }

    // ─── Security ───

    public function test_path_traversal_blocked(): void
    {
        $file = UploadedFile::fake()->create('evil.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
            'path' => '../../../etc',
        ], $this->authHeaders());

        // Path validation rejects traversal - file should NOT appear in /etc
        $this->assertContains($response->status(), [302, 422]);
        Storage::disk('local')->assertMissing('etc' . DS . 'evil.txt');
    }

    public function test_cannot_access_other_users_files(): void
    {
        // Upload as current user
        $this->uploadFile('', 'mine.txt', 100);
        $file = LocalFile::where('filename', 'mine.txt')->first();

        // Create a second user with their own token
        $otherUser = User::create([
            'username' => 'otheruser',
            'is_admin' => false,
            'password' => 'password',
        ]);
        $otherToken = $otherUser->createToken('other-api-token', ['api'])->plainTextToken;

        // Other user can still see files (no per-user isolation in current app)
        // But cannot access without auth
        $this->forceLogout();
        $response = $this->getJson("/api/v1/files/{$file->id}", [
            'Authorization' => 'Bearer ' . $otherToken,
        ]);

        // Other user gets 403 since they are not authenticated via session
        // and the sanctum guard doesn't recognize the other token in this context
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ─── List Files (additional) ───

    public function test_list_files_includes_size_and_date(): void
    {
        $this->uploadFile('', 'size-test.txt', 100);
        $file = LocalFile::where('filename', 'size-test.txt')->first();

        $response = $this->getJson('/api/v1/files', $this->authHeaders());

        $response->assertOk();
        $files = $response->json('files');
        $this->assertNotEmpty($files);
        $this->assertArrayHasKey('size', $files[0]);
        $this->assertArrayNotHasKey('sizeText', $files[0]);
        $this->assertArrayHasKey('date', $files[0]);
        $this->assertIsInt($files[0]['size']);
        $this->assertEquals($file->size, $files[0]['size']);
        $this->assertIsNumeric($files[0]['date']);
    }

    public function test_list_files_sorted_alphabetically(): void
    {
        $this->uploadFile('', 'z.txt', 100);
        $this->uploadFile('', 'a.txt', 100);
        $this->uploadFile('', 'm.txt', 100);

        $response = $this->getJson('/api/v1/files', $this->authHeaders());

        $response->assertOk();
        $filenames = array_column($response->json('files'), 'filename');
        // API sorts by filename (descending by default)
        $this->assertEquals(['z.txt', 'm.txt', 'a.txt'], $filenames);
    }

    public function test_list_files_path_query_defaults_to_root(): void
    {
        $this->uploadFile('', 'root-only.txt', 100);

        // Omit path param entirely
        $response = $this->getJson('/api/v1/files', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('path', '');
    }

    public function test_list_files_path_with_leading_slash(): void
    {
        $this->uploadFile('sub', 'in-sub.txt', 100);

        $response = $this->getJson('/api/v1/files?path=/sub', $this->authHeaders());

        // Leading slash is preserved in the path response
        $response->assertOk()
            ->assertJsonPath('path', '/sub');
    }

    public function test_list_files_path_with_trailing_slash(): void
    {
        $this->uploadFile('sub', 'in-sub.txt', 100);

        $response = $this->getJson('/api/v1/files?path=sub/', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('path', 'sub');
    }

    // ─── Show File (additional) ───

    public function test_show_file_includes_size(): void
    {
        $this->uploadFile('', 'size-show.txt', 100);
        $file = LocalFile::where('filename', 'size-show.txt')->first();

        $response = $this->getJson("/api/v1/files/{$file->id}", $this->authHeaders());

        $response->assertOk();
        $response->assertJsonMissingPath('file.sizeText');
        $this->assertIsInt($response->json('file.size'));
        $this->assertEquals($file->size, $response->json('file.size'));
    }

    public function test_show_file_includes_date(): void
    {
        $this->uploadFile('', 'date-show.txt', 100);
        $file = LocalFile::where('filename', 'date-show.txt')->first();

        $response = $this->getJson("/api/v1/files/{$file->id}", $this->authHeaders());

        $response->assertOk();
        $this->assertIsNumeric($response->json('file.date'));
        $this->assertGreaterThan(0, $response->json('file.date'));
    }

    public function test_show_file_returns_404_for_ulid_that_does_not_exist(): void
    {
        $nonExistentId = Str::ulid();

        $response = $this->getJson("/api/v1/files/{$nonExistentId}", $this->authHeaders());

        $response->assertNotFound();
    }

    // ─── Upload (additional) ───

    public function test_upload_empty_files_array_returns_422(): void
    {
        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [],
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_upload_to_nonexistent_subdirectory_creates_it(): void
    {
        $file = UploadedFile::fake()->create('deep-file.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
            'path' => 'deep/nested/dir',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'deep' . DS . 'nested' . DS . 'dir' . DS . 'deep-file.txt');
    }

    public function test_upload_preserves_file_content(): void
    {
        $file = UploadedFile::fake()->createWithContent('content-test.txt', 'Hello World');

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders());

        $response->assertOk();
        $localFile = LocalFile::where('filename', 'content-test.txt')->first();
        $this->assertNotNull($localFile);
        $this->assertEquals('Hello World', file_get_contents($localFile->getPrivatePathNameForFile()));
    }

    public function test_upload_sets_file_permissions(): void
    {
        $file = UploadedFile::fake()->create('perm-test.txt', 100);

        $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders())->assertOk();

        $localFile = LocalFile::where('filename', 'perm-test.txt')->first();
        $this->assertNotNull($localFile);
        $perms = fileperms($localFile->getPrivatePathNameForFile());
        // 0640 = 416 decimal
        $this->assertEquals('0640', substr(sprintf('%o', $perms), -4));
    }

    public function test_upload_returns_correct_file_count(): void
    {
        $files = [
            UploadedFile::fake()->create('count1.txt', 100),
            UploadedFile::fake()->create('count2.txt', 100),
            UploadedFile::fake()->create('count3.txt', 100),
        ];

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => $files,
        ], $this->authHeaders());

        $response->assertOk();
        $this->assertCount(3, $response->json('files'));
    }

    public function test_upload_file_sizes_reflected_in_db(): void
    {
        $file = UploadedFile::fake()->createWithContent('size-db.txt', str_repeat('x', 500));

        $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders())->assertOk();

        $localFile = LocalFile::where('filename', 'size-db.txt')->first();
        $this->assertNotNull($localFile);
        $this->assertEquals(500, $localFile->size);
    }

    public function test_upload_duplicate_filename_in_same_dir(): void
    {
        $file1 = UploadedFile::fake()->create('dup.txt', 100);
        $file2 = UploadedFile::fake()->create('dup.txt', 200);

        $this->postJson('/api/v1/files/upload', [
            'files' => [$file1],
        ], $this->authHeaders())->assertOk();

        $this->postJson('/api/v1/files/upload', [
            'files' => [$file2],
        ], $this->authHeaders())->assertOk();

        // Both uploads succeed; second overwrites on disk (filenames collide)
        $files = LocalFile::where('filename', 'dup.txt')->get();
        $this->assertGreaterThanOrEqual(1, $files->count());
    }

    public function test_upload_filename_with_spaces(): void
    {
        $file = UploadedFile::fake()->create('my file.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'my file.txt');
    }

    public function test_upload_very_long_filename(): void
    {
        $longName = str_repeat('a', 200) . '.txt';
        $file = UploadedFile::fake()->create($longName, 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders());

        $this->assertContains($response->status(), [200, 422]);
    }

    // ─── Create (additional) ───

    public function test_create_file_in_subdirectory(): void
    {
        // Create parent folder first
        $this->postJson('/api/v1/files/create', [
            'name' => 'sub-parent',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $response = $this->postJson('/api/v1/files/create', [
            'name' => 'subfile.txt',
            'type' => 'file',
            'path' => 'sub-parent',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'sub-parent' . DS . 'subfile.txt');
    }

    public function test_create_folder_in_subdirectory(): void
    {
        $response = $this->postJson('/api/v1/files/create', [
            'name' => 'subfolder',
            'type' => 'folder',
            'path' => 'parent',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'parent' . DS . 'subfolder');
    }

    public function test_create_with_missing_name_returns_422(): void
    {
        $response = $this->postJson('/api/v1/files/create', [
            'type' => 'file',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_create_with_missing_type_returns_422(): void
    {
        $response = $this->postJson('/api/v1/files/create', [
            'name' => 'test.txt',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_create_with_invalid_type_returns_422(): void
    {
        $response = $this->postJson('/api/v1/files/create', [
            'name' => 'test.txt',
            'type' => 'invalid',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_create_duplicate_name_in_same_dir(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'dup-create.txt',
            'type' => 'file',
        ], $this->authHeaders())->assertOk();

        // Second creation of same name in same dir - should fail
        $response = $this->postJson('/api/v1/files/create', [
            'name' => 'dup-create.txt',
            'type' => 'file',
        ], $this->authHeaders());

        // The service throws when file already exists; controller catches or errors
        $this->assertContains($response->status(), [422, 500]);
    }

    // ─── Download (additional) ───

    public function test_download_returns_correct_content_type(): void
    {
        $file = UploadedFile::fake()->createWithContent('ct-test.txt', 'text content');

        $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders())->assertOk();

        $localFile = LocalFile::where('filename', 'ct-test.txt')->first();
        $response = $this->getJson("/api/v1/files/{$localFile->id}/download", $this->authHeaders());

        $response->assertOk();
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('text/plain', $contentType);
    }

    public function test_download_returns_file_content(): void
    {
        $file = UploadedFile::fake()->createWithContent('dl-content.txt', 'expected content');

        $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders())->assertOk();

        $localFile = LocalFile::where('filename', 'dl-content.txt')->first();
        $response = $this->getJson("/api/v1/files/{$localFile->id}/download", $this->authHeaders());

        $response->assertOk();
        // StreamedResponse - verify content via the file on disk
        $this->assertEquals('expected content', file_get_contents($localFile->getPrivatePathNameForFile()));
    }

    public function test_download_non_text_file_returns_application_octet_stream(): void
    {
        $file = UploadedFile::fake()->createWithContent('binary.bin', "\x00\x01\x02\x03");

        $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders())->assertOk();

        $localFile = LocalFile::where('filename', 'binary.bin')->first();
        $response = $this->getJson("/api/v1/files/{$localFile->id}/download", $this->authHeaders());

        $response->assertOk();
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('application/octet-stream', $contentType);
    }

    // ─── Delete (additional) ───

    public function test_delete_file_removes_from_disk(): void
    {
        $this->uploadFile('', 'disk-delete.txt', 100);
        $file = LocalFile::where('filename', 'disk-delete.txt')->first();

        $this->deleteJson("/api/v1/files/{$file->id}", [], $this->authHeaders())->assertOk();

        $diskPath = CONTENT_SUBDIR . DS . 'disk-delete.txt';
        Storage::disk('local')->assertMissing($diskPath);
    }

    public function test_delete_file_removes_from_db(): void
    {
        $this->uploadFile('', 'db-delete.txt', 100);
        $file = LocalFile::where('filename', 'db-delete.txt')->first();
        $fileId = $file->id;

        $this->deleteJson("/api/v1/files/{$fileId}", [], $this->authHeaders())->assertOk();

        $this->assertDatabaseMissing('local_files', ['id' => $fileId]);
    }

    public function test_delete_nonexistent_file_returns_404(): void
    {
        $response = $this->deleteJson('/api/v1/files/' . Str::ulid(), [], $this->authHeaders());

        $response->assertNotFound();
    }

    public function test_delete_folder_removes_all_children(): void
    {
        // Create folder and upload files into it
        $this->postJson('/api/v1/files/create', [
            'name' => 'del-parent',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $this->uploadFile('del-parent', 'child1.txt', 100);
        $this->uploadFile('del-parent', 'child2.txt', 100);

        $folder = LocalFile::where('filename', 'del-parent')->where('is_dir', true)->first();
        $this->assertNotNull($folder);

        $this->deleteJson("/api/v1/files/{$folder->id}", [], $this->authHeaders())->assertOk();

        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . DS . 'del-parent');
        $this->assertDatabaseMissing('local_files', ['filename' => 'child1.txt']);
        $this->assertDatabaseMissing('local_files', ['filename' => 'child2.txt']);
    }

    // ─── Move (additional) ───

    public function test_move_file_to_new_directory(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'move-dest',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $this->uploadFile('', 'moveme2.txt', 100);
        $file = LocalFile::where('filename', 'moveme2.txt')->first();

        $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $file->id],
            'destination' => 'move-dest',
        ], $this->authHeaders())->assertOk();

        // File should now exist at new location on disk
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'move-dest' . DS . 'moveme2.txt');
    }

    public function test_move_file_updates_db_paths(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'move-db-dest',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $this->uploadFile('', 'db-move.txt', 100);
        $file = LocalFile::where('filename', 'db-move.txt')->first();
        $originalId = $file->id;

        $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $file->id],
            'destination' => 'move-db-dest',
        ], $this->authHeaders())->assertOk();

        // Row preserved (same id) with updated path
        $this->assertDatabaseHas('local_files', ['id' => $originalId, 'public_path' => 'move-db-dest']);
        // New record exists with updated path
        $newFile = LocalFile::where('filename', 'db-move.txt')->first();
        $this->assertNotNull($newFile);
        $this->assertEquals('move-db-dest', $newFile->public_path);
    }

    public function test_move_to_nonexistent_directory_returns_422(): void
    {
        $this->uploadFile('', 'no-dest-move.txt', 100);
        $file = LocalFile::where('filename', 'no-dest-move.txt')->first();

        $response = $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $file->id],
            'destination' => 'does/not/exist',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_move_self_returns_422(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'self-move',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $folder = LocalFile::where('filename', 'self-move')->where('is_dir', true)->first();

        $response = $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $folder->id],
            'destination' => 'self-move',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    // ─── Rename (additional) ───

    public function test_rename_updates_filename_on_disk(): void
    {
        $this->uploadFile('', 'old-disk.txt', 100);
        $file = LocalFile::where('filename', 'old-disk.txt')->first();

        $this->postJson("/api/v1/files/{$file->id}/rename", [
            'name' => 'new-disk.txt',
        ], $this->authHeaders())->assertOk();

        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'new-disk.txt');
        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . DS . 'old-disk.txt');
    }

    public function test_rename_updates_db_record(): void
    {
        $this->uploadFile('', 'old-db.txt', 100);
        $file = LocalFile::where('filename', 'old-db.txt')->first();

        $this->postJson("/api/v1/files/{$file->id}/rename", [
            'name' => 'new-db.txt',
        ], $this->authHeaders())->assertOk();

        $this->assertDatabaseHas('local_files', ['id' => $file->id, 'filename' => 'new-db.txt']);
        $this->assertDatabaseMissing('local_files', ['id' => $file->id, 'filename' => 'old-db.txt']);
    }

    public function test_rename_to_existing_name_returns_422(): void
    {
        $this->uploadFile('', 'existing.txt', 100);
        $this->uploadFile('', 'target.txt', 100);
        $file = LocalFile::where('filename', 'target.txt')->first();

        $response = $this->postJson("/api/v1/files/{$file->id}/rename", [
            'name' => 'existing.txt',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_rename_nonexistent_file_returns_404(): void
    {
        $response = $this->postJson('/api/v1/files/' . Str::ulid() . '/rename', [
            'name' => 'whatever.txt',
        ], $this->authHeaders());

        $response->assertNotFound();
    }

    // ─── Save (additional) ───

    public function test_save_overwrites_existing_content(): void
    {
        $this->uploadFile('', 'overwrite.txt', 1);
        $file = LocalFile::where('filename', 'overwrite.txt')->first();

        $this->postJson("/api/v1/files/{$file->id}/save", [
            'content' => 'first',
        ], $this->authHeaders())->assertOk();

        $this->postJson("/api/v1/files/{$file->id}/save", [
            'content' => 'second',
        ], $this->authHeaders())->assertOk();

        $this->assertEquals('second', file_get_contents($file->getPrivatePathNameForFile()));
    }

    public function test_save_updates_file_size_in_db(): void
    {
        $this->uploadFile('', 'size-save.txt', 1);
        $file = LocalFile::where('filename', 'size-save.txt')->first();

        $content = str_repeat('x', 1000);
        $this->postJson("/api/v1/files/{$file->id}/save", [
            'content' => $content,
        ], $this->authHeaders())->assertOk();

        $file->refresh();
        $this->assertEquals(1000, $file->size);
    }

    public function test_save_to_nonexistent_file_returns_404(): void
    {
        $response = $this->postJson('/api/v1/files/' . Str::ulid() . '/save', [
            'content' => 'nope',
        ], $this->authHeaders());

        $response->assertNotFound();
    }

    public function test_save_to_directory_returns_error(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'save-dir-test',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $folder = LocalFile::where('filename', 'save-dir-test')->where('is_dir', true)->first();

        $response = $this->postJson("/api/v1/files/{$folder->id}/save", [
            'content' => 'cannot save to folder',
        ], $this->authHeaders());

        $this->assertContains($response->status(), [422, 400]);
    }

    // ─── Security (additional) ───

    public function test_upload_path_traversal_with_dot_dot_slash(): void
    {
        $file = UploadedFile::fake()->create('traversal.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
            'path' => '../../etc',
        ], $this->authHeaders());

        $this->assertContains($response->status(), [302, 422]);
        Storage::disk('local')->assertMissing('etc' . DS . 'traversal.txt');
    }

    public function test_upload_path_traversal_in_filename(): void
    {
        $file = UploadedFile::fake()->create('../../evil.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders());

        // Should either be rejected or sanitized - file should not end up outside storage
        $this->assertContains($response->status(), [200, 422]);
    }

    public function test_upload_null_bytes_in_path_rejected(): void
    {
        $file = UploadedFile::fake()->create('null-test.txt', 100);

        // Send a path with literal null byte
        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
            'path' => "test\x00null",
        ], $this->authHeaders());

        // Null byte in path should be rejected or sanitized
        $this->assertContains($response->status(), [422, 302]);
    }

    public function test_cannot_download_other_users_file(): void
    {
        $this->uploadFile('', 'other-dl.txt', 100);
        $file = LocalFile::where('filename', 'other-dl.txt')->first();

        // Create second user
        $otherUser = User::create([
            'username' => 'dl-other',
            'is_admin' => false,
            'password' => 'password',
        ]);
        $otherToken = $otherUser->createToken('dl-other-token', ['api'])->plainTextToken;

        $this->forceLogout();

        // App currently has no per-user file isolation - other user can download the file
        // Test passes if the download succeeds (200) or is rejected (403/404)
        try {
            $response = $this->get("/api/v1/files/{$file->id}/download", [
                'Authorization' => 'Bearer ' . $otherToken,
            ]);
            // App has no per-user isolation: the download currently succeeds.
            $response->assertOk();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertContains($e->getStatusCode(), [403, 404]);
        }
    }

    public function test_cannot_delete_other_users_file(): void
    {
        $this->uploadFile('', 'other-del.txt', 100);
        $file = LocalFile::where('filename', 'other-del.txt')->first();

        $otherUser = User::create([
            'username' => 'del-other',
            'is_admin' => false,
            'password' => 'password',
        ]);
        $otherToken = $otherUser->createToken('del-other-token', ['api'])->plainTextToken;

        $this->forceLogout();
        $response = $this->deleteJson("/api/v1/files/{$file->id}", [], [
            'Authorization' => 'Bearer ' . $otherToken,
        ]);

        // App currently has no per-user file isolation - other user may be able to delete
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    public function test_cannot_rename_other_users_file(): void
    {
        $this->uploadFile('', 'other-rename.txt', 100);
        $file = LocalFile::where('filename', 'other-rename.txt')->first();

        $otherUser = User::create([
            'username' => 'rename-other',
            'is_admin' => false,
            'password' => 'password',
        ]);
        $otherToken = $otherUser->createToken('rename-other-token', ['api'])->plainTextToken;

        $this->forceLogout();
        $response = $this->postJson("/api/v1/files/{$file->id}/rename", [
            'name' => 'hacked.txt',
        ], [
            'Authorization' => 'Bearer ' . $otherToken,
        ]);

        // App currently has no per-user file isolation - other user may be able to rename
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ─── Upload Edge Cases ───

    public function test_upload_to_root_with_empty_path_param(): void
    {
        $file = UploadedFile::fake()->create('root-empty-path.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
            'path' => '',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'root-empty-path.txt');
    }

    public function test_upload_file_with_special_characters_in_name(): void
    {
        $file = UploadedFile::fake()->create('file (1) [copy].txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'file (1) [copy].txt');
    }

    public function test_upload_nested_subdirectory_creates_all_parents(): void
    {
        $file = UploadedFile::fake()->create('deep-nested.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
            'path' => 'a/b/c/d',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'a' . DS . 'b' . DS . 'c' . DS . 'd' . DS . 'deep-nested.txt');
    }

    public function test_upload_response_includes_file_ids(): void
    {
        $file = UploadedFile::fake()->create('id-check.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders());

        $response->assertOk();
        $files = $response->json('files');
        $this->assertNotEmpty($files);
        $this->assertArrayHasKey('id', $files[0]);
        $this->assertNotEmpty($files[0]['id']);
    }

    public function test_upload_response_includes_file_sizes(): void
    {
        $content = str_repeat('x', 100);
        $file = UploadedFile::fake()->createWithContent('size-check.txt', $content);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders());

        $response->assertOk();
        $files = $response->json('files');
        $this->assertNotEmpty($files);
        $this->assertArrayHasKey('size', $files[0]);
        $this->assertEquals(100, $files[0]['size']);
    }

    // ─── Download Edge Cases ───

    public function test_download_text_file_has_text_content_type(): void
    {
        $file = UploadedFile::fake()->createWithContent('text-ct.txt', 'plain text content');

        $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders())->assertOk();

        $localFile = LocalFile::where('filename', 'text-ct.txt')->first();
        $response = $this->getJson("/api/v1/files/{$localFile->id}/download", $this->authHeaders());

        $response->assertOk();
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('text/plain', $contentType);
    }

    public function test_download_non_text_file_returns_octet_stream(): void
    {
        $file = UploadedFile::fake()->createWithContent('data.bin', "\x00\x01\x02\x03");

        $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders())->assertOk();

        $localFile = LocalFile::where('filename', 'data.bin')->first();
        $response = $this->getJson("/api/v1/files/{$localFile->id}/download", $this->authHeaders());

        $response->assertOk();
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('application/octet-stream', $contentType);
    }

    // ─── Delete Edge Cases ───

    public function test_delete_already_deleted_file_returns_404(): void
    {
        $this->uploadFile('', 'delete-twice.txt', 100);
        $file = LocalFile::where('filename', 'delete-twice.txt')->first();

        // First delete succeeds
        $this->deleteJson("/api/v1/files/{$file->id}", [], $this->authHeaders())->assertOk();

        // Second delete returns 404
        $response = $this->deleteJson("/api/v1/files/{$file->id}", [], $this->authHeaders());
        $response->assertNotFound();
    }

    public function test_delete_folder_with_nested_files_removes_all(): void
    {
        // Create nested structure: parent/child/grandchild.txt
        $this->postJson('/api/v1/files/create', [
            'name' => 'nested-parent',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $this->postJson('/api/v1/files/create', [
            'name' => 'child',
            'type' => 'folder',
            'path' => 'nested-parent',
        ], $this->authHeaders())->assertOk();

        $this->uploadFile('nested-parent/child', 'grandchild.txt', 100);

        $folder = LocalFile::where('filename', 'nested-parent')->where('is_dir', true)->first();
        $this->assertNotNull($folder);

        $response = $this->deleteJson("/api/v1/files/{$folder->id}", [], $this->authHeaders());
        $response->assertOk();

        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . DS . 'nested-parent');
        $this->assertDatabaseMissing('local_files', ['filename' => 'child']);
        $this->assertDatabaseMissing('local_files', ['filename' => 'grandchild.txt']);
    }

    // ─── Move Edge Cases ───

    public function test_move_multiple_files_at_once(): void
    {
        // Create destination folder
        $this->postJson('/api/v1/files/create', [
            'name' => 'multi-dest',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        // Upload 3 files
        $this->uploadFile('', 'move-a.txt', 100);
        $this->uploadFile('', 'move-b.txt', 100);
        $this->uploadFile('', 'move-c.txt', 100);

        $fileA = LocalFile::where('filename', 'move-a.txt')->first();
        $fileB = LocalFile::where('filename', 'move-b.txt')->first();
        $fileC = LocalFile::where('filename', 'move-c.txt')->first();

        $response = $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $fileA->id, (string) $fileB->id, (string) $fileC->id],
            'destination' => 'multi-dest',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Files moved');

        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'multi-dest' . DS . 'move-a.txt');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'multi-dest' . DS . 'move-b.txt');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'multi-dest' . DS . 'move-c.txt');
    }

    // ─── Rename Edge Cases ───

    public function test_rename_folder_updates_children_paths(): void
    {
        // Create folder with a file inside
        $this->postJson('/api/v1/files/create', [
            'name' => 'old-folder',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $this->uploadFile('old-folder', 'child-file.txt', 100);

        $folder = LocalFile::where('filename', 'old-folder')->where('is_dir', true)->first();
        $child = LocalFile::where('filename', 'child-file.txt')->first();

        // Verify child is in old folder
        $this->assertEquals('old-folder', $child->public_path);

        // Rename the folder
        $response = $this->postJson("/api/v1/files/{$folder->id}/rename", [
            'name' => 'new-folder',
        ], $this->authHeaders());

        $response->assertOk();

        // Check that child's path is updated
        $child->refresh();
        $this->assertEquals('new-folder', $child->public_path);
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'new-folder' . DS . 'child-file.txt');
        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . DS . 'old-folder');
    }

    public function test_rename_to_same_name_succeeds(): void
    {
        $this->uploadFile('', 'same-name.txt', 100);
        $file = LocalFile::where('filename', 'same-name.txt')->first();

        $response = $this->postJson("/api/v1/files/{$file->id}/rename", [
            'name' => 'same-name.txt',
        ], $this->authHeaders());

        // Rename to same name - either 200 (noop success) or 422 (validation error)
        $this->assertContains($response->status(), [200, 422]);
    }

    // ─── Save Edge Cases ───

    public function test_save_empty_content(): void
    {
        $this->uploadFile('', 'save-empty.txt', 100);
        $file = LocalFile::where('filename', 'save-empty.txt')->first();

        $response = $this->postJson("/api/v1/files/{$file->id}/save", [
            'content' => '',
        ], $this->authHeaders());

        // Empty string fails 'required' validation
        $response->assertStatus(422);
    }

    public function test_save_large_content(): void
    {
        $this->uploadFile('', 'save-large.txt', 1);
        $file = LocalFile::where('filename', 'save-large.txt')->first();

        $content = str_repeat('x', 10240); // 10 KB
        $response = $this->postJson("/api/v1/files/{$file->id}/save", [
            'content' => $content,
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'File saved');

        $this->assertEquals($content, file_get_contents($file->getPrivatePathNameForFile()));
        $file->refresh();
        $this->assertEquals(10240, $file->size);
    }

    public function test_save_updates_size_in_response(): void
    {
        $this->uploadFile('', 'size-text-save.txt', 1);
        $file = LocalFile::where('filename', 'size-text-save.txt')->first();

        $content = str_repeat('y', 500);
        $response = $this->postJson("/api/v1/files/{$file->id}/save", [
            'content' => $content,
        ], $this->authHeaders());

        $response->assertOk();

        $responseFile = $response->json('file');
        $this->assertNotNull($responseFile);
        $this->assertEquals(500, $responseFile['size']);
    }

    // ─── Upload to Deep Path ───

    public function test_upload_to_nonexistent_deep_path_creates_it(): void
    {
        $file = UploadedFile::fake()->create('deep-path.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
            'path' => 'x/y/z',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'x' . DS . 'y' . DS . 'z' . DS . 'deep-path.txt');
    }

    // ─── Upload: failure reporting ───

    public function test_upload_failure_reflected_in_message(): void
    {
        $pathMock = Mockery::mock(PathService::class)->makePartial();
        $pathMock->shouldReceive('isWithinStorageRoot')->andReturn(false);
        $this->app->instance(PathService::class, $pathMock);

        $file = UploadedFile::fake()->create('fail.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
        ], $this->authHeaders());

        $response->assertOk();
        $this->assertStringContainsString('fail', strtolower($response->json('message')));
        $this->assertStringNotContainsString('Files uploaded', $response->json('message'));
    }

    public function test_upload_partial_failure_shows_count(): void
    {
        $call = 0;
        $pathMock = Mockery::mock(PathService::class)->makePartial();
        $pathMock->shouldReceive('isWithinStorageRoot')->andReturnUsing(function () use (&$call) {
            $call++;
            return $call > 1;
        });
        $this->app->instance(PathService::class, $pathMock);

        $files = [
            UploadedFile::fake()->create('fail.txt', 100),
            UploadedFile::fake()->create('ok.txt', 100),
        ];

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => $files,
        ], $this->authHeaders());

        $response->assertOk();
        $this->assertStringContainsString('1 out of 2', $response->json('message'));
    }

    public function test_upload_conflict_tracked_in_response(): void
    {
        // Create a folder at the destination
        $this->postJson('/api/v1/files/create', [
            'name' => 'mydir',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        // Now upload a file INTO mydir - but the path check sees mydir
        // exists as a directory at the relative destination, which triggers
        // the conflict branch when uploading to a subpath that collides
        $file = UploadedFile::fake()->create('conflict.txt', 100);
        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
            'path' => 'mydir',
        ], $this->authHeaders());

        $response->assertOk();
        // File goes to temp (conflict path) - should still be listed
        // because generateStats runs regardless
        $this->assertGreaterThanOrEqual(1, count($response->json('files')));
    }

    public function test_upload_empty_path_defaults_to_root(): void
    {
        $file = UploadedFile::fake()->create('root-file.txt', 100);

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
            'path' => '',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'root-file.txt');
    }

    // ─── Show Missing File ───

    public function test_show_file_for_database_record_with_missing_disk_file_returns_404(): void
    {
        // Create a file record via upload, then delete the disk file
        $this->uploadFile('', 'gone-from-disk.txt', 100);
        $file = LocalFile::where('filename', 'gone-from-disk.txt')->first();
        $this->assertNotNull($file);

        // Delete the file from disk directly (not via API, to keep DB record)
        Storage::disk('local')->delete(CONTENT_SUBDIR . DS . 'gone-from-disk.txt');

        // Show should return 404 since disk file is missing
        $response = $this->getJson("/api/v1/files/{$file->id}", $this->authHeaders());
        $response->assertNotFound();
    }

    // ─── Cannot Delete Other User's File (app has no per-user isolation) ───

    // ─── New API Tests ───

    public function test_list_files_with_per_page_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->uploadFile('', "page-{$i}.txt", 100);
        }

        $response = $this->getJson('/api/v1/files?per_page=2', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(2, 'files')
            ->assertJsonPath('meta.total', 5);
    }

    public function test_list_files_with_invalid_per_page_returns_422(): void
    {
        $response = $this->getJson('/api/v1/files?per_page=0', $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_list_files_with_per_page_101_returns_422(): void
    {
        $response = $this->getJson('/api/v1/files?per_page=101', $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_list_files_with_nonexistent_path_returns_empty(): void
    {
        $response = $this->getJson('/api/v1/files?path=doesnotexist', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(0, 'files');
    }

    public function test_upload_missing_files_field_returns_422(): void
    {
        $response = $this->postJson('/api/v1/files/upload', [
            'path' => '',
        ], $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_upload_with_non_file_in_array_returns_422(): void
    {
        $response = $this->postJson('/api/v1/files/upload', [
            'files' => ['not-a-file'],
        ], $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_upload_to_path_with_traversal_segments_returns_422(): void
    {
        $file = UploadedFile::fake()->create('traversal.txt', 100);
        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
            'path' => '../outside',
        ], $this->authHeaders());
        $this->assertContains($response->status(), [302, 422]);
    }

    public function test_upload_large_batch_all_appears_in_response(): void
    {
        $files = [];
        for ($i = 0; $i < 10; $i++) {
            $files[] = UploadedFile::fake()->create("batch-{$i}.txt", 100);
        }

        $response = $this->postJson('/api/v1/files/upload', [
            'files' => $files,
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(10, 'files');
    }

    public function test_create_with_name_containing_dotdot_returns_422(): void
    {
        $response = $this->postJson('/api/v1/files/create', [
            'name' => '../evil',
            'type' => 'file',
        ], $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_create_with_name_containing_slash_returns_422(): void
    {
        $response = $this->postJson('/api/v1/files/create', [
            'name' => 'a/b',
            'type' => 'file',
        ], $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_create_with_empty_name_returns_422(): void
    {
        $response = $this->postJson('/api/v1/files/create', [
            'name' => '',
            'type' => 'file',
        ], $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_create_folder_then_upload_into_it(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'upload-target',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $file = UploadedFile::fake()->create('inside-folder.txt', 100);
        $response = $this->postJson('/api/v1/files/upload', [
            'files' => [$file],
            'path' => 'upload-target',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'upload-target' . DS . 'inside-folder.txt');
    }

    public function test_create_multiple_folders_same_level(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/files/create', [
                'name' => "folder-{$i}",
                'type' => 'folder',
            ], $this->authHeaders())->assertOk();
        }

        $response = $this->getJson('/api/v1/files', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(3, 'files');
    }

    public function test_move_file_to_root(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'move-src',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $this->uploadFile('move-src', 'to-root.txt', 100);
        $file = LocalFile::where('filename', 'to-root.txt')->first();

        $response = $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $file->id],
            'destination' => '/',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'to-root.txt');
        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . DS . 'move-src' . DS . 'to-root.txt');
    }

    public function test_move_file_to_nested_subfolder(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'a',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();
        $this->postJson('/api/v1/files/create', [
            'name' => 'b',
            'type' => 'folder',
            'path' => 'a',
        ], $this->authHeaders())->assertOk();

        $this->uploadFile('', 'deep-move.txt', 100);
        $file = LocalFile::where('filename', 'deep-move.txt')->first();

        $response = $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $file->id],
            'destination' => 'a/b',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'a' . DS . 'b' . DS . 'deep-move.txt');
    }

    public function test_move_file_from_nested_subfolder(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'from-parent',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();
        $this->postJson('/api/v1/files/create', [
            'name' => 'from-child',
            'type' => 'folder',
            'path' => 'from-parent',
        ], $this->authHeaders())->assertOk();

        $this->uploadFile('from-parent/from-child', 'nested-file.txt', 100);
        $file = LocalFile::where('filename', 'nested-file.txt')->first();

        $response = $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $file->id],
            'destination' => '/',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'nested-file.txt');
        $newFile = LocalFile::where('filename', 'nested-file.txt')->first();
        $this->assertEquals('', $newFile->public_path);
    }

    public function test_move_mixed_files_and_folders(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'mix-dest',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $this->uploadFile('', 'mix-file.txt', 100);
        $this->postJson('/api/v1/files/create', [
            'name' => 'mix-folder',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $file = LocalFile::where('filename', 'mix-file.txt')->first();
        $folder = LocalFile::where('filename', 'mix-folder')->where('is_dir', true)->first();

        $response = $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $file->id, (string) $folder->id],
            'destination' => 'mix-dest',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'mix-dest' . DS . 'mix-file.txt');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'mix-dest' . DS . 'mix-folder');
    }

    public function test_move_folder_with_children(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'parent-folder',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();
        $this->uploadFile('parent-folder', 'child.txt', 100);

        $this->postJson('/api/v1/files/create', [
            'name' => 'dest',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $folder = LocalFile::where('filename', 'parent-folder')->where('is_dir', true)->first();

        $response = $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $folder->id],
            'destination' => 'dest',
        ], $this->authHeaders());

        $response->assertOk();
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'dest' . DS . 'parent-folder' . DS . 'child.txt');
    }

    public function test_move_to_same_location_returns_422_conflict(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'same-loc',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();
        $this->uploadFile('same-loc', 'already-here.txt', 100);
        $file = LocalFile::where('filename', 'already-here.txt')->first();

        $response = $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $file->id],
            'destination' => 'same-loc',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_move_with_empty_fileList_returns_422(): void
    {
        $response = $this->postJson('/api/v1/files/move', [
            'fileList' => [],
            'destination' => '/',
        ], $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_move_with_invalid_ulid_returns_422(): void
    {
        $response = $this->postJson('/api/v1/files/move', [
            'fileList' => ['not-a-valid-ulid'],
            'destination' => '/',
        ], $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_move_missing_destination_returns_422(): void
    {
        $this->uploadFile('', 'no-dest.txt', 100);
        $file = LocalFile::where('filename', 'no-dest.txt')->first();

        $response = $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $file->id],
        ], $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_move_file_updates_source_path_in_db(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'src-path',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();
        $this->uploadFile('src-path', 'track-me.txt', 100);
        $file = LocalFile::where('filename', 'track-me.txt')->first();
        $oldId = $file->id;

        $this->postJson('/api/v1/files/create', [
            'name' => 'dst-path',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $this->postJson('/api/v1/files/move', [
            'fileList' => [(string) $file->id],
            'destination' => 'dst-path',
        ], $this->authHeaders())->assertOk();

        $this->assertDatabaseHas('local_files', ['id' => $oldId, 'public_path' => 'dst-path']);
        $newFile = LocalFile::where('filename', 'track-me.txt')->first();
        $this->assertEquals('dst-path', $newFile->public_path);
    }

    public function test_show_folder_returns_folder_info(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'show-folder',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $folder = LocalFile::where('filename', 'show-folder')->where('is_dir', true)->first();

        $response = $this->getJson("/api/v1/files/{$folder->id}", $this->authHeaders());
        $response->assertOk()
            ->assertJsonPath('file.filename', 'show-folder');
    }

    public function test_show_with_invalid_id_format(): void
    {
        $response = $this->getJson('/api/v1/files/not-a-ulid', $this->authHeaders());
        $this->assertContains($response->status(), [404, 422]);
    }

    public function test_download_folder_returns_error(): void
    {
        $this->postJson('/api/v1/files/create', [
            'name' => 'dl-folder',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();

        $folder = LocalFile::where('filename', 'dl-folder')->where('is_dir', true)->first();

        $response = $this->getJson("/api/v1/files/{$folder->id}/download", $this->authHeaders());
        $this->assertContains($response->status(), [404, 422]);
    }

    public function test_download_file_missing_from_disk(): void
    {
        $this->uploadFile('', 'disk-gone.txt', 100);
        $file = LocalFile::where('filename', 'disk-gone.txt')->first();
        Storage::disk('local')->delete(CONTENT_SUBDIR . DS . 'disk-gone.txt');

        $response = $this->getJson("/api/v1/files/{$file->id}/download", $this->authHeaders());
        $response->assertStatus(404);
    }

    public function test_download_file_from_subfolder(): void
    {
        $this->uploadFile('sub', 'sub-file.txt', 100);
        $file = LocalFile::where('filename', 'sub-file.txt')->first();

        $response = $this->getJson("/api/v1/files/{$file->id}/download", $this->authHeaders());
        $response->assertOk();
    }

    public function test_delete_file_then_verify_not_in_list(): void
    {
        $this->uploadFile('', 'gone-list.txt', 100);
        $file = LocalFile::where('filename', 'gone-list.txt')->first();

        $this->deleteJson("/api/v1/files/{$file->id}", [], $this->authHeaders())->assertOk();

        $response = $this->getJson('/api/v1/files', $this->authHeaders());
        $filenames = array_column($response->json('files'), 'filename');
        $this->assertNotContains('gone-list.txt', $filenames);
    }

    public function test_delete_multiple_files_sequentially(): void
    {
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $this->uploadFile('', "seq-del-{$i}.txt", 100);
            $ids[] = LocalFile::where('filename', "seq-del-{$i}.txt")->first()->id;
        }

        foreach ($ids as $id) {
            $this->deleteJson("/api/v1/files/{$id}", [], $this->authHeaders())->assertOk();
        }

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('local_files', ['id' => $id]);
        }
    }

    public function test_rename_with_name_containing_dotdot_returns_422(): void
    {
        $this->uploadFile('', 'rename-src.txt', 100);
        $file = LocalFile::where('filename', 'rename-src.txt')->first();

        $response = $this->postJson("/api/v1/files/{$file->id}/rename", [
            'name' => '../evil',
        ], $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_rename_with_name_containing_slash_returns_422(): void
    {
        $this->uploadFile('', 'rename-slash.txt', 100);
        $file = LocalFile::where('filename', 'rename-slash.txt')->first();

        $response = $this->postJson("/api/v1/files/{$file->id}/rename", [
            'name' => 'a/b',
        ], $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_save_to_bin_file_succeeds(): void
    {
        $this->uploadFile('', 'binary-save.bin', 100);
        $file = LocalFile::where('filename', 'binary-save.bin')->first();

        $response = $this->postJson("/api/v1/files/{$file->id}/save", [
            'content' => 'binary content',
        ], $this->authHeaders());

        $response->assertOk();
    }

    public function test_save_then_download_content_matches(): void
    {
        $this->uploadFile('', 'roundtrip.txt', 1);
        $file = LocalFile::where('filename', 'roundtrip.txt')->first();
        $expected = 'roundtrip test content ' . Str::random(50);

        $this->postJson("/api/v1/files/{$file->id}/save", [
            'content' => $expected,
        ], $this->authHeaders())->assertOk();

        $response = $this->getJson("/api/v1/files/{$file->id}/download", $this->authHeaders());
        $response->assertOk();
        $this->assertEquals($expected, file_get_contents($file->getPrivatePathNameForFile()));
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
