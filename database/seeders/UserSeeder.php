<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserAdditionalInformation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'employee_id' => 'ADM001',
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        UserAdditionalInformation::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'gender' => 'male',
                'phone' => '+1234567890',
                'address' => '123 Admin Street, City, Country',
            ]
        );

        $employee = User::updateOrCreate(
            ['email' => 'employee1@example.com'],
            [
                'employee_id' => 'EMP001',
                'name' => 'Demo Employee',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        UserAdditionalInformation::updateOrCreate(
            ['user_id' => $employee->id],
            [
                'gender' => 'female',
                'phone' => '+1987654321',
                'address' => '456 Employee Ave, City, Country',
            ]
        );

        $this->command->info('Admin user created: admin@example.com / password');
        $this->command->info('Demo user created: employee1@example.com / password');
    }
}
