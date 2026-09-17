<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Services\TokenService;

trait AuthenticatesApi
{
    protected function apiHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(TokenService::class)->issue($user)['access_token']];
    }
}
