<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_using_factory()
    {
        $user = User::factory()->create();
        $this->assertNotNull($user->id);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_user_attributes_are_fillable()
    {
        $userData = [
            'username' => 'testuser',
            'is_admin' => true,
            'password' => bcrypt('password'),
        ];
        $user = User::create($userData);

        $this->assertEquals('testuser', $user->username);
        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->password);
    }

    public function test_password_is_hashed_on_creation()
    {
        $user = User::factory()->create(['password' => 'plainpassword']);
        $this->assertNotEquals('plainpassword', $user->password);
        $this->assertTrue(password_verify('plainpassword', $user->password));
    }

    public function test_hidden_attributes_are_hidden()
    {
        $user = User::factory()->create();
        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

}
