<?php

use Illuminate\Foundation\Inspiring;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('user:create-admin {--email=} {--name=} {--password=} {--no-verify}', function () {
    $email = strtolower((string) ($this->option('email') ?: $this->ask('Admin email')));
    $name = (string) ($this->option('name') ?: $this->ask('Admin name', 'Bluxor Admin'));
    $password = (string) ($this->option('password') ?: $this->secret('Admin password'));

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('A valid email address is required.');

        return self::FAILURE;
    }

    if (strlen($password) < 12) {
        $this->error('Use a password with at least 12 characters.');

        return self::FAILURE;
    }

    $role = Role::firstOrCreate(['name' => 'admin']);
    $user = User::updateOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'password' => Hash::make($password),
            'status' => 'active',
            'email_verified_at' => $this->option('no-verify') ? null : now(),
        ]
    );
    $user->roles()->syncWithoutDetaching([$role->id]);

    $this->info("Admin user ready: {$user->email}");

    return self::SUCCESS;
})->purpose('Create or update an admin user without shipping seeded credentials');
