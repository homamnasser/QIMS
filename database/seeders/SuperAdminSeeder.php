<?php

namespace Database\Seeders;

use App\Enums\RoleFamily;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

// استيراد موديل الدور

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'web'],
            [
                'role_family' => RoleFamily::SuperAdmin->value,
                'is_system' => true,
                'role_family_reviewed_at' => now(),
            ]
        );

        $role->forceFill([
            'role_family' => RoleFamily::SuperAdmin->value,
            'is_system' => true,
            'suggested_role_family' => null,
            'role_family_reviewed_at' => now(),
        ])->save();

        $user = User::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'first_name' => 'super',
                'last_name' => 'admin',
                'password' => '123456789',
                'phone' => '0938316303',
                'birth_date' => '1990-01-01',
            ]
        );

        $user->assignRole($role);

        $this->command->info('Super Admin created successfully!');

        if ($user->wasRecentlyCreated) {
            $this->command->info('Super Admin user created successfully!');
        } else {
            $this->command->info('Super Admin user already exists!');
        }
    }
}
