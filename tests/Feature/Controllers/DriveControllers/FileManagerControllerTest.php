<?php

namespace Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
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

        $response->assertInertia(fn(Assert $page) => $page->component('Drive/DriveHome')
            ->has('files')
            ->where('path', '/drive')
            ->where('token', csrf_token())
            ->count('files', 4));
    }

    public function test_index_returns_files_for_given_sub_path()
    {
        $this->uploadMultipleFiles();

        $response = $this->get(route('drive', ['path' => 'foo/bar']));

        $response->assertInertia(fn(Assert $page) => $page->component('Drive/DriveHome')
            ->has('files')
            ->where('path', '/drive/foo/bar')
            ->where('token', csrf_token())
            ->count('files', 2));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
