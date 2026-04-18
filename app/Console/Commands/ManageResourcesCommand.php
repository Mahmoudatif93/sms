<?php

namespace App\Console\Commands;

use App\Models\Resource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class ManageResourcesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'manage:resources';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List API routes, allow user to add them to the resources table, and toggle their active status';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get all API routes
        $routes = collect(Route::getRoutes())->filter(function ($route) {
            return in_array('api', $route->gatherMiddleware());
        })->map(function ($route) {
            return [
                'method' => implode('|', $route->methods()),
                'uri' => $route->uri(),
            ];
        });

        // Display available routes
        $this->info("Available API Routes:");
        $routes->each(function ($route, $index) {
            $this->line("[$index] {$route['method']} {$route['uri']}");
        });

        // Ask user which routes to add or toggle
        $selectedRoutes = $this->ask('Enter the route numbers you want to add or toggle (comma separated)');

        // Convert the input into an array of indices
        $selectedIndices = explode(',', str_replace(' ', '', $selectedRoutes));

        foreach ($selectedIndices as $index) {
            if (!isset($routes[$index])) {
                $this->error("Invalid route number: $index");
                continue;
            }

            $route = $routes[$index];
            $version = 'v1';  // Set the version as needed

            // Check if the resource already exists
            $resource = Resource::where('method', $route['method'])
                ->where('uri', $route['uri'])
                ->where('version', $version)
                ->first();

            if ($resource) {
                // Toggle the active status
                $resource->is_active = !$resource->is_active;
                $resource->save();

                $this->info("Toggled the status of resource: {$route['method']} {$route['uri']} (Now " . ($resource->is_active ? 'Active' : 'Inactive') . ")");
            } else {
                // Create a new resource if it doesn't exist
                Resource::create([
                    'method' => $route['method'],
                    'uri' => $route['uri'],
                    'version' => $version,
                    'is_active' => true,
                ]);

                $this->info("Added resource: {$route['method']} {$route['uri']} as Active.");
            }
        }

        return 0;
    }
}
