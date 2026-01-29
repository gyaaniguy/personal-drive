<?php

namespace Tests\Unit\Models;

use App\Models\Share;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Models\LocalFile;
use App\Models\SharedFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

class LocalFileTest extends TestCase
{
    use RefreshDatabase;


    public function test_local_file_can_be_created_using_factory()
    {
        $localFile = LocalFile::factory()->create();
        $this->assertNotNull($localFile->id);
        $this->assertDatabaseHas('local_files', ['id' => $localFile->id]);
    }

    public function test_ulid_is_generated_on_creation()
    {
        $localFile = LocalFile::factory()->create();
        $this->assertTrue(Str::isUlid($localFile->id));
    }

    public function test_local_file_attributes_are_fillable()
    {
        $userData = User::factory()->create();
        $fileData = [
            'filename' => 'test_file.txt',
            'is_dir' => false,
            'public_path' => '/path/to/public',
            'private_path' => '/path/to/private',
            'size' => 1024,
            'user_id' => $userData->id,
            'file_type' => 'text',
        ];
        $localFile = LocalFile::create($fileData);

        $this->assertEquals('test_file.txt', $localFile->filename);
        $this->assertFalse($localFile->is_dir);
        $this->assertEquals('/path/to/public', $localFile->public_path);
        $this->assertEquals('/path/to/private', $localFile->private_path);
        $this->assertEquals(1024, $localFile->size);
        $this->assertEquals($userData->id, $localFile->user_id);
        $this->assertEquals('text', $localFile->file_type);
    }

    public function test_hidden_attributes_are_hidden()
    {
        $localFile = LocalFile::factory()->create();
        $fileArray = $localFile->toArray();

        $this->assertArrayNotHasKey('private_path', $fileArray);
        $this->assertArrayNotHasKey('user_id', $fileArray);
    }

    public function test_get_by_id_returns_correct_file()
    {
        $localFile = LocalFile::factory()->create();
        $foundFile = LocalFile::getById($localFile->id);
        $this->assertEquals($localFile->id, $foundFile->id);
    }

    public function test_set_has_thumbnail_updates_thumbnail_status()
    {
        $localFile1 = LocalFile::factory()->create(['has_thumbnail' => 0]);
        $localFile2 = LocalFile::factory()->create(['has_thumbnail' => 0]);

        LocalFile::setHasThumbnail([$localFile1->id, $localFile2->id]);

        $this->assertEquals(1, $localFile1->fresh()->has_thumbnail);
        $this->assertEquals(1, $localFile2->fresh()->has_thumbnail);
    }

    public function test_get_by_ids_returns_correct_files()
    {
        $localFile1 = LocalFile::factory()->create();
        $localFile2 = LocalFile::factory()->create();
        $localFile3 = LocalFile::factory()->create();

        $foundFiles = LocalFile::getByIds([$localFile1->id, $localFile3->id])->get();

        $this->assertCount(2, $foundFiles);
        $this->assertTrue($foundFiles->contains($localFile1));
        $this->assertTrue($foundFiles->contains($localFile3));
        $this->assertFalse($foundFiles->contains($localFile2));
    }

    public function test_insert_rows_upserts_data()
    {
        LocalFile::clearTable();

        $user = User::factory()->create();
        $insertArr = [
            [
                'filename' => 'file1.txt',
                'is_dir' => false,
                'public_path' => '/path/a',
                'private_path' => '/private/a',
                'size' => 100,
                'user_id' => $user->id,
                'file_type' => 'text',
            ],
            [
                'filename' => 'file2.txt',
                'is_dir' => false,
                'public_path' => '/path/b',
                'private_path' => '/private/b',
                'size' => 200,
                'user_id' => $user->id,
                'file_type' => 'text',
            ],
        ];

        LocalFile::insertRows($insertArr);
        $this->assertCount(2, LocalFile::all());

        // Update one
        $insertArr[0]['size'] = 150;
        LocalFile::insertRows([$insertArr[0]]);
        $this->assertEquals(150, LocalFile::where('filename', 'file1.txt')->first()->size);
    }

