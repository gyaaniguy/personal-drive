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

    public function test_share_fetch_file_success()
    {
        $slug = 'testslug';
        list($toShareFileIds) = $this->getDataForMakingShare();
        $this->createShare($toShareFileIds, 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $response = $this->get(route('drive.fetch-file', ['id' => $toShareFileIds[0], 'slug' => $slug]));
        $response->assertOk();
    }

    public function test_share_fetch_file_fail()
    {
        $slug = 'testslug';
        list($toShareFileIds) = $this->getDataForMakingShare('password', 7, 1);
        $this->createShare($toShareFileIds, 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $allFiles = LocalFile::all()->pluck('id')->toArray();


        $response = $this->get(route('drive.fetch-file', ['id' => $allFiles[1], 'slug' => $slug]));
        $response->assertRedirect(route('rejected', [
            'message' => 'Could not find file to send'
        ]));
    }

    public function test_share_download_success()
    {
        $slug = 'test-slug';
        list($toShareFileIds) = $this->getDataForMakingShare();
        $this->createShare($toShareFileIds, 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $this->get('/shared/' . $slug);
        $response = $this->followingRedirects()->get('/shared/' . $slug);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/ShareFilesGuestHome')
                ->where('slug', $slug)
        );

        $response = $this->post('/download-files', [
            'fileList' => [$toShareFileIds[0]],
            'slug'   => $slug,
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=ace.txt');
    }

    public function test_share_download_fail()
    {
        $slug = 'test-slug';
        list($toShareFileIds) = $this->getDataForMakingShare();
        $this->createShare(array_slice($toShareFileIds,0,2), 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $this->get('/shared/' . $slug);
        $response = $this->followingRedirects()->get('/shared/' . $slug);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/ShareFilesGuestHome')
                ->where('slug', $slug)
        );

        $allFiles = LocalFile::all()->pluck('id')->toArray();

        $response = $this->post('/download-files', [
            'fileList' => [$allFiles[3]],
            'slug'   => $slug,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => false,
            'message' => 'Error: authorization issue',
        ]);
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
        list($toShareFileIds, $password) = $this->getDataForMakingShare('', 2, 4);
        unset($toShareFileIds[2]);
        $filesObj = LocalFile::getByIds($toShareFileIds)->get();
        $filesObj = LocalFile::modifyFileCollectionForGuest($filesObj);

        $filesObjBar = LocalFile::getByPublicPathLikeSearch('bar')->get();
        $filesObjBar = LocalFile::modifyFileCollectionForGuest($filesObjBar);
//        var_dump($filesObj,$filesObjBar1);
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
                ->where('files', $filesObjBar)
        );

        // Something that does not exist
        $response = $this->get('/shared/' . $slug2 . '/foo1');
        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/ShareFilesGuestHome')
                ->where('path', '/shared/' . $slug2 . '/foo1')
                ->where('guest', 'on')
                ->where('files', [])
        );
        // UnAuthorized
        $response = $this->get('/shared/' . $slug2 . '/foo');
        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/ShareFilesGuestHome')
                ->where('path', '/shared/' . $slug2 . '/foo')
                ->where('guest', 'on')
                ->where('files', [])
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

//    public function test_share_download_with_invalid_files()
//    {
//        $slug = 'test-slug';
//        $slug1 = 'test-slug1';
//        list($toShareFileIds, $password) = $this->getDataForMakingShare('', 2, 3);
//        $this->createShare($toShareFileIds, $password, 13, $slug);
//        $this->logout();
//        $response = $this->postCheckPassword('test-slug', 'password');
//
//        $response->assertSessionHas('status', false);
//        $response->assertSessionHas('message', 'Wrong password');
//    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadMultipleFiles('');
    }
}
