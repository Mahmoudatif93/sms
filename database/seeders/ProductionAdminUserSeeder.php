<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use App\Models\Supervisor;

class ProductionAdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!App::environment('production')) {
            $this->command->info('Skipping ProductionAdminUserSeeder — not in production environment.');
            return;
        }

        // You can customize the admin details as needed
        $adminUsername = 'dreams.admin';
        $adminEmail = 'apps@dreams.com.sa';

        $existing = Supervisor::where('username', $adminUsername)->first();

        if ($existing) {
            $this->command->info("Production admin user '{$adminUsername}' already exists.");
            return;
        }


        Supervisor::create([
            'group_id' => 1,
            'username' => $adminUsername,
            'password' => Hash::make('ofru2zE/8JVBMMe1ZssvvOA=='),
            'email' => $adminEmail,
            'number' => '966554571143',
            'date' => now(),
            'lang' => 'en',
            'status' => 1
        ]);

        $this->command->info("Production admin user '{$adminUsername}' has been created.");
    }
}
