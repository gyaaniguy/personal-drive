<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Tests\Feature\BaseFeatureTest;

class CheckAdminTest extends BaseFeatureTest
{
    public function test_admin_can_access_admin_routes()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $response = $this->get('/admin-config');

        $response->assertStatus(200);
    }

    public function test_non_admin_is_redirected()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        $response = $this->get('/admin-config');

        $response->assertStatus(302);
        $response->assertRedirect(
            route(
                'rejected', [
                'message' => 'You do not have admin access'
                ]
            )
        );
    }
}
