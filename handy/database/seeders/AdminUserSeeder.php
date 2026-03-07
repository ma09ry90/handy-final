<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash; // Import Hash

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Use updateOrCreate to ensure it fixes the data if it already exists
        User::updateOrCreate(
            ['email' => 'admin@admin.com'], // Search by email
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin1234'), // MUST BE HASHED
                'role_id' => 4, // Force Role ID 4
                'account_status' => 'active', // Force Active
            ]
        );
    }
}