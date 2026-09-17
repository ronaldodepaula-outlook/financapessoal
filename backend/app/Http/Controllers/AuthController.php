<?php

namespace App\Http\Controllers;

use App\Enums\RecordStatus;
use App\Helpers\ApiResponse;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private TokenService $tokens) {}

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->validated('email'))->first();
        // Hash válido fictício mantém o custo de verificação quando o e-mail não existe.
        $hash = $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = Hash::check($request->validated('password'), $hash);
        if (! $valid || ! $user || $user->status !== RecordStatus::ACTIVE) {
            throw new AuthenticationException;
        }

        return ApiResponse::success([...$this->tokens->issue($user), 'user' => $user], 'Login realizado.')->header('Cache-Control', 'no-store');
    }

    public function refresh(Request $request)
    {
        $data = $request->validate(['refresh_token' => 'required|string|regex:/^[a-f0-9]{96}$/']);

        return ApiResponse::success($this->tokens->refresh($data['refresh_token']), 'Sessão renovada.')->header('Cache-Control', 'no-store');
    }

    public function me(Request $request)
    {
        return ApiResponse::success($request->user())->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request)
    {
        $this->tokens->revoke($request->attributes->get('auth_session'));

        return ApiResponse::success(null, 'Sessão encerrada.');
    }
}