    public function test_clear_table_truncates_table()
    {
        LocalFile::clearTable();

        LocalFile::factory()->count(5)->create();
        $this->assertCount(5, LocalFile::all());

        LocalFile::clearTable();
        $this->assertCount(0, LocalFile::all());
    }

    public function test_get_files_for_public_path_returns_correct_files()
    {
        $user = User::factory()->create();
        LocalFile::factory()->create(['public_path' => '/root', 'filename' => 'z_file.txt', 'user_id' => $user->id]);
        $file2 = LocalFile::factory()->create(
            [
            'public_path' => '/root', 'filename' => 'a_file.txt', 'user_id' => $user->id
            ]
        );
        LocalFile::factory()->create(['public_path' => '/other', 'user_id' => $user->id]);

        $files = LocalFile::getFilesForPublicPath('/root');

        $this->assertCount(2, $files);
        $this->assertEquals('z_file.txt', $files->first()->filename); // Ordered by filename desc
        $this->assertEquals('a_file.txt', $files->last()->filename);
    }

    public function test_modify_file_collection_for_drive_adds_size_text()
    {
        $file = LocalFile::factory()->create(['size' => 1024]);
        $collection = new Collection([$file]);

        $modifiedCollection = LocalFile::modifyFileCollectionForDrive($collection);
        $this->assertEquals('1 KB', $modifiedCollection->first()->sizeText);
    }

    public function test_get_item_size_text_formats_size()
    {
        $file = LocalFile::factory()->make(['size' => 2048, 'is_dir' => false]);
        $this->assertEquals('2 KB', LocalFile::getItemSizeText($file));

        $dir = LocalFile::factory()->make(['size' => 0, 'is_dir' => true]);
        $this->assertEquals('', LocalFile::getItemSizeText($dir));
    }

    public function test_modify_file_collection_for_guest_modifies_public_path()
    {
        $file = LocalFile::factory()->create(['public_path' => 'shared/folder']);
        $collection = new Collection([$file]);

        $modifiedCollection = LocalFile::modifyFileCollectionForGuest($collection, '/shared');
        $this->assertEquals('folder/', $modifiedCollection->first()->public_path);
    }

