<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ProfileTest extends TestCase
{

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/admin/profile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role']]);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->putJson('/api/admin/profile', [
                'name'  => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('current-password'),
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson('/api/admin/profile/password', [
                'current_password'      => 'current-password',
                'password'              => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('current-password'),
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson('/api/admin/profile/password', [
                'current_password'      => 'wrong-password',
                'password'              => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertStatus(422);
    }
}
