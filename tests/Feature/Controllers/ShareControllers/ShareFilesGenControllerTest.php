<?php

namespace Tests\Feature\Controllers\ShareControllers;

use App\Exceptions\PersonalDriveExceptions\ShareFileException;
use App\Models\LocalFile;
use App\Models\Share;
use App\Models\SharedFile;
use App\Services\LocalFileStatsService;
use App\Services\PathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\Feature\BaseFeatureTest;

class ShareFilesGenControllerTest extends BaseFeatureTest
{
    public $fileNames = [
        'ace.txt', 'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/bar/1.txt'
    ];

    public function test_share_no_valid_files()
    {
        $response = $this->createShare([(string) Str::ulid()]);
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'No valid files to share. Try a Resync');
    }

    public function test_share_multiple()
    {
        $slug = 'test-slug';
        list($toShareFileIds, $password, $expiry) = $this->getDataForMakingShare();
        $this->createShare($toShareFileIds, $password, $expiry, $slug);
        $response = $this->createShare($toShareFileIds, '', 0, $slug);

        $response->assertSessionHasErrors(
            [
            'slug' => 'The slug has already been taken.',
            ]
        );
        $shares = Share::all();
        $this->assertCount(1, $shares);
    }

    public function test_share_success()
    {
        $slug = 'test-slug';
        list($toShareFileIds, $password, $expiry) = $this->getDataForMakingShare();
        $response = $this->createShare($toShareFileIds, $password, $expiry, $slug);

        $response->assertSessionHas('shared_link', fn($value) => str_ends_with($value, '/shared/' . $slug));

        $shares = Share::all();
        $this->assertCount(1, $shares);
        $share = $shares->first();
        $this->assertEquals($slug, $share->slug);

        $this->assertTrue(Hash::check($password, $share->password));
        $this->assertEquals($expiry, $share->expiry);

        $sharedFiles = SharedFile::where('share_id', $share->id)->get();
        $this->assertCount(2, $sharedFiles);
        $sharedFileIds = $sharedFiles->pluck('file_id')->toArray();
        $this->assertEquals($toShareFileIds, $sharedFileIds);
    }

    public function test_share_generate_slug()
    {
        list($toShareFileIds) = $this->getDataForMakingShare();

        $response = $this->createShare($toShareFileIds);

        $response->assertSessionHas('shared_link', fn($value) => str_contains($value, '/shared/'));

        $shares = Share::all();
        $this->assertCount(1, $shares);
        $share = $shares->first();
        $this->assertEquals(10, strlen($share->slug));
        $this->assertNotEmpty($share->slug);

        $this->assertEmpty($share->password);
        $this->assertEmpty($share->expiry);
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadMultipleFiles('');
    }
}
