<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\InteractsWithApiAuth;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use InteractsWithApiAuth;
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secretpass',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'role'],
                'token',
            ])
            ->assertJsonPath('user.name', 'Ada Lovelace');

        $this->assertDatabaseHas('users', [
            'email' => 'ada@example.com',
        ]);

        $user = User::where('email', 'ada@example.com')->first();
        $this->assertNotNull($user->api_token);
        $this->assertSame($user->api_token, $response->json('token'));
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'grace@example.com',
            'password' => Hash::make('correct-horse'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'grace@example.com',
            'password' => 'correct-horse',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name'],
                'token',
            ])
            ->assertJsonPath('user.id', $user->id);

        $this->assertNotNull($user->fresh()->api_token);
    }

    public function test_login_fails_for_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'wrong@example.com',
            'password' => Hash::make('correct-horse'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'nope-nope',
        ]);

        $response->assertStatus(422);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsToken($user)->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', $user->name);
    }

    public function test_logout_clears_token(): void
    {
        $user = User::factory()->create();
        $this->actingAsToken($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(204);
        $this->assertNull($user->fresh()->api_token);
    }

    public function test_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAsToken($user)->patchJson('/api/auth/profile', [
            'name' => 'New Name',
            'timezone' => 'America/New_York',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.timezone', 'America/New_York');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('America/New_York', $user->timezone);
    }

    public function test_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAsToken($user)->postJson('/api/auth/password', [
            'current_password' => 'old-password',
            'new_password' => 'brand-new-pass',
        ]);

        $response->assertStatus(204);
        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAsToken($user)->postJson('/api/auth/password', [
            'current_password' => 'wrong-current',
            'new_password' => 'brand-new-pass',
        ]);

        $response->assertStatus(422);
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
