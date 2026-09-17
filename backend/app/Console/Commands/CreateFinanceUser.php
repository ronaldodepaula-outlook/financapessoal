<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InitialPlanningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateFinanceUser extends Command
{
    protected $signature = 'finance:user {email} {--name=}';

    protected $description = 'Cria usuário e categorias iniciais; solicita senha em entrada oculta.';

    public function handle(InitialPlanningService $initial): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Execute interativamente para informar a senha sem expô-la na linha de comando.');

            return self::FAILURE;
        }
        $data = [
            'email' => mb_strtolower(trim($this->argument('email'))),
            'name' => $this->option('name') ?: $this->ask('Nome'),
            'password' => $this->secret('Senha (mínimo 12 caracteres)'),
            'password_confirmation' => $this->secret('Confirme a senha'),
        ];
        $validator = Validator::make($data, [
            'name' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($data, $initial) {
            $user = User::create(array_intersect_key($data, array_flip(['name', 'email', 'password'])));
            $initial->initialize($user);
        });
        $this->info('Usuário, categorias, orçamentos e rascunhos iniciais criados. Nenhum lançamento financeiro foi gerado.');

        return self::SUCCESS;
    }
}
