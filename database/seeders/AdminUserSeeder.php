<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\RolePresets;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Role 1: Admin & HR — full system access
        $admin = User::firstOrCreate(
            ['email' => 'admin@mge-pms.test'],
            [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'password' => 'password',
                'status' => 'active',
            ]
        );
        $admin->syncRoles(['Admin & HR']);

        // Role 2: Finances & HR
        $finance = User::firstOrCreate(
            ['email' => 'finance@mge-pms.test'],
            [
                'first_name' => 'Finance',
                'last_name' => 'Manager',
                'password' => 'password',
                'status' => 'active',
            ]
        );
        $finance->syncRoles(['Finances & HR']);
        $finance->syncPermissions(RolePresets::for('Finances & HR'));

        // Role 3: Projects
        $pm = User::firstOrCreate(
            ['email' => 'pm@mge-pms.test'],
            [
                'first_name' => 'Project',
                'last_name' => 'Manager',
                'password' => 'password',
                'status' => 'active',
            ]
        );
        $pm->syncRoles(['Projects']);
        $pm->syncPermissions(RolePresets::for('Projects'));
    }
}
