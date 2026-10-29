<?php

namespace Database\Seeders;

use App\Models\ClubCategory;
use App\Models\IntakeMap;
use App\Models\ProgrammeAbbrev;
use App\Models\Setting;
use App\Services\Camsys\StatusMapper;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['student', 'staff', 'admin', 'superadmin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        Setting::write(StatusMapper::SETTING_KEY, StatusMapper::DEFAULTS);

        // Dummy-ID fallback only; real QIU-YYYYMM-NNNN IDs carry the month. Adjust in Admin › Settings.
        foreach ([1 => 1, 2 => 5, 3 => 9] as $intake => $month) {
            IntakeMap::updateOrCreate(['intake_number' => $intake], ['month' => $month]);
        }

        $abbreviations = [
            'CS101' => 'BCS', 'BA202' => 'DBA', 'ENG301' => 'BME', 'NUR102' => 'DN', 'MIT501' => 'MIT',
            'ACC301' => 'BAcc', 'BMC101' => 'BMC', 'DGD101' => 'DGD', 'EEE201' => 'BEE', 'BHM101' => 'BHM',
        ];
        foreach ($abbreviations as $code => $abbreviation) {
            ProgrammeAbbrev::updateOrCreate(['programme_code' => $code], ['abbreviation' => $abbreviation]);
        }

        foreach (['Academic', 'Arts & Culture', 'Sports', 'Community Service', 'Leadership'] as $name) {
            ClubCategory::firstOrCreate(['name' => $name]);
        }
    }
}
