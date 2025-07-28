<?php

namespace Tests\Helpers;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

trait SetupSite
{

    public function postUpload(array $filesArray, string $testPath): TestResponse
    {
        return $this->post(route('drive.upload'), [
            '_token' => csrf_token(),
            'files' => $filesArray,
            'path' => $testPath
        ]);
    }

    public function create_upload_file_success($testPath = '/foo/bar', $fileName = 'dummy.txt')
    {
        $file = UploadedFile::fake()->create($fileName, 100);

        $response = $this->postUpload([$file], $testPath);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Files uploaded'));
        Storage::disk('local')->assertExists($this->storageFilesUUID . DIRECTORY_SEPARATOR . $testPath . DIRECTORY_SEPARATOR . $fileName);
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
