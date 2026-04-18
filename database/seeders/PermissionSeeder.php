<?php

namespace Database\Seeders;
use App\Models\Permission;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Assign menu permission to Parents role
        Permission::create([
            'menu_id' => 1, 
            'role_id' => 1, // Parents role
            'can_access' => true
        ]);

        Permission::create([
            'menu_id' => 2, 
            'role_id' => 1, // Parents role
            'can_access' => true
        ]);

        Permission::create([
            'menu_id' => 3, 
            'role_id' => 2, // Child role
            'can_access' => true
        ]);

        // User-specific permission to override role permissions
        Permission::create([
            'menu_id' => 3, 
            'user_id' => 1, // Specific user with ID 1
            'can_access' => false // Deny access
        ]);
    }
}
