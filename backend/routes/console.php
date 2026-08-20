<?php

use Illuminate\Foundation\Inspiring;
use App\Models\Role;
use App\Models\User;
use App\Services\GuestPurchaseClaimService;
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

Artisan::command('purchases:claim-verified-guests {--dry-run}', function () {
    $claims = app(GuestPurchaseClaimService::class);
    $dryRun = (bool) $this->option('dry-run');
    $totals = [
        'verified_users_checked' => 0,
        'matching_paid_guest_orders' => 0,
        'potential_entitlements' => 0,
        'orders_claimed' => 0,
        'entitlements_claimed' => 0,
        'products_claimed' => 0,
    ];

    User::query()
        ->whereNotNull('email_verified_at')
        ->orderBy('id')
        ->chunkById(100, function ($users) use (&$totals, $claims, $dryRun) {
            foreach ($users as $user) {
                $totals['verified_users_checked']++;

                if ($dryRun) {
                    $preview = $claims->previewForVerifiedUser($user);
                    $totals['matching_paid_guest_orders'] += $preview['matching_paid_guest_orders'];
                    $totals['potential_entitlements'] += $preview['potential_entitlements'];

                    continue;
                }

                $result = $claims->claimForVerifiedUser($user);
                $totals['orders_claimed'] += $result['orders_claimed'];
                $totals['entitlements_claimed'] += $result['entitlements_claimed'];
                $totals['products_claimed'] += $result['products_claimed'] ?? 0;
            }
        });

    $this->info('Verified users checked: '.$totals['verified_users_checked']);

    if ($dryRun) {
        $this->info('Matching paid guest orders: '.$totals['matching_paid_guest_orders']);
        $this->info('Potential entitlements: '.$totals['potential_entitlements']);
    } else {
        $this->info('Orders claimed: '.$totals['orders_claimed']);
        $this->info('Entitlements claimed: '.$totals['entitlements_claimed']);
        $this->info('Products claimed: '.$totals['products_claimed']);
    }

    return self::SUCCESS;
})->purpose('Claim paid guest purchases for verified matching customer accounts');
