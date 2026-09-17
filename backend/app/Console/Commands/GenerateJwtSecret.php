<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateJwtSecret extends Command
{
    protected $signature = 'finance:jwt-secret';

    protected $description = 'Gera o segredo JWT somente se ainda estiver vazio, sem exibi-lo.';

    public function handle(): int
    {
        $file = base_path('.env');
        if (! is_file($file)) {
            $this->error('Crie o .env antes de gerar o segredo.');

            return self::FAILURE;
        }
        $contents = file_get_contents($file);
        if (preg_match('/^JWT_SECRET=(.+)$/m', $contents, $match) && trim($match[1], "\"'\r \t") !== '') {
            $this->info('O segredo JWT existente foi preservado.');

            return self::SUCCESS;
        }
        $line = 'JWT_SECRET='.bin2hex(random_bytes(48));
        $contents = preg_match('/^JWT_SECRET=.*$/m', $contents)
            ? preg_replace('/^JWT_SECRET=.*$/m', $line, $contents) : $contents."\n".$line."\n";
        file_put_contents($file, $contents, LOCK_EX);
        $this->callSilent('config:clear');
        $this->info('Segredo JWT gerado e salvo no .env.');

        return self::SUCCESS;
    }
}
