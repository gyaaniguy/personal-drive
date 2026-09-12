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
                ->where('totalShares', count($shares))
        );
    }

    public function test_added_share_row_shows_all_values_then_pause_then_delete()
    {
        $slug = 'row-values';
        list($toShareFileIds, $password) = $this->getDataForMakingShare();
        $this->createShare($toShareFileIds, $password, 7, $slug);

        $share = Share::whereBySlug($slug)->firstOrFail();
        $expectedFilenames = LocalFile::whereIn('id', $toShareFileIds)->orderBy('id')->pluck('filename');

        // row visible with all column values rendered by AllShares
        $this->get('shares-all')->assertInertia(
            fn($page) => $page
                ->component('Drive/Shares/AllShares')
                ->has('shares', 1)
                ->where('shares.0.id', $share->id)
                ->where('shares.0.slug', $slug)
                ->where('shares.0.enabled', 1)
                ->where('shares.0.password', fn($v) => Hash::check($password, $v))
                ->where('shares.0.expiry', 7)
                ->where('shares.0.expiry_time', $share->expiry_time)
                ->where('shares.0.created_at', fn($v) => str_starts_with($v, $share->created_at->toDateString()))
                ->has('shares.0.shared_files', count($toShareFileIds))
                ->where('shares.0.shared_files.0.local_file.filename', $expectedFilenames[0])
                ->where('shares.0.shared_files.1.local_file.filename', $expectedFilenames[1])
        );

        // pause -> row still visible, now disabled
        $this->post(route('drive.share-pause'), ['_token' => csrf_token(), 'id' => $share->id])
            ->assertSessionHas('status', true)
            ->assertSessionHas('message', 'Paused');

        $this->get('shares-all')->assertInertia(
            fn($page) => $page
                ->component('Drive/Shares/AllShares')
                ->has('shares', 1)
                ->where('shares.0.id', $share->id)
                ->where('shares.0.enabled', 0)
        );

        // delete -> row gone
        $this->post(route('drive.share-delete'), ['_token' => csrf_token(), 'id' => $share->id])
            ->assertSessionHas('status', true)
            ->assertSessionHas('message', 'Share deleted');

        $this->get('shares-all')->assertInertia(
            fn($page) => $page
                ->component('Drive/Shares/AllShares')
                ->has('shares', 0)
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
