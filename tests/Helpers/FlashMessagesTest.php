<?php

namespace Helpers;

use App\Traits\FlashMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class FlashMessagesTest extends TestCase
{
    protected $flashMessages;

    public function test_sets_success_message_and_redirects_back()
    {
        $response = $this->flashMessages->success('Operation successful');

        $this->assertEquals('Operation successful', session()->get('message'));
        $this->assertTrue(session()->get('status'));
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function test_sets_error_message_and_redirects_back()
    {
        $response = $this->flashMessages->error('Operation failed');

        $this->assertEquals('Operation failed', session()->get('message'));
        $this->assertFalse(session()->get('status'));
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function test_flash_messages_are_temporarily_stored()
    {
        $this->flashMessages->success('Temporary');

        $this->assertTrue(session()->has('message'));
        $this->assertTrue(session()->has('status'));

        session()->driver()->save();
        session()->flush();
        session()->regenerate();
        session()->start();

        $this->assertFalse(session()->has('message'));
        $this->assertFalse(session()->has('status'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->flashMessages = new class {
            use FlashMessages;
        };
    }
}
