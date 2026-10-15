<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create {email?}';

    protected $description = 'Create or update the local administrator without a hardcoded password';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Admin email');
        $password = $this->secret('Admin password (input hidden)');
        if (! is_string($password) || strlen($password) < 12) {
            $this->error('رمز باید حداقل ۱۲ نویسه باشد.');

            return self::FAILURE;
        } $user = User::updateOrCreate(['email' => $email], ['name' => 'مدیر', 'password' => Hash::make($password)]);
        $this->info("Admin {$user->email} آماده است.");

        return self::SUCCESS;
    }
}
