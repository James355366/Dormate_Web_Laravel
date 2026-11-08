<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    protected $signature = 'dormate:create-admin {--name=} {--email=}';

    protected $description = 'Create the first Super Admin without overwriting existing accounts';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = strtolower(trim($this->option('email') ?: $this->ask('Email address')));
        $password = $this->secret('Password (8 to 72 characters)');
        $confirm = $this->secret('Confirm password');
        $validator = validator(compact('name', 'email', 'password'), [
            'name' => 'required|string|max:100', 'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:8|max:72',
        ]);
        if ($validator->fails() || $password !== $confirm) {
            $this->error($validator->fails() ? $validator->errors()->first() : 'Passwords do not match.');

            return self::FAILURE;
        }
        User::create(['name' => $name, 'email' => $email, 'password_hash' => Hash::make($password), 'role' => 'SUPER_ADMIN', 'status' => 'active']);
        $this->info('Super Admin created. Choose Super Admin on the login screen.');

        return self::SUCCESS;
    }
}
