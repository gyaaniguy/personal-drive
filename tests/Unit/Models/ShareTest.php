<?php

namespace Tests\Unit\Models;

use App\Models\LocalFile;
use App\Models\Share;
use App\Models\SharedFile;
use Database\Factories\LocalFileFactory;
use Database\Factories\ShareFactory;
use Database\Factories\SharedFileFactory;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_can_be_created()
    {
        $share = Share::add('test-slug', 'password123', 7, '/public/path');

        $this->assertNotNull($share->id);
        $this->assertEquals('test-slug', $share->slug);
        $this->assertEquals('password123', $share->password);
        $this->assertEquals(7, $share->expiry);
        $this->assertEquals('/public/path', $share->public_path);
        $this->assertDatabaseHas('shares', ['slug' => 'test-slug']);
    }

    public function test_get_all_unexpired_returns_only_unexpired_shares()
    {
        // Set a fixed "now" for the test
        Carbon::setTestNow(Carbon::now());

        Share::factory()->expired()->create();
        $unexpiredShare = Share::factory()->unexpired()->create();

        $unexpiredShares = Share::getAllUnExpired();

        $this->assertCount(1, $unexpiredShares);
        $this->assertTrue($unexpiredShares->contains('slug', $unexpiredShare->slug));
        $this->assertFalse($unexpiredShares->contains('slug', 'expired-slug'));

        // Reset Carbon's "now"
        Carbon::setTestNow(null);
    }

    public function test_where_by_slug_returns_correct_share()
    {
        $share = Share::add('find-me');
        $foundShare = Share::whereBySlug('find-me')->first();
        $this->assertEquals($share->id, $foundShare->id);
    }

    public function test_where_by_id_returns_correct_share()
    {
        $share = Share::add('find-by-id');
        $foundShare = Share::whereById($share->id)->first();
        $this->assertEquals($share->id, $foundShare->id);
    }

     public function test_get_expiry_time_attribute_formats_correctly()
    {
        $share = Share::add('expiry-test', null, 5);
        // Mock Carbon to control the created_at time for consistent testing
        Carbon::setTestNow(Carbon::parse('2025-01-01 10:00:00'));
        $share->created_at = Carbon::parse('2025-01-01 10:00:00');

        $expectedExpiryTime = Carbon::parse('2025-01-01 10:00:00')->addDays(5)->format('jS M Y g:i A');
        $this->assertEquals($expectedExpiryTime, $share->expiry_time);

        Carbon::setTestNow(); // Reset Carbon
    }

    public function test_shared_files_relationship()
    {
        $share = Share::factory()->create();
        $localFile = LocalFile::factory()->create();
        $sharedFile = SharedFile::factory()->create(['share_id' => $share->id, 'file_id' => $localFile->id]);

        $this->assertCount(1, $share->fresh()->sharedFiles);
        $this->assertTrue($share->fresh()->sharedFiles->where('share_id', $sharedFile->share_id)->where(
            'file_id',
            $sharedFile->file_id
        )->isNotEmpty());
    }

    // Note: getFilenamesByPath is complex due to raw DB query.
    // It's often better to test such methods as part of a feature test
    // where the database state is fully controlled and realistic.
    // For a unit test, one might mock DB facade, but that can be brittle.
    // I'll skip this for now to keep the unit test focused on model methods.
}
