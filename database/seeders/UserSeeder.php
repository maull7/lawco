<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => bcrypt('12345678'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'reviewer@example.com'],
            [
                'name' => 'Reviewer',
                'password' => bcrypt('12345678'),
                'role' => 'reviewer',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'subadmin@example.com'],
            [
                'name' => 'Sub Admin',
                'password' => bcrypt('12345678'),
                'role' => 'sub_admin',
                'permissions' => [
                    'upload_regulations',
                    'manage_categories',
                    'manage_types',
                    'manage_sub_categories',
                    'manage_prompts',
                    'access_legal_decisions',
                ],
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'subadmin1@mail.com'],
            [
                'name' => 'Sub Admin 1',
                'password' => bcrypt('12345678'),
                'role' => 'sub_admin',
                'permissions' => [
                    'upload_regulations',
                ],
                'email_verified_at' => now(),
            ]
        );
    }
}
