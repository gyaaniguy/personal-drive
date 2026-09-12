<?php

namespace Tests\Feature\Controllers\ShareControllers;

use App\Http\Middleware\HandleGuestShareMiddleware;
use App\Http\Controllers\DriveControllers\FileFetchController;
use App\Models\LocalFile;
use App\Models\Share;
use App\Services\LocalFileStatsService;
use App\Services\ShareAuthorizationService;
use App\Services\ThumbnailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\Feature\BaseFeatureTest;

class ShareGuestControllerTest extends BaseFeatureTest
{
    public function test_get_post_password_success()
    {
        $slug = 'test-slug';
        $slug1 = 'test-slug1';

        [$toShareFileIds] = $this->getDataForMakingShare();

        $this->createShare($toShareFileIds, 'password', 7, $slug);
        $this->createShare($toShareFileIds, 'password1', 7, $slug1);
        $this->logout();

        $response = $this->postCheckPassword($slug1, 'password1');
        $shareSlug1 = Share::whereBySlug($slug1)->first();
        $response->assertSessionHas("shared_{$slug1}_authenticated", true);
        $response->assertSessionHas('share_id', $shareSlug1->id);
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
        [$toShareFileIds] = $this->getDataForMakingShare();
        $this->createShare($toShareFileIds, 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $response = $this->get(route('drive.fetch-file', ['id' => $toShareFileIds[0], 'slug' => $slug]));
        $response->assertOk();
    }

    public function test_share_fetch_html_file_is_sandboxed_inline()
    {
        $slug = 'testslug';
        $htmlFile = UploadedFile::fake()->createWithContent(
            'evil.html',
            '<html><script>alert(1)</script></html>'
        );
        $this->postUpload([$htmlFile], '');
        $localFile = LocalFile::where('filename', 'evil.html')->firstOrFail();
        $this->assertSame('html', $localFile->file_type);

        $this->createShare([$localFile->id], 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $this->mockStreamFile();

        $response = $this->get(route('drive.fetch-file', ['id' => $localFile->id, 'slug' => $slug]));
        $response->assertOk();
        $this->assertSame('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    /**
     * VideoStreamer::streamFile() exits the process after echoing, so the
     * real stream cannot run inside a test. Mock it; the controller still
     * sets its headers on the returned response before streaming.
     */
    private function mockStreamFile(): void
    {
        $mock = Mockery::mock(
            FileFetchController::class.'[streamFile]', [
                app(LocalFileStatsService::class),
                app(ThumbnailService::class),
                app(ShareAuthorizationService::class),
            ]
        );

        $mock->shouldReceive('streamFile')
            ->once()
            ->withAnyArgs();

        $this->app->instance(FileFetchController::class, $mock);
    }

    public function test_share_fetch_file_fail()
    {
        $slug = 'testslug';
        $sharedFile = LocalFile::where('filename', 'ace.txt')
            ->where('public_path', '')
            ->firstOrFail();
        $unsharedFile = LocalFile::where('filename', 'ace.txt')
            ->where('public_path', 'foo')
            ->firstOrFail();
        $this->createShare([$sharedFile->id], 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $response = $this->get(route('drive.fetch-file', ['id' => $unsharedFile->id, 'slug' => $slug]));
        $response->assertRedirect(
            route(
                'rejected',
                [
                    'message' => 'Could not find file to send',
                ]
            )
        );
    }

    public function test_share_download_success()
    {
        $slug = 'test-slug';
        [$toShareFileIds] = $this->getDataForMakingShare();
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

        $response = $this->post(
            '/download-files',
            [
                '_token' => csrf_token(),
                'fileList' => [$toShareFileIds[0]],
                'slug' => $slug,
            ]
        );

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=ace.txt');
    }

    public function test_share_download_fail()
    {
        $slug = 'test-slug';
        $sharedFile = LocalFile::where('filename', 'ace.txt')
            ->where('public_path', '')
            ->firstOrFail();
        $unsharedFile = LocalFile::where('filename', 'ace.txt')
            ->where('public_path', 'foo')
            ->firstOrFail();
        $this->createShare([$sharedFile->id], 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $this->get('/shared/' . $slug);
        $response = $this->followingRedirects()->get('/shared/' . $slug);
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/ShareFilesGuestHome')
                ->where('slug', $slug)
        );

        $response = $this->post(
            '/download-files',
            [
                '_token' => csrf_token(),
                'fileList' => [$unsharedFile->id],
                'slug' => $slug,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(
            [
                'status' => false,
                'message' => 'Error: authorization issue',
            ]
        );
    }

    public function test_share_download_valid_invalid_mix()
    {
        $slug = 'test-slug';
        list($toShareFileIds) = $this->getDataForMakingShare('password', 0, 3);
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

        $allFiles = LocalFile::all()->pluck('id')->toArray();

        $response = $this->post(
            '/download-files',
            [
                '_token' => csrf_token(),
                //$allFiles[3] is not shared, but in a different sub-dir
                'fileList' => [$allFiles[0], $allFiles[1], $allFiles[3] ],
                'slug' => $slug,
            ]
        );
        $response->assertStatus(200);
        $response->assertJson(
            [
                'status' => false,
                'message' => 'Error: authorization issue',
            ]
        );

        $response = $this->post(
            '/download-files',
            [
                '_token' => csrf_token(),
                'fileList' => [$allFiles[0], $allFiles[1], $allFiles[2] ],
                'slug' => $slug,
            ]
        );
        $response->assertStatus(200);
        $response->assertHeaderContains('Content-Disposition', 'attachment; filename=personal_drive_');
    }


    public function test_share_download_rejects_mixed_authorized_and_unauthorized_file_ids()
    {
        $slug = 'test-slug';
        $sharedDirectory = LocalFile::where('filename', 'bar')
            ->where('public_path', '')
            ->where('is_dir', true)
            ->firstOrFail();
        $authorizedFile = LocalFile::where('filename', '1.txt')
            ->where('public_path', 'bar')
            ->firstOrFail();
        $unauthorizedFile = LocalFile::where('filename', 'ace.txt')
            ->where('public_path', 'foo')
            ->firstOrFail();
        $this->createShare([$sharedDirectory->id], 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $response = $this->post(
            '/download-files',
            [
                '_token' => csrf_token(),
                'fileList' => [$authorizedFile->id, $unauthorizedFile->id],
                'slug' => $slug,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(
            [
                'status' => false,
                'message' => 'Error: authorization issue',
            ]
        );
    }

    public function test_share_download_allows_direct_file_and_directory_descendant_together()
    {
        $slug = 'test-slug';
        $sharedFile = LocalFile::where('filename', 'ace.txt')
            ->where('public_path', '')
            ->firstOrFail();
        $sharedDirectory = LocalFile::where('filename', 'foo')
            ->where('public_path', '')
            ->where('is_dir', true)
            ->firstOrFail();
        $descendant = LocalFile::where('filename', '1.txt')
            ->where('public_path', 'foo/bar')
            ->firstOrFail();
        $this->createShare([$sharedFile->id, $sharedDirectory->id], 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $response = $this->post(
            '/download-files',
            [
                '_token' => csrf_token(),
                'fileList' => [$sharedFile->id, $descendant->id],
                'slug' => $slug,
            ]
        );

        $response->assertOk();
        $response->assertHeaderContains('Content-Disposition', 'attachment; filename=personal_drive_');
    }

    public function test_share_download_rejects_similarly_prefixed_sibling_directory()
    {
        $slug = 'test-slug';
        $sharedDirectory = LocalFile::where('filename', 'bar')
            ->where('public_path', '')
            ->where('is_dir', true)
            ->firstOrFail();
        $siblingFile = LocalFile::where('filename', '1.txt')
            ->where('public_path', 'barbar')
            ->firstOrFail();
        $this->createShare([$sharedDirectory->id], 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $response = $this->post(
            '/download-files',
            [
                '_token' => csrf_token(),
                'fileList' => [$siblingFile->id],
                'slug' => $slug,
            ]
        );

        $response->assertOk();
        $this->assertInstanceOf(JsonResponse::class, $response->baseResponse);
        $response->assertJson(
            [
                'status' => false,
                'message' => 'Error: authorization issue',
            ]
        );
    }

    public function test_share_download_treats_sql_wildcards_in_directory_names_literally()
    {
        $slug = 'test-slug';
        $sharedDirectory = LocalFile::where('filename', 'bar_')
            ->where('public_path', '')
            ->where('is_dir', true)
            ->firstOrFail();
        $siblingFile = LocalFile::where('filename', '1.txt')
            ->where('public_path', 'barX')
            ->firstOrFail();
        $this->createShare([$sharedDirectory->id], 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $response = $this->post(
            '/download-files',
            [
                '_token' => csrf_token(),
                'fileList' => [$siblingFile->id],
                'slug' => $slug,
            ]
        );

        $response->assertOk();
        $this->assertInstanceOf(JsonResponse::class, $response->baseResponse);
        $response->assertJson(
            [
                'status' => false,
                'message' => 'Error: authorization issue',
            ]
        );
    }

    public function test_share_download_treats_percent_in_directory_names_literally()
    {
        $slug = 'test-percent-slug';
        $sharedDirectory = LocalFile::where('filename', 'bar%')
            ->where('public_path', '')
            ->where('is_dir', true)
            ->firstOrFail();
        $siblingFile = LocalFile::where('filename', '1.txt')
            ->where('public_path', 'baranything')
            ->firstOrFail();
        $this->createShare([$sharedDirectory->id], 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $response = $this->post(
            '/download-files',
            [
                '_token' => csrf_token(),
                'fileList' => [$siblingFile->id],
                'slug' => $slug,
            ]
        );

        $response->assertOk();
        $this->assertInstanceOf(JsonResponse::class, $response->baseResponse);
        $response->assertJson(
            [
                'status' => false,
                'message' => 'Error: authorization issue',
            ]
        );
    }

    public function test_share_listing_rejects_similarly_prefixed_sibling_directory()
    {
        $slug = 'test-slug';
        $sharedDirectory = LocalFile::where('filename', 'bar')
            ->where('public_path', '')
            ->where('is_dir', true)
            ->firstOrFail();
        $this->createShare([$sharedDirectory->id], 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $response = $this->get('/shared/' . $slug . '/barbar');

        $response->assertOk();
        $response->assertInertia(
            fn($page) => $page
                ->component('Drive/ShareFilesGuestHome')
                ->where('files', [])
        );
    }

    public function test_share_fetch_rejects_similarly_prefixed_sibling_directory()
    {
        $slug = 'test-slug';
        $sharedDirectory = LocalFile::where('filename', 'bar')
            ->where('public_path', '')
            ->where('is_dir', true)
            ->firstOrFail();
        $siblingFile = LocalFile::where('filename', '1.txt')
            ->where('public_path', 'barbar')
            ->firstOrFail();
        $siblingFile->update(['file_type' => 'text']);
        $this->createShare([$sharedDirectory->id], 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $response = $this->get(
            route('drive.fetch-file', ['id' => $siblingFile->id, 'slug' => $slug])
        );

        $response->assertRedirect(
            route('rejected', ['message' => 'Could not find file to send'])
        );
    }

    public function test_share_thumbnail_rejects_file_outside_share()
    {
        $slug = 'test-slug';
        $sharedFile = LocalFile::where('filename', 'ace.txt')
            ->where('public_path', '')
            ->firstOrFail();
        $unsharedFile = LocalFile::where('filename', 'ace.txt')
            ->where('public_path', 'foo')
            ->firstOrFail();
        $unsharedFile->file_type = 'image';
        $unsharedFile->has_thumbnail = true;
        $unsharedFile->save();
        $this->createShare([$sharedFile->id], 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $thumbnailService = Mockery::mock(ThumbnailService::class);
        $thumbnailService->shouldReceive('getFullFileThumbnailPath')->andReturn('/path/that/does/not/exist');
        $this->app->instance(ThumbnailService::class, $thumbnailService);

        $response = $this->get(
            route('drive.get-thumb', ['id' => $unsharedFile->id, 'slug' => $slug])
        );

        $response->assertRedirect(
            route('rejected', ['message' => 'Could not find file to send'])
        );
    }

    public function test_share_thumbnail_allows_file_in_share()
    {
        $slug = 'test-slug';
        $sharedFile = LocalFile::where('filename', 'ace.txt')
            ->where('public_path', '')
            ->firstOrFail();
        $sharedFile->file_type = 'image';
        $sharedFile->has_thumbnail = true;
        $sharedFile->save();
        $this->createShare([$sharedFile->id], 'password', 7, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password');

        $thumbnailPath = $sharedFile->getPrivatePathNameForFile();
        $thumbnailService = Mockery::mock(ThumbnailService::class);
        $thumbnailService->shouldReceive('getFullFileThumbnailPath')
            ->once()
            ->withAnyArgs()
            ->andReturn($thumbnailPath);
        $controller = Mockery::mock(
            FileFetchController::class . '[streamFile]',
            [
                app(LocalFileStatsService::class),
                $thumbnailService,
                app(ShareAuthorizationService::class),
            ]
        );
        $controller->shouldReceive('streamFile')->once()->with($thumbnailPath);
        $this->app->instance(FileFetchController::class, $controller);

        $response = $this->get(
            route('drive.get-thumb', ['id' => $sharedFile->id, 'slug' => $slug])
        );

        $response->assertOk();
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
        [$toShareFileIds, $password] = $this->getDataForMakingShare('', 2, 4);
        unset($toShareFileIds[3]);
        $filesObj = LocalFile::getByIds($toShareFileIds)->get();
        $filesObj = LocalFile::modifyFileCollectionForGuest($filesObj);

        $filesObjBar = LocalFile::getByPublicPathLikeSearch('bar')->get();
        $filesObjBar = LocalFile::modifyFileCollectionForGuest($filesObjBar);
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

    public function test_missing_share_is_handled_without_guest_middleware(): void
    {
        $this->withoutMiddleware(HandleGuestShareMiddleware::class);

        $response = $this->get('/shared/no-such-share');

        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Wrong password');
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

    public function test_expired_authenticated_share_cannot_stream_file_bytes(): void
    {
        $slug = 'expired-guest';
        $contents = 'expired-share-private-bytes';
        $file = LocalFile::where('filename', 'ace.txt')
            ->where('public_path', '')
            ->firstOrFail();
        $file->file_type = 'text';
        $file->save();
        file_put_contents($file->getPrivatePathNameForFile(), $contents);
        $this->createShare([$file->id], 'password', 1, $slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password')
            ->assertRedirect('/shared/' . $slug);

        $share = Share::whereBySlug($slug)->firstOrFail();
        $share->created_at = now()->subDays(2);
        $share->save();

        $response = $this->get(route('drive.fetch-file', ['id' => $file->id, 'slug' => $slug]));

        $response->assertRedirect(route('login', ['slug' => $slug]));
        $this->assertStringNotContainsString($contents, $response->getContent());
    }


    public function test_paused_share_cannot_stream_authenticated_guest_file_bytes(): void
    {
        $slug = 'paused-fetch';
        $contents = 'paused-share-private-bytes';
        $file = LocalFile::where('filename', 'ace.txt')
            ->where('public_path', '')
            ->firstOrFail();
        $file->file_type = 'text';
        $file->save();
        file_put_contents($file->getPrivatePathNameForFile(), $contents);
        $this->createShare([$file->id], 'password', 7, $slug);
        $shareId = $this->getSlugId($slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password')
            ->assertRedirect('/shared/' . $slug);

        $this->actingAs(\App\Models\User::firstOrFail());
        $this->post(route('drive.share-pause'), ['_token' => csrf_token(), 'id' => $shareId])
            ->assertSessionHas('status', true);
        $this->logout();

        $response = $this->get(route('drive.fetch-file', ['id' => $file->id, 'slug' => $slug]));

        $response->assertRedirect(route('login', ['slug' => $slug]));
        $this->assertStringNotContainsString($contents, $response->getContent());
    }

    public function test_deleted_share_cannot_stream_authenticated_guest_thumbnail_bytes(): void
    {
        $slug = 'deleted-thumb';
        $this->uploadImage('deleted-thumb.png');
        $file = LocalFile::where('filename', 'deleted-thumb.png')
            ->where('public_path', '')
            ->firstOrFail();
        $this->post('/gen-thumbs', ['_token' => csrf_token(), 'ids' => [$file->id]])
            ->assertRedirect(route('drive'));
        $thumbnailPath = app(ThumbnailService::class)->getFullFileThumbnailPath($file);
        $this->assertFileExists($thumbnailPath);
        $thumbnailContents = file_get_contents($thumbnailPath);
        $this->assertNotSame('', $thumbnailContents);
        $this->createShare([$file->id], 'password', 7, $slug);
        $shareId = $this->getSlugId($slug);
        $this->logout();

        $this->postCheckPassword($slug, 'password')
            ->assertRedirect('/shared/' . $slug);

        $this->actingAs(\App\Models\User::firstOrFail());
        $this->post(route('drive.share-delete'), ['_token' => csrf_token(), 'id' => $shareId])
            ->assertSessionHas('status', true);
        $this->assertDatabaseMissing('shares', ['id' => $shareId]);
        $this->logout();

        $response = $this->get(route('drive.get-thumb', ['id' => $file->id, 'slug' => $slug]));

        $response->assertRedirect(route('login', ['slug' => $slug]));
        $this->assertStringNotContainsString($thumbnailContents, $response->getContent());
    }


    protected function setUp(): void
    {
        $fileNames = [
            'ace.txt', 'beta.txt', 'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/c.txt', 'foo/bar/1.txt',
            'foo/bar/2.txt', 'barbar/1.txt', 'bar_/1.txt', 'barX/1.txt', 'bar%/1.txt', 'baranything/1.txt',
        ];
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadMultipleFiles('', $fileNames);
    }
}
