<?php

use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Loads the sample camsys dump into the in-memory camsys connection and seeds lookups. */
function seedCamsys(): void
{
    Artisan::call('camsys:import-dump');
    (new SettingsSeeder)->run();
}

function superAdmin(): User
{
    return tap(User::factory()->create())->assignRole('superadmin');
}

function sldOfficer(): User
{
    return tap(User::factory()->create())->assignRole('admin');
}
