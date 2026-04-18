<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            PipelineTabSeeder::class,
            BoardSeeder::class,
            ServiceSeeder::class,
            SettingSeeder::class,


        ]);
        // Create roles with 'api' guard
        /*  $adminRole = Role::create(['name' => 'parent', 'guard_name' => 'api']);
         $userRole = Role::create(['name' => 'childs', 'guard_name' => 'api']);



            // Create permissions with route names
        //Permission::create(['name' => 'view sms sender', 'guard_name' => 'api', 'route_name' => 'sms.sender']);
       // Permission::create(['name' => 'view whatsapp sender', 'guard_name' => 'api', 'route_name' => 'sms.sender']);


          // Define the resources you want to create permissions for
          $resources = ['senders', 'Tickets', 'ContactGroups','BalanceTransfer','FavoritSms','UserWebhook','UserOtp'
          ,'Whitelistip','UserProfile','MessagesSent','SmsDetails','UserTag','SubAccounts']; // Add your resource names here

          // Loop through each resource and create permissions for each action
          foreach ($resources as $resource) {
              Permission::create(['name' => "{$resource}.index", 'route_name' => "{$resource}.index"]);
              Permission::create(['name' => "{$resource}.create", 'route_name' => "{$resource}.create"]);
              Permission::create(['name' => "{$resource}.store", 'route_name' => "{$resource}.store"]);
              Permission::create(['name' => "{$resource}.show", 'route_name' => "{$resource}.show"]);
              Permission::create(['name' => "{$resource}.edit", 'route_name' => "{$resource}.edit"]);
              Permission::create(['name' => "{$resource}.update", 'route_name' => "{$resource}.update"]);
              Permission::create(['name' => "{$resource}.destroy", 'route_name' => "{$resource}.destroy"]);
          }


*/
        // Assign permissions to roles
        // $adminRole->givePermissionTo(['view sms sender', 'view whatsapp sender', 'assign roles']);
        //  $userRole->givePermissionTo(['view sms sender']);

    }
}
