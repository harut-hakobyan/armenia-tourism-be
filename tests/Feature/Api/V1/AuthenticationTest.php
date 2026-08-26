<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_log_in_read_profile_and_log_out(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Manager,
            'password' => 'correct-password',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'device_name' => 'feature-test',
        ]);

        $token = $loginResponse
            ->assertOk()
            ->assertJsonPath('data.user.role', UserRole::Manager->value)
            ->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertUnprocessable();
    }

    public function test_role_middleware_rejects_non_admin_user(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)
            ->getJson('/api/v1/admin/health')
            ->assertForbidden();
    }

    public function test_role_middleware_allows_admin_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/health')
            ->assertOk();
    }
}
