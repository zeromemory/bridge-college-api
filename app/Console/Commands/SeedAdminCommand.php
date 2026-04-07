<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedAdminCommand extends Command
{
    protected $signature = 'db:seed-admin';

    protected $description = 'Create admin user if not exists (safe for production — does NOT wipe data)';

    public function handle(): int
    {
        $existing = User::where('cnic', '35202-9999999-1')->first();

        if ($existing) {
            $this->info("Admin already exists: {$existing->email} (ID: {$existing->id})");

            return self::SUCCESS;
        }

        $admin = User::create([
            'name' => 'BCI Admin',
            'cnic' => '35202-9999999-1',
            'email' => 'admin@bridgecollegeinternational.com',
            'password' => Hash::make('Bci@Admin#2026!'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $admin->forceFill([
            'email_verified_at' => now(),
            'password_set_at' => now(),
        ])->save();

        $this->info("Admin created: {$admin->email} (ID: {$admin->id})");
        $this->table(
            ['CNIC', 'Email', 'Password'],
            [['35202-9999999-1', 'admin@bridgecollegeinternational.com', 'Bci@Admin#2026!']],
        );

        return self::SUCCESS;
    }
}
