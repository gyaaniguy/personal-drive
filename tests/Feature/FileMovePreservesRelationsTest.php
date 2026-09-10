<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\LocalFile;
use App\Models\Share;
use App\Models\SharedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

class FileMovePreservesRelationsTest extends BaseFeatureTest
{
    public function test_move_file_preserves_favorite(): void
    {
        $this->uploadMultipleFiles('', ['bar/1.txt', 'foo/.keep']);
        $file = LocalFile::where('filename', '1.txt')->where('public_path', 'bar')->firstOrFail();
        $fileId = $file->id;

        Favorite::create([
            'user_id' => auth()->user()->id,
            'local_file_id' => $file->id,
        ]);
        $this->assertDatabaseCount('favorites', 1);

        $response = $this->postMoveFiles([$file->id], 'foo');
        $response->assertSessionHas('status', true);

        $this->assertDatabaseCount('favorites', 1);
        $fav = Favorite::first();
        $this->assertSame($fileId, $fav->local_file_id);

        $movedFile = LocalFile::find($fileId);
        $this->assertNotNull($movedFile);
        $this->assertSame('foo', $movedFile->public_path);
    }

    public function test_move_file_preserves_share(): void
    {
        $this->uploadMultipleFiles('', ['bar/1.txt', 'foo/.keep']);
        $file = LocalFile::where('filename', '1.txt')->where('public_path', 'bar')->firstOrFail();
        $fileId = $file->id;

        $share = Share::create(['slug' => 'test-slug', 'password' => '', 'expiry' => 30]);
        DB::table('shared_files')->insert(['share_id' => $share->id, 'file_id' => $file->id]);
        $this->assertDatabaseCount('shared_files', 1);

        $response = $this->postMoveFiles([$file->id], 'foo');
        $response->assertSessionHas('status', true);

        $this->assertDatabaseCount('shared_files', 1);
        $sf = SharedFile::first();
        $this->assertSame($fileId, $sf->file_id);

        $movedFile = LocalFile::find($fileId);
        $this->assertNotNull($movedFile);
        $this->assertSame('foo', $movedFile->public_path);
    }

    public function test_move_directory_preserves_favorites_and_shares(): void
    {
        $this->uploadMultipleFiles('', ['mydir/child.txt', 'dest/.keep']);
        $dir = LocalFile::where('filename', 'mydir')->where('is_dir', true)->firstOrFail();
        $child = LocalFile::where('filename', 'child.txt')->where('public_path', 'mydir')->firstOrFail();
        $dirId = $dir->id;
        $childId = $child->id;

        Favorite::create(['user_id' => auth()->user()->id, 'local_file_id' => $dir->id]);
        $share = Share::create(['slug' => 'dir-slug', 'password' => '', 'expiry' => 30]);
        DB::table('shared_files')->insert(['share_id' => $share->id, 'file_id' => $child->id]);

        $response = $this->postMoveFiles([$dir->id], 'dest');
        $response->assertSessionHas('status', true);

        $this->assertDatabaseCount('favorites', 1);
        $this->assertSame($dirId, Favorite::first()->local_file_id);

        $this->assertDatabaseCount('shared_files', 1);
        $this->assertSame($childId, SharedFile::first()->file_id);

        $movedDir = LocalFile::find($dirId);
        $this->assertSame('dest', $movedDir->public_path);

        $movedChild = LocalFile::find($childId);
        $this->assertSame('dest/mydir', $movedChild->public_path);
    }

    private function postMoveFiles(array $fileIds, string $path): TestResponse
    {
        return $this->post(route('drive.move-files'), [
            '_token' => csrf_token(),
            'fileList' => $fileIds,
            'path' => $path,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
