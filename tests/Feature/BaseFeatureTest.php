<?php

namespace Tests\Feature;

use App\Models\LocalFile;
use App\Models\Share;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class BaseFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function uploadMultipleFiles(
        $testPath = '',
        $fileNames = [
            'ace.txt', 'beta.txt', 'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/c.txt', 'foo/bar/1.txt',
            'foo/bar/2.txt'
        ]
    ): TestResponse {
        $files = $this->getFilesForFileNames($fileNames);
        return $this->postUpload($files, $testPath);
    }

    public function getFilesForFileNames(mixed $fileNames): array
    {
        $files = [];
        foreach ($fileNames as $fileName) {
            $files[] = UploadedFile::fake()->create($fileName, 100);
        }
        return $files;
    }

    public function postUpload(array $files, string $testPath): TestResponse
    {
        $response = $this->post(route('drive.upload'), [
            '_token' => csrf_token(),
            'files' => $files,
            'path' => $testPath
        ]);

        $response->assertSessionHas('status', true);
        $this->assertFilesExist($files, $testPath);

        return $response;
    }

    public function assertFilesExist(array $files, string $testPath): void
    {
        $this->assertTrue(collect($files)->every(fn($file) => Storage::disk('local')->exists(
            CONTENT_SUBDIR . DS . ($testPath ? $testPath . DS : '') . $file->getClientOriginalPath()
        )));
    }

    public function postCheckPassword(string $slug, string $password): TestResponse
    {
        return $this->post(route('shared.check-password'), [
            '_token' => csrf_token(),
            'slug' => $slug,
            'password' => $password,
        ]);
    }

    public function uploadFile(
        string $testPath = '/foo/bar',
        string $name = 'dummy.txt',
        int $size = 100
    ): TestResponse {
        $file = UploadedFile::fake()->create($name, $size);
        return $this->postUpload([$file], $testPath);
    }

    public function setupStoragePathPost(string $storagePath = ''): TestResponse
    {
        $response = $this->setStoragePath($storagePath);
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Storage path updated successfully');
        return $response;
    }

    protected function uploadImage(string $fileName){
        $file = UploadedFile::fake()->image($fileName);

        return $this->postUpload([$file], '');
    }

    public function setStoragePath(string $storagePath = ''): TestResponse
    {
        $storagePath = $this->getFakeLocalStoragePath($storagePath);

        $this->get(route('admin-config', ['setupMode' => '1']));
        return $this->post(route('admin-config.update'), [
            '_token' => csrf_token(),
            'storage_path' => $storagePath
        ]);
    }

    public function getFakeLocalStoragePath(string $storagePath): string
    {
        return Storage::disk('local')->path($storagePath);
    }

    public function logout(): void
    {
        $this->post(route('logout'), [
            '_token' => csrf_token(),
        ]);
    }

    public function getSlugId(string $slug): mixed
    {
        return Share::where('slug', $slug)->pluck('id')->first();
    }

    public function createMultipleShares(array $slugs): void
    {
        list($toShareFileIds) = $this->getDataForMakingShare();

        foreach ($slugs as $slug) {
            $this->createShare($toShareFileIds, 'password', 13, $slug);
        }
    }

    public function getDataForMakingShare($password = 'password', $expiry = 13, $numFilesToShare = 2): array
    {
        $allFiles = LocalFile::all();
        $allFiles[0]->file_type = 'text';
        $allFiles[0]->save();
        $toShareFileIds = $allFiles->slice(0, $numFilesToShare)->pluck('id')->toArray();
        return array($toShareFileIds, $password, $expiry);
    }

    public function createShare(
        array $toShareFileIds,
        string $password = '',
        int $expiry = -1,
        string $slug = ''
    ): TestResponse {
        $postData = [
            '_token' => csrf_token(),
            'fileList' => $toShareFileIds,
        ];
        if ($password) {
            $postData['password'] = $password;
        }
        if ($expiry !== -1) {
            $postData['expiry'] = $expiry;
        }
        if ($slug) {
            $postData['slug'] = $slug;
        }

        return $this->post(route('drive.share-files'), $postData);
    }

    protected function setup(): void
    {
        parent::setup();
        Storage::fake('local');
    }

    protected function makeUserUsingSetup(): void
    {
        $this->withSession([]);

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:fresh', ['--force' => true]);

        $response = $this->setupAccountPost();
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'username' => 'testuser',
            'is_admin' => 1,
        ]);

        $response->assertRedirect(route('admin-config', ['setupMode' => true]));
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Created User successfully');
    }

    public function setupAccountPost($password = 'password'): TestResponse
    {
        return $this->post(route('setup.account'), [
            '_token' => csrf_token(),
            'username' => 'testuser',
            'password' => $password,
        ]);
    }

    protected function makeUser(bool $isAdmin = true): User
    {
        $user = User::create([
            'username' => 'testuser',
            'is_admin' => $isAdmin,
            'password' => 'password',
        ]);
        $this->actingAs($user);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        return $user;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        Storage::disk('local')->deleteDirectory('');
    }
}
