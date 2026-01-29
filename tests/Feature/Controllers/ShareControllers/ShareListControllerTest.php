<?php

namespace Tests\Feature\Controllers\ShareControllers;

use App\Exceptions\PersonalDriveExceptions\ShareFileException;
use App\Models\LocalFile;
use App\Models\Share;
use App\Models\SharedFile;
use App\Services\LocalFileStatsService;
use App\Services\PathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\Feature\BaseFeatureTest;

class ShareListControllerTest extends BaseFeatureTest
{
    public $fileNames = [
        'ace.txt', 'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/bar/1.txt'
    ];


    public function test_list_multiple()
    {
        $slug = 'test-slug';
        $slug1 = 'test-slug1';
        list($toShareFileIds, $password, $expiry) = $this->getDataForMakingShare();
        $this->createShare($toShareFileIds, $password, $expiry, $slug);
        $this->createShare($toShareFileIds, $password, $expiry, $slug1);

        $response = $this->get('shares-all');
        $response->assertStatus(200);
        $shares = Share::all();

        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/Shares/AllShares')
                ->has('shares', count($shares))
        );
    }

    public function test_list_expiry_scenarios()
    {
        list($toShareFileIds) = $this->getDataForMakingShare();

        $this->createShare($toShareFileIds, '', 9, '9days');
        $this->createShare($toShareFileIds, '', 11, '11days');


        $shares = Share::all();
        $this->assertCount(2, $shares);

        Share::first()->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();
        $response = $this->get('shares-all');
        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/Shares/AllShares')
                ->has('shares', 1)
        );
        Share::first()->update(['expiry' => 11]);
        $response = $this->get('shares-all');
        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/Shares/AllShares')
                ->has('shares', 2)
        );
        Share::first()->update(['expiry' => null]);
        $response = $this->get('shares-all');
        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/Shares/AllShares')
                ->has('shares', 2)
        );
        Share::first()->update(['expiry' => '9']);
        $response = $this->get('shares-all');
        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/Shares/AllShares')
                ->has('shares', 1)
        );
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadMultipleFiles('');
    }
}
