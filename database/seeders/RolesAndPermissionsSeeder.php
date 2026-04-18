<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
      // Create permissions
      /*Permission::create(['name' => 'View whats up']);
      Permission::create(['name' => 'View sms']);

      // Create roles and assign permissions
      $adminRole = Role::create(['name' => 'admin']);
      $userRole = Role::create(['name' => 'user']);

      $adminRole->givePermissionTo(['View whats up', 'View sms']);
      $userRole->givePermissionTo('View whats up');*/
     /* $user = User::find(24);
      $user->givePermissionTo('View sms');
      $user->assignRole($userRole);*/

    }
}
