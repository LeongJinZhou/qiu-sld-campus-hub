<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // A live install gets only the clean production data: roles, lookups, the Super Admin and the SLD Officer.
        if (! app()->isLocal() && ! config('sld.demo_login')) {
            $this->call(ProductionSeeder::class);

            return;
        }

        // Local development and a hosted demo (DEMO_LOGIN=true): demo staff, clubs, events and finance.
        $this->call([SettingsSeeder::class, StaffSeeder::class, DemoClubsSeeder::class, DemoEventsSeeder::class, DemoFinanceSeeder::class]);
    }
}
