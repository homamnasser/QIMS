<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

// استيراد موديل الدور

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'first_name' => 'super',
                'last_name'  => 'admin',
                'password'   => '123456789',
                'phone'      => '0938316303',
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
