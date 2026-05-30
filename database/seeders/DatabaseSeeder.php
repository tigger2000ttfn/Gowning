<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds the initial System Admin account.
     * Credentials are read from env so they are never committed:
     *   ADMIN_EMAIL, ADMIN_PASSWORD (set in .env before seeding).
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@matcastellas.com');
        $password = env('ADMIN_PASSWORD', 'ChangeMe!2026');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'System Admin',
                'password' => Hash::make($password),
                'role' => Role::SuperUser,
                'is_active' => true,
                'approval_status' => 'approved',
                'approved_at' => now(),
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info("Seeded System Admin: {$email}");

        $this->call(RoleCapabilitySeeder::class);
        $this->call(AutomationRuleSeeder::class);
        $this->call(SampleDataSeeder::class);
    }
}
