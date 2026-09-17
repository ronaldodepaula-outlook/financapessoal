<?php

namespace Tests\Feature;

use App\Enums\RecordStatus;
use App\Models\AuthSession;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): array
    {
        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('data');
    }

    public function test_login_me_and_logout_revoke_access_and_refresh_tokens(): void
    {
        $user = User::factory()->create();
        $data = $this->login($user);
        $this->assertArrayNotHasKey('password', $data['user']);
        $this->assertNotSame($data['refresh_token'], AuthSession::first()->getRawOriginal('refresh_token_hash'));
        $headers = ['Authorization' => 'Bearer '.$data['access_token']];
        $this->getJson('/api/auth/me', $headers)->assertOk()->assertJsonPath('data.id', $user->id);
        $this->postJson('/api/auth/logout', [], $headers)->assertOk();
        $this->getJson('/api/auth/me', $headers)->assertUnauthorized();
        $this->postJson('/api/auth/refresh', ['refresh_token' => $data['refresh_token']])->assertUnauthorized();
        $this->assertSame(2, $user->auditLogs()->count());
    }

    public function test_refresh_rotates_both_credentials_and_rejects_replay(): void
    {
        $data = $this->login(User::factory()->create());
        $new = $this->postJson('/api/auth/refresh', ['refresh_token' => $data['refresh_token']])->assertOk()->json('data');
        $this->assertNotSame($data['access_token'], $new['access_token']);
        $this->assertSame($data['refresh_expires_at'], $new['refresh_expires_at']);
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$data['access_token']])->assertUnauthorized();
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$new['access_token']])->assertOk();
        $this->postJson('/api/auth/refresh', ['refresh_token' => $data['refresh_token']])->assertUnauthorized();
    }

    public function test_invalid_expired_and_wrong_issuer_tokens_are_rejected(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer invalid'])->assertUnauthorized();
        $data = $this->login(User::factory()->create());
        $parts = explode('.', $data['access_token']);
        $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $expired = JWT::encode([...$claims, 'exp' => time() - 5, 'nbf' => time() - 100], config('jwt.secret'), 'HS256');
        $wrongIssuer = JWT::encode([...$claims, 'iss' => 'another-app'], config('jwt.secret'), 'HS256');
        foreach ([$expired, $wrongIssuer, $data['access_token'].'bad'] as $token) {
            $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        }
    }

    public function test_inactive_user_is_denied_in_existing_session(): void
    {
        $user = User::factory()->create();
        $data = $this->login($user);
        $user->status = RecordStatus::INACTIVE;
        $user->save();
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$data['access_token']])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnauthorized();
    }

    public function test_wrong_credentials_and_validation_have_standard_errors(): void
    {
        $this->postJson('/api/auth/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/api/auth/login', ['email' => ['invalid'], 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertUnauthorized();
        $user = User::factory()->create();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
    }
}
