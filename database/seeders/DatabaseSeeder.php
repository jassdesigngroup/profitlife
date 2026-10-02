<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Roles, permisos y ajustes se siembran en todos los entornos; los datos
     * de ejemplo solo en local.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
        ]);

        if ($this->container->environment('local')) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
