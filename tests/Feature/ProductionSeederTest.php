<?php

use App\Models\Club;
use App\Models\Event;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ProductionSeeder;

it('installs a clean live system: the Super Admin and SLD Officer, settings, and no demo data', function () {
    (new ProductionSeeder)->run();
    (new ProductionSeeder)->run(); // safe to run again

    $super = User::where('email', 'ai@qiu.edu.my')->sole();
    $officer = User::where('email', 'yeeshiu.chong@qiu.edu.my')->sole();
    expect($super->hasRole('superadmin'))->toBeTrue()
        ->and($officer->hasRole('admin'))->toBeTrue()->and($officer->hasRole('staff'))->toBeTrue()->and($officer->isSldAdmin())->toBeTrue()
        ->and(Staff::where('email', 'yeeshiu.chong@qiu.edu.my')->sole()->title)->toBe('SLD Officer')
        ->and(User::count())->toBe(2)->and(Staff::count())->toBe(1)
        ->and(Club::count())->toBe(0)->and(Event::count())->toBe(0)->and(Student::count())->toBe(0);
});
