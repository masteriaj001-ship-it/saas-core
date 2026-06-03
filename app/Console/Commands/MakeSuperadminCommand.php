<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeSuperadminCommand extends Command
{
    protected $signature = 'jaosoft:make-superadmin';

    protected $description = 'Create a global superadmin user (tenant_id=null, is_superadmin=true)';

    public function handle(): int
    {
        $name = $this->ask('Nombre del superadmin');
        $email = $this->ask('Email del superadmin');
        $password = $this->secret('Contraseña');

        if (User::where('email', $email)->exists()) {
            $this->error("Ya existe un usuario con el email {$email}.");

            return Command::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_superadmin' => true,
            'tenant_id' => null,
        ]);

        $this->info("Superadmin creado: {$user->email}");

        return Command::SUCCESS;
    }
}
