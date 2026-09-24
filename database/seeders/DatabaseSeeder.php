<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Note: model events must stay enabled (no WithoutModelEvents) because
     * HasClientUuid generates the client `uuid` on the `creating` event.
     */
    public function run(): void
    {
        $this->call([
            CrewRoleSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
