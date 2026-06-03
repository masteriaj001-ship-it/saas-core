<?php

declare(strict_types=1);

namespace App\Modules\Shared\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateSuperAdmin extends Command
{
    protected $signature = 'shared:create-super-admin
        {--email= : Email address for the super admin}
        {--password= : Password for the super admin}';

    protected $description = 'Create a global superadmin user (non-interactive, transactional)';

    public function handle(): int
    {
        $email = $this->option('email');
        $password = $this->option('password');

        if (empty($email) || empty($password)) {
            $this->error('Both --email and --password options are required.');

            return Command::FAILURE;
        }

        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
        ], [
            'email' => ['required', 'email:rfc', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return Command::FAILURE;
        }

        try {
            DB::transaction(function () use ($email, $password) {
                User::create([
                    'name' => 'Super Admin',
                    'email' => $email,
                    'password' => Hash::make($password),
                    'is_superadmin' => true,
                    'tenant_id' => null,
                ]);
            });

            $this->info("Superadmin created successfully: {$email}");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to create superadmin: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
