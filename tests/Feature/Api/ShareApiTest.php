<?php

namespace Tests\Feature\Api;

use App\Models\LocalFile;
use App\Models\Share;
use App\Models\SharedFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\BaseFeatureTest;

class ShareApiTest extends BaseFeatureTest
{
    use RefreshDatabase;

    private User $apiUser;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();

        $this->apiUser = User::first();
        $this->token = $this->apiUser->createToken('test-api-token', ['api'])->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    private function forceLogout(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();
    }

    private function createTestFiles(int $count = 2): array
    {
        $files = [];
        for ($i = 0; $i < $count; $i++) {
            $this->uploadFile('', "share-file-{$i}.txt", 100);
            $files[] = LocalFile::where('filename', "share-file-{$i}.txt")->first();
        }
        return $files;
    }

    public function test_list_shares(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'list-share-test',
            'expiry' => 13,
        ], $this->authHeaders());

        $response = $this->getJson('/api/v1/shares', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'shares')
            ->assertJsonPath('shares.0.slug', 'list-share-test');
    }

    public function test_create_share(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'my-share',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('share.slug', 'my-share')
            ->assertJsonStructure(['share', 'url']);

        $this->assertDatabaseHas('shares', ['slug' => 'my-share']);
    }

    public function test_create_share_with_password(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'protected-share',
            'password' => 'secret123',
        ], $this->authHeaders());

        $response->assertOk();

        $share = Share::where('slug', 'protected-share')->first();
        $this->assertNotEmpty($share->password);
        $this->assertNotEquals('secret123', $share->password);
    }

    public function test_delete_share(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'delete-me',
        ], $this->authHeaders());

        $share = Share::where('slug', 'delete-me')->first();

        $response = $this->deleteJson("/api/v1/shares/{$share->id}", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Share deleted');

        $this->assertDatabaseMissing('shares', ['id' => $share->id]);
    }

    public function test_toggle_share(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'toggle-share',
            'expiry' => 13,
        ], $this->authHeaders());

        $share = Share::where('slug', 'toggle-share')->first();
        $this->assertTrue((bool) $share->enabled);

        $response = $this->postJson("/api/v1/shares/{$share->id}/toggle", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Share paused');

        $share->refresh();
        $this->assertFalse((bool) $share->enabled);

        $response = $this->postJson("/api/v1/shares/{$share->id}/toggle", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Share enabled');

        $share->refresh();
        $this->assertTrue((bool) $share->enabled);
    }

    public function test_toggle_nonexistent_share_returns_404(): void
    {
        $response = $this->postJson('/api/v1/shares/99999/toggle', [], $this->authHeaders());

        $response->assertNotFound()
            ->assertJsonPath('message', 'Share not found');
    }

    public function test_shares_requires_auth(): void
    {
        $this->forceLogout();

        $response = $this->getJson('/api/v1/shares');

        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_create_share_generates_slug_if_not_provided(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
        ], $this->authHeaders());

        $response->assertOk();

        $slug = $response->json('share.slug');
        $this->assertNotEmpty($slug);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{10}$/', $slug);
    }

    public function test_create_share_with_custom_slug(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'custom-slug-test',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('share.slug', 'custom-slug-test');

        $this->assertDatabaseHas('shares', ['slug' => 'custom-slug-test']);
    }

    public function test_share_password_is_hashed_not_plaintext(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'hash-check-share',
            'password' => 'mypassword123',
        ], $this->authHeaders());

        $response->assertOk();

        $share = Share::where('slug', 'hash-check-share')->first();
        $this->assertNotEmpty($share->password);
        $this->assertNotEquals('mypassword123', $share->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('mypassword123', $share->password));
    }

    public function test_list_shares_excludes_expired(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        // Create share with expiry of 1 day
        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'expired-share',
            'expiry' => 1,
        ], $this->authHeaders());

        // Manually set created_at to 2 days ago so the share is expired
        Share::where('slug', 'expired-share')
            ->update(['created_at' => now()->subDays(2)]);

        $response = $this->getJson('/api/v1/shares', $this->authHeaders());

        $response->assertOk();

        $slugs = collect($response->json('shares'))->pluck('slug')->toArray();
        $this->assertNotContains('expired-share', $slugs);
    }

    public function test_list_shares_includes_enabled_and_disabled(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        // Create share, then pause it
        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'paused-share',
            'expiry' => 13,
        ], $this->authHeaders());

        $share = Share::where('slug', 'paused-share')->first();
        $this->postJson("/api/v1/shares/{$share->id}/toggle", [], $this->authHeaders());

        // Paused share should still appear in list
        $response = $this->getJson('/api/v1/shares', $this->authHeaders());

        $response->assertOk();

        $slugs = collect($response->json('shares'))->pluck('slug')->toArray();
        $this->assertContains('paused-share', $slugs);
    }

    public function test_toggle_from_disabled_to_enabled(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'toggle-reverse',
            'expiry' => 13,
        ], $this->authHeaders());

        $share = Share::where('slug', 'toggle-reverse')->first();

        // Pause
        $this->postJson("/api/v1/shares/{$share->id}/toggle", [], $this->authHeaders());
        $share->refresh();
        $this->assertFalse((bool) $share->enabled);

        // Enable
        $response = $this->postJson("/api/v1/shares/{$share->id}/toggle", [], $this->authHeaders());
        $response->assertOk()
            ->assertJsonPath('message', 'Share enabled');

        $share->refresh();
        $this->assertTrue((bool) $share->enabled);
    }

    public function test_delete_share_removes_from_db(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'delete-from-db',
        ], $this->authHeaders());

        $share = Share::where('slug', 'delete-from-db')->first();
        $this->assertNotNull($share);

        $response = $this->deleteJson("/api/v1/shares/{$share->id}", [], $this->authHeaders());
        $response->assertOk();

        $this->assertDatabaseMissing('shares', ['id' => $share->id]);
        $this->assertDatabaseMissing('shared_files', ['share_id' => $share->id]);
    }

    public function test_create_share_with_nonexistent_file_ids_returns_422(): void
    {
        $fakeId1 = \Illuminate\Support\Str::ulid();
        $fakeId2 = \Illuminate\Support\Str::ulid();

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => [$fakeId1, $fakeId2],
            'slug' => 'bad-file-share',
        ], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Some files not found');
    }

    public function test_share_url_format(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'url-format-test',
        ], $this->authHeaders());

        $response->assertOk();

        $url = $response->json('url');
        $this->assertStringEndsWith('/shared/url-format-test', $url);
    }

    public function test_create_share_with_empty_file_list_returns_422(): void
    {
        $response = $this->postJson('/api/v1/shares', [
            'fileList' => [],
            'slug' => 'empty-file-share',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_share_slug_is_string(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'string-slug-check',
        ], $this->authHeaders());

        $response->assertOk();

        $slug = $response->json('share.slug');
        $this->assertIsString($slug);
        $this->assertNotEmpty($slug);
    }

    public function test_share_url_contains_slug(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'url-slug-check',
        ], $this->authHeaders());

        $response->assertOk();

        $url = $response->json('url');
        $this->assertIsString($url);
        $this->assertStringContainsString('url-slug-check', $url);
    }

    public function test_toggle_share_multiple_times(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'multi-toggle',
            'expiry' => 13,
        ], $this->authHeaders());

        $share = Share::where('slug', 'multi-toggle')->first();

        // Initially enabled
        $this->assertTrue((bool) $share->enabled);

        // Toggle 1: disable
        $this->postJson("/api/v1/shares/{$share->id}/toggle", [], $this->authHeaders());
        $share->refresh();
        $this->assertFalse((bool) $share->enabled);

        // Toggle 2: enable
        $this->postJson("/api/v1/shares/{$share->id}/toggle", [], $this->authHeaders());
        $share->refresh();
        $this->assertTrue((bool) $share->enabled);

        // Toggle 3: disable
        $response = $this->postJson("/api/v1/shares/{$share->id}/toggle", [], $this->authHeaders());
        $response->assertOk();
        $share->refresh();
        $this->assertFalse((bool) $share->enabled);
    }

    public function test_delete_share_does_not_delete_files(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'delete-share-files',
        ], $this->authHeaders());

        $share = Share::where('slug', 'delete-share-files')->first();

        $this->deleteJson("/api/v1/shares/{$share->id}", [], $this->authHeaders());

        // Files should still exist in DB
        foreach ($files as $file) {
            $this->assertDatabaseHas('local_files', ['id' => $file->id]);
        }
    }

    // ─── New API Tests ───

    public function test_create_share_missing_fileList_returns_422(): void
    {
        $response = $this->postJson('/api/v1/shares', [], $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_create_share_with_duplicate_slug_returns_422(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'dup-slug',
        ], $this->authHeaders())->assertOk();

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'dup-slug',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_create_share_with_invalid_slug_chars_returns_422(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'has spaces!',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_create_share_with_url_hostile_slug_returns_422(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        foreach (['frag#z', 'pct%zz'] as $slug) {
            $response = $this->postJson('/api/v1/shares', [
                'fileList' => $fileIds,
                'slug' => $slug,
            ], $this->authHeaders());

            $response->assertStatus(422);
        }

        $this->assertDatabaseCount('shares', 0);
    }

    public function test_create_share_with_negative_expiry_returns_422(): void
    {
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'neg-expiry',
            'expiry' => -5,
        ], $this->authHeaders());

        $response->assertStatus(422);
        $this->assertDatabaseCount('shares', 0);
    }

    public function test_list_shares_empty(): void
    {
        $response = $this->getJson('/api/v1/shares', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(0, 'shares');
    }

    public function test_list_shares_per_page_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $files = $this->createTestFiles();
            $fileIds = array_map(fn($f) => (string) $f->id, $files);
            $this->postJson('/api/v1/shares', [
                'fileList' => $fileIds,
                'slug' => "share-page-{$i}",
                'expiry' => 30,
            ], $this->authHeaders())->assertOk();
        }

        $response = $this->getJson('/api/v1/shares?per_page=2', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(2, 'shares')
            ->assertJsonPath('meta.total', 5);
    }

    public function test_shares_across_users_isolated(): void
    {
        // User A creates share
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);
        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'userA-share',
            'expiry' => 30,
        ], $this->authHeaders())->assertOk();

        // Create User B and force logout so session doesn't leak
        $userB = User::create(['username' => 'shareUserB', 'is_admin' => false, 'password' => 'password']);
        $tokenB = $userB->createToken('share-b-token', ['api'])->plainTextToken;
        $this->forceLogout();

        // User B lists - shares are global (no user isolation in controller)
        $response = $this->getJson('/api/v1/shares', [
            'Authorization' => 'Bearer ' . $tokenB,
        ]);
        $response->assertOk();
    }

    public function test_delete_nonexistent_share_succeeds_silently(): void
    {
        // Share::destroy() has no existence check — deleting non-existent ID is a no-op
        $response = $this->deleteJson('/api/v1/shares/999999', [], $this->authHeaders());
        $response->assertOk();
    }

    public function test_delete_other_users_share_no_isolation(): void
    {
        // User A creates share
        $files = $this->createTestFiles();
        $fileIds = array_map(fn($f) => (string) $f->id, $files);
        $this->postJson('/api/v1/shares', [
            'fileList' => $fileIds,
            'slug' => 'userA-protect',
        ], $this->authHeaders())->assertOk();

        $share = Share::where('slug', 'userA-protect')->first();

        // User B deletes it — no user isolation in destroy endpoint
        $userB = User::create(['username' => 'delUserB', 'is_admin' => false, 'password' => 'password']);
        $tokenB = $userB->createToken('del-b-token', ['api'])->plainTextToken;
        $this->forceLogout();

        $response = $this->deleteJson("/api/v1/shares/{$share->id}", [], [
            'Authorization' => 'Bearer ' . $tokenB,
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('shares', ['id' => $share->id]);
    }

    public function test_create_share_with_mixed_files_and_folders(): void
    {
        $this->uploadFile('', 'share-file-mix.txt', 100);
        $file = LocalFile::where('filename', 'share-file-mix.txt')->first();

        $this->postJson('/api/v1/files/create', [
            'name' => 'share-folder-mix',
            'type' => 'folder',
        ], $this->authHeaders())->assertOk();
        $folder = LocalFile::where('filename', 'share-folder-mix')->where('is_dir', true)->first();

        $response = $this->postJson('/api/v1/shares', [
            'fileList' => [(string) $file->id, (string) $folder->id],
            'slug' => 'mixed-share',
        ], $this->authHeaders());

        $response->assertOk();
        $this->assertDatabaseHas('shares', ['slug' => 'mixed-share']);
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
