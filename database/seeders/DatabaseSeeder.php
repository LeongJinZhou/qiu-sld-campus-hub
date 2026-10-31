<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([SettingsSeeder::class, StaffSeeder::class]);

        // Demo clubs and events: locally, and on a hosted demo (DEMO_LOGIN=true). Never in a pilot.
        if (app()->isLocal() || config('sld.demo_login')) {
            $this->call([DemoClubsSeeder::class, DemoEventsSeeder::class, DemoFinanceSeeder::class]);
        }
    }
}
