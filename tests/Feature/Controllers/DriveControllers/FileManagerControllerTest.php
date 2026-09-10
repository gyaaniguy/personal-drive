<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use App\Models\Setting;
use App\Services\FileOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\BaseFeatureTest;

class FileManagerControllerTest extends BaseFeatureTest
{
    use RefreshDatabase;

    public function test_index_returns_files_for_root_path()
    {
        $this->uploadMultipleFiles();

        $response = $this->get(route('drive', ['path' => '']));

        $response->assertInertia(
            fn(Assert $page) => $page->component('Drive/DriveHome')
                ->has('files')
                ->where('path', '/drive')
                ->where('token', csrf_token())
                ->count('files', 4)
        );
    }

    public function test_index_returns_files_for_given_sub_path()
    {
        $this->uploadMultipleFiles();

        $response = $this->get(route('drive', ['path' => 'foo/bar']));

        $response->assertInertia(
            fn(Assert $page) => $page->component('Drive/DriveHome')
                ->has('files')
                ->where('path', '/drive/foo/bar')
                ->where('folderExists', true)
                ->where('token', csrf_token())
                ->count('files', 2)
        );
    }

    public function test_index_marks_created_empty_folder_as_existing()
    {
        $storagePath = Storage::disk('local')->path('alternate');
        Setting::updateStoragePath($storagePath);
        app(FileOperationsService::class)->setFilesystem(null);

        $this->post(route('drive.create-item'), [
            '_token' => csrf_token(),
            'itemName' => 'empty',
            'path' => '',
            'isFile' => false,
        ])->assertSessionHas('status', true);
        $response = $this->get(route('drive', ['path' => 'empty']));

        $response->assertInertia(
            fn(Assert $page) => $page->where('folderExists', true)
                ->count('files', 0)
        );
    }

    public function test_index_resolves_folder_named_with_hash()
    {
        $storagePath = Storage::disk('local')->path('alternate');
        Setting::updateStoragePath($storagePath);
        app(FileOperationsService::class)->setFilesystem(null);

        $this->post(route('drive.create-item'), [
            '_token' => csrf_token(),
            'itemName' => 'h#sh',
            'path' => '',
            'isFile' => false,
        ])->assertSessionHas('status', true);
        $response = $this->get(route('drive', ['path' => 'h#sh']));

        $response->assertInertia(
            fn(Assert $page) => $page->where('path', '/drive/h#sh')
                ->where('folderExists', true)
        );
    }

    public function test_index_marks_missing_folder()
    {
        $response = $this->get(route('drive', ['path' => 'missing']));

        $response->assertInertia(
            fn(Assert $page) => $page->where('folderExists', false)
                ->count('files', 0)
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
