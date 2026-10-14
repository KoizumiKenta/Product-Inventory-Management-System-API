<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_a_user_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Nabila Hassan',
            'email' => 'nabila@example.com',
            'password' => 'open-sesame',
            'password_confirmation' => 'open-sesame',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.email', 'nabila@example.com');

        $this->assertDatabaseHas('users', ['email' => 'nabila@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_returns_a_token(): void
    {
        // the user factory hashes 'password' for every user it makes
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $token = $response->json('token');

        $this->assertIsString($token);
        $this->assertNotSame('', $token);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_login_with_a_wrong_password_fails_validation(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_guests_cannot_reach_protected_routes(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_a_guest_without_an_accept_header_still_gets_json(): void
    {
        $this->get('/api/v1/products')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_validation_errors_are_json_without_an_accept_header(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/v1/products', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'name', 'sku', 'price', 'stock_quantity']);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_revoked_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;
        $user->tokens()->delete();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }
}
