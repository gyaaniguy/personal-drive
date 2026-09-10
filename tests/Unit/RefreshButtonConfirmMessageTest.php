<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RefreshButtonConfirmMessageTest extends TestCase
{
    public function test_confirm_message_reassures_favorites_are_kept(): void
    {
        $path = dirname(__DIR__, 2) . '/resources/js/Pages/Drive/Components/RefreshButton.jsx';
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertStringContainsString('Favorites are kept', $source, 'Confirm message must state favorites are kept');
    }

    public function test_confirm_message_warns_about_shares(): void
    {
        $path = dirname(__DIR__, 2) . '/resources/js/Pages/Drive/Components/RefreshButton.jsx';
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertStringContainsString('shares', $source, 'Confirm message must mention shares');
    }
}
