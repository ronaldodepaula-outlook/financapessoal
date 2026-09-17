<?php

namespace App\Services;

use App\Enums\RecordStatus;
use App\Models\AuthSession;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class TokenService
{
    public function __construct(private AuditService $audit) {}

    private function secret(): string
    {
        $secret = (string) config('jwt.secret');
        if (strlen($secret) < 64 || config('jwt.ttl') < 1 || config('jwt.refresh_ttl') < config('jwt.ttl')) {
            throw new \LogicException('Configuração JWT inválida.');
        }

        return $secret;
    }

    public function issue(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $refresh = bin2hex(random_bytes(48));
            $session = new AuthSession;
            $session->id = (string) Str::uuid();
            $session->user_id = $user->id;
            $session->refresh_token_hash = hash('sha256', $refresh);
            $session->version = 1;
            $session->expires_at = now()->addMinutes(config('jwt.refresh_ttl'));
            $session->save();
            $tokens = $this->tokens($session, $refresh);
            $this->audit->record($user, 'auth.login', $user);

            return $tokens;
        });
    }

    private function tokens(AuthSession $session, string $refresh): array
    {
        $expires = min(now()->addMinutes(config('jwt.ttl'))->timestamp, $session->expires_at->timestamp);

        return [
            'access_token' => JWT::encode([
                'iss' => config('jwt.issuer'), 'aud' => config('jwt.issuer'),
                'sub' => (string) $session->user_id, 'sid' => $session->id, 'ver' => $session->version,
                'jti' => (string) Str::uuid(), 'iat' => now()->timestamp, 'nbf' => now()->timestamp, 'exp' => $expires,
            ], $this->secret(), 'HS256'),
            'token_type' => 'Bearer', 'expires_in' => $expires - now()->timestamp,
            'refresh_token' => $refresh, 'refresh_expires_at' => $session->expires_at->toIso8601String(),
        ];
    }

    public function authenticate(?string $token): AuthSession
    {
        $secret = $this->secret();
        if (! $token) {
            throw new AuthenticationException;
        }
        try {
            $claims = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (Throwable) {
            throw new AuthenticationException;
        }
        if (($claims->iss ?? null) !== config('jwt.issuer') || ($claims->aud ?? null) !== config('jwt.issuer')
            || ! is_string($claims->sub ?? null) || ! ctype_digit($claims->sub)
            || ! is_string($claims->sid ?? null) || ! Str::isUuid($claims->sid)
            || ! is_int($claims->ver ?? null) || ! is_int($claims->exp ?? null) || $claims->exp <= now()->timestamp) {
            throw new AuthenticationException;
        }
        $session = AuthSession::with('user')->find($claims->sid);
        if (! $session || (string) $session->user_id !== $claims->sub || $session->version !== $claims->ver
            || $session->revoked_at || $session->expires_at->isPast() || $session->user->status !== RecordStatus::ACTIVE) {
            throw new AuthenticationException;
        }

        return $session;
    }

    public function refresh(string $token): array
    {
        return DB::transaction(function () use ($token) {
            $session = AuthSession::where('refresh_token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $session || $session->revoked_at || $session->expires_at->isPast() || $session->user->status !== RecordStatus::ACTIVE) {
                throw new AuthenticationException;
            }
            $refresh = bin2hex(random_bytes(48));
            $session->refresh_token_hash = hash('sha256', $refresh);
            $session->version++;
            $session->save();
            $tokens = $this->tokens($session, $refresh);
            $this->audit->record($session->user, 'auth.refresh', $session->user);

            return $tokens;
        });
    }

    public function revoke(AuthSession $session): void
    {
        DB::transaction(function () use ($session) {
            $locked = AuthSession::lockForUpdate()->findOrFail($session->id);
            $locked->revoked_at = now();
            $locked->save();
            $this->audit->record($locked->user, 'auth.logout', $locked->user);
        });
    }
}
