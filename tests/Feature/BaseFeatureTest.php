<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UUIDService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BaseFeatureTest extends TestCase
{
    public string $storageFilesUUID;
    protected UUIDService $uuidService;

    public function uploadMultipleFiles(
        $testPath = '',
        $fileNames = [
            'ace.txt', 'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/c.txt', 'foo/bar/1.txt', 'foo/bar/2.txt',
            'foo/bar/3.txt'
        ]
    ): TestResponse {
        $files = [];
        foreach ($fileNames as $fileName) {
            $files[] = UploadedFile::fake()->create($fileName, 100);
        }
        return $this->postUpload($files, $testPath);
    }

    public function postUpload(array $files, string $testPath): TestResponse
    {
        $response = $this->post(route('drive.upload'), [
            '_token' => csrf_token(),
            'files' => $files,
            'path' => $testPath
        ]);

        $response->assertSessionHas('status', true);
        $this->assertTrue(collect($files)->every(fn($file) => Storage::disk('local')->exists(
            $this->storageFilesUUID . DIRECTORY_SEPARATOR . ($testPath ? $testPath . DIRECTORY_SEPARATOR : '') . $file->getClientOriginalPath()
        )));

        return $response;
    }

    public function upload_file(
        string $testPath = '/foo/bar',
        string $name = 'dummy.txt',
        int $size = 100
    ): TestResponse {
        $file = UploadedFile::fake()->create($name, $size);
        return $this->postUpload([$file], $testPath);
    }

    public function setupStoragePathPost(string $storagePath = ''): TestResponse
    {
        Storage::fake('local');
        if (!$storagePath) {
            $storagePath = Storage::disk('local')->path('');
            $storagePath = substr($storagePath, 0, strlen($storagePath) - 1);
        }

        $this->get(route('admin-config', ['setupMode' => '1']));
        return $this->post(route('admin-config.update'), [
            '_token' => csrf_token(),
            'storage_path' => $storagePath
        ]);
    }

    protected function setup(): void
    {
        parent::setup();
        $this->uuidService = app(UUIDService::class);
        $this->storageFilesUUID = $this->uuidService->getStorageFilesUUID();
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
}
