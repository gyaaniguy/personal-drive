<?php

namespace Tests\Unit\Traits;

use App\Models\Share;
use App\Models\SharedFile;
use App\Models\LocalFile;
use App\Services\DownloadService;
use App\Traits\GuestResourceAuthorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Mockery;
use Tests\TestCase;

class GuestResourceAuthorizeTest extends TestCase
{
    use RefreshDatabase, GuestResourceAuthorize;

    protected $downloadService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->downloadService = Mockery::mock(DownloadService::class);
    }

    public function test_returns_false_when_no_share_id_in_session()
    {
        Session::forget('share_id');

        $result = $this->guestVerified(['file-1', 'file-2'], $this->downloadService);

        $this->assertFalse($result);
    }

    public function test_returns_false_when_guest_has_no_permissions()
    {
        Session::put('share_id', 1);

        $this->downloadService
            ->shouldReceive('hasGuestShareFileIdPermissions')
            ->with(1, ['file-1', 'file-2'])
            ->once()
            ->andReturn(false);

        $result = $this->guestVerified(['file-1', 'file-2'], $this->downloadService);

        $this->assertFalse($result);
    }

    public function test_returns_true_when_guest_has_permissions()
    {
        Session::put('share_id', 1);

        $this->downloadService
            ->shouldReceive('hasGuestShareFileIdPermissions')
            ->with(1, ['file-1', 'file-2'])
            ->once()
            ->andReturn(true);

        $result = $this->guestVerified(['file-1', 'file-2'], $this->downloadService);

        $this->assertTrue($result);
    }

    public function test_returns_true_with_single_file_permission()
    {
        Session::put('share_id', 5);

        $this->downloadService
            ->shouldReceive('hasGuestShareFileIdPermissions')
            ->with(5, ['file-1'])
            ->once()
            ->andReturn(true);

        $result = $this->guestVerified(['file-1'], $this->downloadService);

        $this->assertTrue($result);
    }

    public function test_returns_true_with_multiple_file_permissions()
    {
        Session::put('share_id', 10);

        $this->downloadService
            ->shouldReceive('hasGuestShareFileIdPermissions')
            ->with(10, ['file-1', 'file-2', 'file-3'])
            ->once()
            ->andReturn(true);

        $result = $this->guestVerified(['file-1', 'file-2', 'file-3'], $this->downloadService);

        $this->assertTrue($result);
    }

    public function test_returns_false_with_empty_file_ids()
    {
        Session::put('share_id', 1);

        $this->downloadService
            ->shouldReceive('hasGuestShareFileIdPermissions')
            ->with(1, [])
            ->once()
            ->andReturn(false);

        $result = $this->guestVerified([], $this->downloadService);

        $this->assertFalse($result);
    }

    public function test_uses_correct_share_id_from_session()
    {
        Session::put('share_id', 999);

        $this->downloadService
            ->shouldReceive('hasGuestShareFileIdPermissions')
            ->with(999, ['file-1'])
            ->once()
            ->andReturn(true);

        $result = $this->guestVerified(['file-1'], $this->downloadService);
        $this->assertTrue($result);
    }

    public function test_with_numeric_share_id()
    {
        Session::put('share_id', 42);

        $this->downloadService
            ->shouldReceive('hasGuestShareFileIdPermissions')
            ->with(42, ['file-1'])
            ->once()
            ->andReturn(true);

        $result = $this->guestVerified(['file-1'], $this->downloadService);

        $this->assertTrue($result);
    }

    public function test_with_string_file_ids()
    {
        Session::put('share_id', 1);

        $this->downloadService
            ->shouldReceive('hasGuestShareFileIdPermissions')
            ->with(1, ['abc', 'def'])
            ->once()
            ->andReturn(true);

        $result = $this->guestVerified(['abc', 'def'], $this->downloadService);

        $this->assertTrue($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
