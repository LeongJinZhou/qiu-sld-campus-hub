<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([SettingsSeeder::class, StaffSeeder::class]);

        if (app()->isLocal()) {
            $this->call([DemoClubsSeeder::class, DemoEventsSeeder::class]);
        }
    }
}
