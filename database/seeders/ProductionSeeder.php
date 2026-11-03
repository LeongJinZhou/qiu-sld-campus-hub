<?php

namespace Database\Seeders;

use App\Enums\GlobalRole;
use App\Models\ClubCategory;
use App\Models\IntakeMap;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\User;
use App\Services\Camsys\StatusMapper;
use App\Services\Roles\RoleService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * A clean live install: the four account roles, the camsys status map, QIU intakes and club categories, plus the
 * Super Admin and the SLD Officer. No demo clubs, students, staff, events or money. Safe to run again.
 *
 *   php artisan db:seed --class=ProductionSeeder --force
 *
 * The accounts can be changed with SUPERADMIN_EMAIL and SLD_OFFICER_EMAIL / SLD_OFFICER_NAME.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (GlobalRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
        if (! Setting::read(StatusMapper::SETTING_KEY)) {
            Setting::write(StatusMapper::SETTING_KEY, StatusMapper::DEFAULTS);
        }
        foreach ([1 => 3, 2 => 7, 3 => 11] as $intake => $month) {
            IntakeMap::updateOrCreate(['intake_number' => $intake], ['month' => $month]);
        }
        foreach (['Academic', 'Arts & Culture', 'Sports', 'Community Service', 'Leadership'] as $name) {
            ClubCategory::firstOrCreate(['name' => $name]);
        }

        $roles = app(RoleService::class);

        // Super Admin: everything, including who else is Super Admin. Also listed in SUPERADMIN_EMAILS.
        $super = User::firstOrCreate(['email' => strtolower((string) env('SUPERADMIN_EMAIL', 'ai@qiu.edu.my'))], ['name' => 'Super Admin']);
        $roles->grantGlobal($super, GlobalRole::SuperAdmin);

        // SLD Officer: runs the SLD portal and manages the other SLD admins and roles. A staff record lets her sign in.
        $email = strtolower((string) env('SLD_OFFICER_EMAIL', 'yeeshiu.chong@qiu.edu.my'));
        $name = (string) env('SLD_OFFICER_NAME', 'Yee Shiu Chong');
        $officer = User::firstOrCreate(['email' => $email], ['name' => $name]);
        Staff::updateOrCreate(['email' => $email], ['user_id' => $officer->id, 'name' => $name, 'title' => 'SLD Officer', 'department' => 'Student Life Division', 'active' => true]);
        $roles->grantGlobal($officer, GlobalRole::Staff);
        $roles->grantGlobal($officer, GlobalRole::Admin);
    }
}
