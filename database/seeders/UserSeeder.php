<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'staff_id' => 'NPT-001',
                'phone' => '+233 24 000 0001',
                'department' => 'Applications Support',
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['admin']);

        $staff = [
            ['name' => 'Kwame Mensah', 'email' => 'kwame@example.com', 'staff_id' => 'NPT-002', 'phone' => '+233 24 000 0002'],
            ['name' => 'Ama Owusu', 'email' => 'ama@example.com', 'staff_id' => 'NPT-003', 'phone' => '+233 24 000 0003'],
            ['name' => 'Kofi Asante', 'email' => 'kofi@example.com', 'staff_id' => 'NPT-004', 'phone' => '+233 24 000 0004'],
        ];

        foreach ($staff as $person) {
            $user = User::updateOrCreate(
                ['email' => $person['email']],
                [
                    'name' => $person['name'],
                    'staff_id' => $person['staff_id'],
                    'phone' => $person['phone'],
                    'department' => 'Applications Support',
                    'password' => 'password',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles(['support']);
        }
    }
}
