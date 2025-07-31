<?php

namespace Tests\Feature\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\Feature\BaseFeatureTest;
use Tests\TestCase;
use App\Models\User;

class PreventSetupAccessTest extends BaseFeatureTest
{
    public function test_redirects_if_users_table_exists_and_has_records()
    {
        // Ensure the users table exists and has a user
        User::factory()->create();

        $response = $this->get(route('setup.account'));

        $response->assertStatus(302);
        $response->assertRedirect('/');
        $response->assertSessionHas('message', 'Setup already completed.');
    }

    public function test_allows_access_if_users_table_exists_but_has_no_records()
    {
        // Ensure the users table exists but is empty (RefreshDatabase will create it)
        DB::table('users')->truncate(); // Ensure no users from previous tests or seeders

        $response = $this->get(route('setup.account'));

        $response->assertOk();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure the build directory exists for tests
        if (!file_exists(public_path('build'))) {
            mkdir(public_path('build'), 0777, true);
        }
        // Ensure manifest.json exists so EnsureFrontendBuilt middleware passes
        if (!file_exists(public_path('build/manifest.json'))) {
            file_put_contents(public_path('build/manifest.json'), '{}');
        }
    }
}
