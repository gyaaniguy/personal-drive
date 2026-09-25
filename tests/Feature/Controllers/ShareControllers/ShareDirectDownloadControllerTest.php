<?php

namespace Tests\Feature\Controllers\ShareControllers;

use App\Models\LocalFile;
use App\Models\Share;
use Illuminate\Testing\TestResponse;
use Tests\Feature\BaseFeatureTest;

class ShareDirectDownloadControllerTest extends BaseFeatureTest
{
    public function test_create_direct_share_returns_dl_link(): void
    {
        $file = LocalFile::getByName('ace.txt')->firstOrFail();

        $response = $this->createDirectShare([$file->id]);

        $response->assertSessionHas('shared_link', fn($value) => str_ends_with($value, '/download/direct-slug'));
        $this->assertTrue(Share::whereBySlug('direct-slug')->firstOrFail()->direct);
    }

    public function test_direct_share_rejects_folder(): void
    {
        $folder = LocalFile::where('is_dir', true)->firstOrFail();

        $response = $this->createDirectShare([$folder->id]);

        $response->assertSessionHas('message', 'Direct links work only for a single file');
        $this->assertDatabaseCount('shares', 0);
    }

    public function test_direct_share_rejects_multiple_files(): void
    {
        $fileIds = LocalFile::where('is_dir', false)->limit(2)->pluck('id')->all();

        $response = $this->createDirectShare($fileIds);

        $response->assertSessionHas('message', 'Direct links work only for a single file');
        $this->assertDatabaseCount('shares', 0);
    }

    public function test_download_without_password(): void
    {
        $file = LocalFile::getByName('ace.txt')->firstOrFail();
        $this->createDirectShare([$file->id]);
        $this->post(route('logout'));

        $response = $this->get('/download/direct-slug');

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('ace.txt', $response->headers->get('Content-Disposition'));
    }

    public function test_download_with_password_uses_basic_auth(): void
    {
        $file = LocalFile::getByName('ace.txt')->firstOrFail();
        $this->createDirectShare([$file->id], 'secret-pass');
        $this->post(route('logout'));

        $this->get('/download/direct-slug')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Basic realm="Download", charset="UTF-8"');

        $this->withBasicAuth('anyone', 'wrong-pass')->get('/download/direct-slug')->assertStatus(401);

        $this->withBasicAuth('anyone', 'secret-pass')->get('/download/direct-slug')->assertOk();
    }

    public function test_paused_or_normal_share_not_downloadable(): void
    {
        $file = LocalFile::getByName('ace.txt')->firstOrFail();
        $this->createDirectShare([$file->id]);
        $this->createShare([$file->id], '', -1, 'page-slug');
        Share::whereBySlug('direct-slug')->update(['enabled' => false]);

        $this->get('/download/direct-slug')->assertNotFound();
        $this->get('/download/page-slug')->assertNotFound();
    }

    public function test_expired_direct_share_not_downloadable(): void
    {
        $file = LocalFile::getByName('ace.txt')->firstOrFail();
        $this->createDirectShare([$file->id]);
        Share::whereBySlug('direct-slug')->update(['expiry' => 1, 'created_at' => now()->subDays(2)]);
        $this->post(route('logout'));

        $this->get('/download/direct-slug')->assertNotFound();
    }

    public function test_share_list_marks_direct_share(): void
    {
        $file = LocalFile::getByName('ace.txt')->firstOrFail();
        $this->createDirectShare([$file->id]);

        $this->get('shares-all')->assertInertia(
            fn($page) => $page->where('shares.0.slug', 'direct-slug')->where('shares.0.direct', true)
        );
    }

    private function createDirectShare(array $fileIds, string $password = ''): TestResponse
    {
        $postData = [
            '_token' => csrf_token(),
            'fileList' => $fileIds,
            'slug' => 'direct-slug',
            'direct' => true,
        ];
        if ($password) {
            $postData['password'] = $password;
        }

        return $this->post(route('drive.share-files'), $postData);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadMultipleFiles('');
    }
}
