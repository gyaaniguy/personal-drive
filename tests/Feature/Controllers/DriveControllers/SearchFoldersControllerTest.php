<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Feature\BaseFeatureTest;

class SearchFoldersControllerTest extends BaseFeatureTest
{
    public function test_returns_matching_folder_as_full_path()
    {
        $response = $this->getJson(route('drive.search-folders', ['query' => 'foo']));

        $response->assertOk();
        $this->assertContains('foo', $response->json());
    }

    public function test_returns_nested_folder_with_parent_path()
    {
        // 'bar' exists both at the root and nested under 'foo'.
        $response = $this->getJson(route('drive.search-folders', ['query' => 'bar']));

        $response->assertOk();
        $paths = $response->json();
        $this->assertContains('bar', $paths);
        $this->assertContains('foo/bar', $paths);
    }

    public function test_excludes_files_matching_the_query()
    {
        // 'ace' matches the files ace.txt / foo/ace.txt but no folder.
        $response = $this->getJson(route('drive.search-folders', ['query' => 'ace']));

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_only_returns_current_users_folders()
    {
        $other = User::create([
            'username' => 'otheruser',
            'is_admin' => false,
            'password' => 'password',
        ]);
        LocalFile::create([
            'filename' => 'secretfolder',
            'is_dir' => 1,
            'public_path' => '',
            'private_path' => '/tmp',
            'size' => '',
            'user_id' => $other->id,
            'file_type' => 'dir',
        ]);

        $response = $this->getJson(route('drive.search-folders', ['query' => 'secretfolder']));

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_query_is_required()
    {
        // Web routes flash-and-redirect on validation failure (see bootstrap/app.php).
        $this->getJson(route('drive.search-folders'))->assertStatus(302);
    }

    public function test_query_rejects_slash()
    {
        $this->getJson(route('drive.search-folders', ['query' => 'foo/bar']))
            ->assertStatus(302);
    }

    public function test_requires_authentication()
    {
        $this->logout();

        $this->getJson(route('drive.search-folders', ['query' => 'foo']))
            ->assertUnauthorized();
    }

    public function test_navigating_to_found_folder_renders_inertia_drive_page()
    {
        $paths = $this->getJson(route('drive.search-folders', ['query' => 'foo']))->json();
        $this->assertContains('foo', $paths);

        $response = $this->get('/drive/foo');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Drive/DriveHome')
                ->where('path', '/drive/foo')
                ->where('folderExists', true)
                ->has('files')
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadMultipleFiles();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
