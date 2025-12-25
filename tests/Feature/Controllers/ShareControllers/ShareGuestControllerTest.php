<?php

namespace Feature\Controllers\ShareControllers;

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
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\Feature\BaseFeatureTest;

class ShareGuestControllerTest extends BaseFeatureTest
{
    private array $fileNames = [
        'ace.txt', 'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/bar/1.txt'
    ];


    public function test_get_post_password_success()
    {
        $slug = 'test-slug';
        $slug1 = 'test-slug1';

        list($toShareFileIds) = $this->getDataForMakingShare();

        $this->createShare($toShareFileIds, 'password', 7, $slug);
        $this->createShare($toShareFileIds, 'password1', 7, $slug1);
        $this->logout();

        $response = $this->postCheckPassword($slug1, 'password1');
        $shareSlug1 = Share::whereBySlug($slug1)->first();
        $response->assertSessionHas("shared_{$slug1}_authenticated", true);
        $response->assertSessionHas("share_id", $shareSlug1->id);
        $response->assertStatus(302);
        $response->assertRedirect('/shared/' . $slug1);

        $this->get('/shared/' . $slug1);
        $response = $this->followingRedirects()->get('/shared/' . $slug1);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/ShareFilesGuestHome')
                ->where('slug', $slug1)
        );
        $response = $this->followingRedirects()->get('/shared/' . $slug);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/Shares/CheckSharePassword')
                ->where('slug', $slug)
        );
    }

    public function test_get_post_password_with_invalid_slug()
    {
        $slug = 'test-slug';
        $this->createMultipleShares([$slug]);
        $this->logout();
        $response = $this->postCheckPassword('wrong-slug', 'password');

        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Wrong password');
    }

    public function test_get_invalid_password()
    {
        $slug = 'test-slug';
        $this->createMultipleShares([$slug]);
        $this->logout();
        $response = $this->postCheckPassword($slug, 'wrong-password');

        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Wrong password');
    }


    public function test_share_password_page()
    {
        $slug = 'test-slug';
        $slug1 = 'test-slug1';
        $slug2 = 'test-slug2';
        $this->createMultipleShares([$slug, $slug1]);
        list($toShareFileIds, $password) = $this->getDataForMakingShare('', 2, 3);
        $filesObj = LocalFile::getByIds($toShareFileIds)->get();
        $filesObj = LocalFile::modifyFileCollectionForGuest($filesObj);
        $this->createShare($toShareFileIds, $password, 13, $slug2);
        $this->logout();

        $response = $this->get('/shared/' . $slug1);
        $response->assertStatus(302);
        $response->assertRedirect('/shared-password/' . $slug1);
        $response = $this->followingRedirects()->get('/shared/' . $slug1);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/Shares/CheckSharePassword')
                ->where('slug', $slug1)
        );
        $response = $this->get('/shared/' . $slug2);
        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/ShareFilesGuestHome')
                ->where('path', '/shared/' . $slug2)
                ->where('guest', 'on')
                ->where('files', $filesObj)
        );

        $response = $this->get('/shared/' . $slug2 . '/bar');
        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/ShareFilesGuestHome')
                ->where('path', '/shared/' . $slug2 . '/bar')
                ->where('guest', 'on')
            //                ->where('files', $filesObj)
        );
    }

    public function test_get_invalid_share()
    {
        $this->logout();
        $response = $this->get('/shared/');
        $response->assertRedirect(route('rejected'));

        $response = $this->get('/shared/' . 'no-such-share');
        $response->assertRedirect(route('login', ['slug' => 'no-such-share']));
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadMultipleFiles('', $this->fileNames);
    }
}
