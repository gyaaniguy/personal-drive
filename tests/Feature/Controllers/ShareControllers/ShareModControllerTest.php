<?php

namespace Tests\Feature\Controllers\ShareControllers;

use App\Exceptions\PersonalDriveExceptions\ShareFileException;
use App\Models\LocalFile;
use App\Models\Share;
use App\Models\User;
use App\Models\SharedFile;
use App\Services\LocalFileStatsService;
use App\Services\PathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\Feature\BaseFeatureTest;

class ShareModControllerTest extends BaseFeatureTest
{
    public $fileNames = [
        'ace.txt', 'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/bar/1.txt'
    ];


    public function test_pause_fail()
    {
        $response = $this->post(
            route('drive.share-pause'), [
            '_token' => csrf_token(),
            'id' => rand(1000000, 232324234234),
            ]
        );
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Error! could not find share');
    }

    public function test_delete_fail()
    {
        $id = rand(1000000, 232324234234);
        $response = $this->deleteShare($id);
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Error! could not delete share');
    }

    public function deleteShare(int $id): TestResponse
    {
        return $this->post(
            route('drive.share-delete'), [
            '_token' => csrf_token(),
            'id' => $id,
            ]
        );
    }

    public function test_anonymous_guest_cannot_pause_existing_share()
    {
        $this->createMultipleShares(['anon-pause-share']);
        $shareId = $this->getSlugId('anon-pause-share');
        $this->logout();

        $this->pauseShare($shareId)
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('shares', ['id' => $shareId, 'enabled' => 1]);
    }

    public function test_anonymous_guest_cannot_delete_existing_share()
    {
        $this->createMultipleShares(['anon-delete-share']);
        $shareId = $this->getSlugId('anon-delete-share');
        $this->logout();

        $this->deleteShare($shareId)
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('shares', ['id' => $shareId, 'enabled' => 1]);
    }

    public function test_non_admin_cannot_pause_existing_share()
    {
        $this->createMultipleShares(['na-pause-share']);
        $shareId = $this->getSlugId('na-pause-share');
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->pauseShare($shareId)
            ->assertRedirect(route('rejected', ['message' => 'You do not have admin access']));

        $this->assertDatabaseHas('shares', ['id' => $shareId, 'enabled' => 1]);
    }

    public function test_non_admin_cannot_delete_existing_share()
    {
        $this->createMultipleShares(['na-delete-share']);
        $shareId = $this->getSlugId('na-delete-share');
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->deleteShare($shareId)
            ->assertRedirect(route('rejected', ['message' => 'You do not have admin access']));

        $this->assertDatabaseHas('shares', ['id' => $shareId, 'enabled' => 1]);
    }

    public function test_delete_success()
    {
        $slug = 'test-slug';
        $slug1 = 'test-slug1';
        $this->createMultipleShares([$slug, $slug1]);

        $slug1Id = $this->getSlugId($slug1);

        $response = $this->deleteShare($slug1Id);
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Successfully deleted share');
        $response = $this->deleteShare($slug1Id);
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Error! could not delete share');
    }

    public function test_pause_resume_success()
    {
        $slug = 'test-slug';
        $slug1 = 'test-slug1';
        $this->createMultipleShares([$slug, $slug1]);
        $this->assertEquals(1, $this->getShareEnabledStatus($slug1));

        $slug1Id = $this->getSlugId($slug1);
        $response = $this->pauseShare($slug1Id);
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Paused');
        $this->assertEquals(0, $this->getShareEnabledStatus($slug1));
        $this->assertEquals(1, $this->getShareEnabledStatus($slug));

        $response = $this->pauseShare($slug1Id);
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Enabled');
        $this->assertEquals(1, $this->getShareEnabledStatus($slug1));

        $response = $this->pauseShare($slug1Id);
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Paused');
        $this->assertEquals(0, $this->getShareEnabledStatus($slug1));
    }

    public function getShareEnabledStatus(string $slug1): mixed
    {
        return Share::where('slug', $slug1)->pluck('enabled')->first();
    }

    public function pauseShare(mixed $slug1Id): TestResponse
    {
        return $this->post(
            route('drive.share-pause'), [
            '_token' => csrf_token(),
            'id' => $slug1Id
            ]
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
