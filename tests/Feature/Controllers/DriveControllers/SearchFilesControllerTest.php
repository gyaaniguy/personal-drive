<?php

namespace Feature\Controllers\DriveControllers;

use Tests\Feature\BaseFeatureTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;

class SearchFilesControllerTest extends BaseFeatureTest
{
    public function test_search_results_single_success()
    {
        $response = $this->post(route('drive.search'), [
            '_token' => csrf_token(),
            'query' => 'foo',
        ]);

        $response->assertOk();
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/DriveHome')
                ->where('searchResults', true)
                ->has('files')
                ->count('files', 1)
        );
    }

    public function test_search_results_multiple_success()
    {
        $response = $this->post(route('drive.search'), [
            '_token' => csrf_token(),
            'query' => 'bar',
        ]);


        $response->assertOk();
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/DriveHome')
                ->where('searchResults', true)
                ->has('files')
                ->count('files', 2)
        );
    }

    public function test_search_partial_success()
    {
        $response = $this->post(route('drive.search'), [
            '_token' => csrf_token(),
            'query' => 'ace.txt',
        ]);


        $response->assertOk();
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/DriveHome')
                ->where('searchResults', true)
                ->has('files')
                ->count('files', 2)
        );
    }

    public function test_search_noresults()
    {
        $response = $this->post(route('drive.search'), [
            '_token' => csrf_token(),
            'query' => 'nothere',
        ]);


        $response->assertOk();
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/DriveHome')
                ->where('searchResults', true)
                ->has('files')
                ->count('files', 0)
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
