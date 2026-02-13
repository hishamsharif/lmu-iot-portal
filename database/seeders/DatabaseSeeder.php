<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Factories\UserFactory;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\warning;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        if (config('app.env') === 'production') {
            warning('Seeders cannot run in production environment');
            exit();
        }

        // run the command permission:sync
        Artisan::call('permission:sync');

        Storage::disk('public')->deleteDirectory('logos');

        UserFactory::new()->create([
            'name' => 'Admin',
            'email' => 'admin@admin.com',
            'is_super_admin' => true,
        ]);

        $this->call([
            OrganizationSeeder::class,
            DeviceSchemaSeeder::class,
            DeviceControlSeeder::class,
            DeviceTelemetrySeeder::class,
            IoTDashboardSeeder::class,
        ]);
    }
}
