<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the default administrator account.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@spms.test'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Admin@12345'),
            ],
        );

        $admin->assignRole('Administrator');
    }
}
