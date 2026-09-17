<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_user_with_hashed_password_and_initial_categories(): void
    {
        $this->artisan('finance:user', ['email' => 'teste@example.test', '--name' => 'Usuário de teste'])
            ->expectsQuestion('Senha (mínimo 12 caracteres)', 'SenhaDeTeste123!')
            ->expectsQuestion('Confirme a senha', 'SenhaDeTeste123!')
            ->assertSuccessful();
        $user = User::where('email', 'teste@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('SenhaDeTeste123!', $user->password));
        $this->assertGreaterThan(20, $user->categories()->count());
        $this->assertSame(0, $user->transactions()->count());
    }
}
