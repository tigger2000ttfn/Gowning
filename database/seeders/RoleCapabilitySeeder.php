<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\RoleCapability;
use Illuminate\Database\Seeder;

class RoleCapabilitySeeder extends Seeder
{
    /** Seed each role's default capabilities (only if not already present). */
    public function run(): void
    {
        foreach (Role::cases() as $role) {
            foreach ($role->defaultCapabilities() as $cap) {
                RoleCapability::firstOrCreate(['role' => $role->value, 'capability' => $cap]);
            }
        }
        RoleCapability::flush();
        $this->command?->info('Role capability matrix seeded.');
    }
}
