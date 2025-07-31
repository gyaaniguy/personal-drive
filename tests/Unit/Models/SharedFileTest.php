<?php

namespace Tests\Unit\Models;

use App\Models\LocalFile;
use App\Models\Share;
use App\Models\SharedFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mockery;
use ReflectionMethod;
use Tests\Feature\BaseFeatureTest;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SharedFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_array_inserts_shared_files()
    {
        // Create real LocalFile and Share instances
        $localFile1 = LocalFile::factory()->create();
        $localFile2 = LocalFile::factory()->create();
        $share = Share::factory()->create();

        $localFiles = new Collection([$localFile1, $localFile2]);

        $result = SharedFile::addArray($localFiles, $share->id);

        $this->assertTrue($result);

        // Assert that the shared files were inserted into the database
        $this->assertDatabaseHas('shared_files', [
            'share_id' => $share->id,
            'file_id' => $localFile1->id,
        ]);
        $this->assertDatabaseHas('shared_files', [
            'share_id' => $share->id,
            'file_id' => $localFile2->id,
        ]);
    }

    public function test_get_file_ids_returns_correct_array()
    {
        $shareId = 5;
        $mockLocalFile = Mockery::mock(LocalFile::class);
        $mockLocalFile->shouldReceive('getAttribute')->with('id')->andReturn(200);

        // Use reflection to call the private static method
        $method = new ReflectionMethod(SharedFile::class, 'getFileIds');
        $method->setAccessible(true);
        $result = $method->invoke(null, $shareId, $mockLocalFile);

        $this->assertEquals([
            'share_id' => $shareId,
            'file_id' => 200,
        ], $result);
    }

    public function test_share_relationship()
    {
        $sharedFile = new SharedFile();
        $relation = $sharedFile->share();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Share::class, $relation->getRelated());
    }

    public function test_local_file_relationship()
    {
        $sharedFile = new SharedFile();
        $relation = $sharedFile->localFile();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(LocalFile::class, $relation->getRelated());
        $this->assertEquals('file_id', $relation->getForeignKeyName());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