    public function test_search_files_returns_matching_files()
    {
        $user = User::factory()->create();
        LocalFile::factory()->create(['filename' => 'document.pdf', 'user_id' => $user->id]);
        LocalFile::factory()->create(['filename' => 'image.jpg', 'user_id' => $user->id]);
        LocalFile::factory()->create(['filename' => 'my_document.docx', 'user_id' => $user->id]);

        $results = LocalFile::searchFiles('doc');
        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('filename', 'document.pdf'));
        $this->assertTrue($results->contains('filename', 'my_document.docx'));
    }

    public function test_get_ids_by_like_public_path_returns_correct_ids()
    {
        $user = User::factory()->create();
        $file1 = LocalFile::factory()->create(['public_path' => '/folder/sub', 'user_id' => $user->id]);
        $file2 = LocalFile::factory()->create(['public_path' => '/folder/another', 'user_id' => $user->id]);
        LocalFile::factory()->create(['public_path' => '/other', 'user_id' => $user->id]);

        $ids = LocalFile::getIdsByLikePublicPath('/folder');
        $this->assertCount(2, $ids);
        $this->assertContains($file1->id, $ids);
        $this->assertContains($file2->id, $ids);
    }

    public function test_get_by_public_path_like_search_returns_correct_builder()
    {
        $user = User::factory()->create();
        $file1 = LocalFile::factory()->create(['public_path' => '/folder/sub', 'user_id' => $user->id]);
        $file2 = LocalFile::factory()->create(['public_path' => '/folder/another', 'user_id' => $user->id]);

        $builder = LocalFile::getByPublicPathLikeSearch('/folder');
        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertCount(2, $builder->get());
    }

    public function test_get_for_file_obj_returns_correct_file()
    {
        $user = User::factory()->create();
        $localFile = LocalFile::factory()->create(
            [
            'filename' => 'test.txt',
            'public_path' => '/test/path',
            'user_id' => $user->id
            ]
        );

        $mockSplFileInfo = Mockery::mock(SplFileInfo::class);
        $mockSplFileInfo->shouldReceive('getFilename')->andReturn('test.txt');
        $mockSplFileInfo->shouldReceive('getRelativePath')->andReturn('/test/path');

        $foundFile = LocalFile::getForFileObj($mockSplFileInfo);
        $this->assertEquals($localFile->id, $foundFile->id);
    }

    public function test_shared_files_relationship()
    {
        $localFile = LocalFile::factory()->create();
        $share = Share::factory()->create();
        $sharedFile = SharedFile::factory()->create(['file_id' => $localFile->id, 'share_id' => $share->id]);

        $this->assertInstanceOf(Collection::class, $localFile->fresh()->sharedFiles);
        $this->assertTrue(
            $localFile->fresh()->sharedFiles->where('share_id', $sharedFile->share_id)->where(
                'file_id',
                $sharedFile->file_id
            )->isNotEmpty()
        );
    }

    public function test_delete_using_public_path_deletes_correct_files()
    {
        $user = User::factory()->create();
        $parentDir = LocalFile::factory()->create(
            [
            'filename' => 'parent_dir',
            'is_dir' => true,
            'public_path' => '/root',
            'user_id' => $user->id
            ]
        );
        $childFile = LocalFile::factory()->create(
            [
            'filename' => 'child_file.txt',
            'is_dir' => false,
            'public_path' => '/root/parent_dir',
            'user_id' => $user->id
            ]
        );
        $otherFile = LocalFile::factory()->create(
            [
            'filename' => 'other_file.txt',
            'is_dir' => false,
            'public_path' => '/root',
            'user_id' => $user->id
            ]
        );

        $parentDir->deleteUsingPublicPath();

        $this->assertDatabaseMissing('local_files', ['id' => $childFile->id]);
        $this->assertDatabaseHas('local_files', ['id' => $otherFile->id]);
    }

    public function test_get_public_pathname_returns_correct_path()
    {
        $localFile = LocalFile::factory()->make(
            [
            'public_path' => '/my/folder',
            'filename' => 'my_file.doc',
            ]
        );
        $this->assertEquals('/my/folder' . DS . 'my_file.doc', $localFile->getPublicPathPlusName());
    }

    public function test_is_valid_file_returns_false_for_directory()
    {
        $localFile = LocalFile::factory()->make(
            [
            'private_path' => '/tmp',
            'filename' => 'valid_dir',
            'is_dir' => true,
            ]
        );

        // Mock global functions
        $mockIsFile = Mockery::mock('alias:is_file');
        $mockIsFile->shouldReceive('is_file')
            ->with('/tmp' . DS . 'valid_dir')
            ->andReturn(false);

        $this->assertFalse($localFile->isValidFile());
    }

    public function test_get_private_pathname_for_file_returns_correct_path()
    {
        $localFile = LocalFile::factory()->make(
            [
            'private_path' => '/private/folder',
            'filename' => 'secret.txt',
            ]
        );
        $this->assertEquals(
            '/private/folder' . DS . 'secret.txt',
            $localFile->getPrivatePathNameForFile()
        );
    }

    public function test_is_valid_dir_returns_false_for_file()
    {
        $localFile = LocalFile::factory()->make(
            [
            'private_path' => '/tmp',
            'filename' => 'valid_file.txt',
            'is_dir' => false,
            ]
        );

        // Mock global functions
        Mockery::mock('alias:is_dir')
            ->shouldReceive('is_dir')
            ->with('/tmp' . DS . 'valid_file.txt')
            ->andReturn(false);

        $this->assertFalse($localFile->isValidDir());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
