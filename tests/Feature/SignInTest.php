<?php

use App\Exceptions\NotInRecordsException;
use App\Models\Staff;
use App\Services\Auth\SignInService;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

beforeEach(fn () => seedCamsys());

it('rejects accounts outside @qiu.edu.my', function () {
    app(SignInService::class)->signIn('someone@gmail.com', 'Someone', null, 'g1');
})->throws(NotInRecordsException::class);

it('rejects QIU accounts that are neither in camsys nor staff', function () {
    app(SignInService::class)->signIn('stranger@qiu.edu.my', 'Stranger', null, 'g1');
})->throws(NotInRecordsException::class);

it('signs a camsys student in, links the record, keeps the Google photo and the official name', function () {
    $user = app(SignInService::class)->signIn('ahmad.razak@example.com', 'Google Name', 'https://photo', 'g1', enforceDomain: false);

    expect($user->name)->toBe('Ahmad Bin Razak')
        ->and($user->avatar_url)->toBe('https://photo')
        ->and($user->hasRole('student'))->toBeTrue()
        ->and($user->student->student_number)->toBe('STD20240001');
});

it('lets registered staff in without a camsys record', function () {
    Staff::create(['email' => 'lecturer@qiu.edu.my', 'name' => 'Dr Lecturer']);

    $user = app(SignInService::class)->signIn('Lecturer@qiu.edu.my', 'Dr L', null, 'g2');

    expect($user->hasRole('staff'))->toBeTrue()->and($user->staff->email)->toBe('lecturer@qiu.edu.my');
});

it('grants Super Admin to configured emails', function () {
    config(['sld.superadmin_emails' => ['boss@qiu.edu.my']]);

    expect(app(SignInService::class)->signIn('boss@qiu.edu.my', 'Boss', null, 'g3')->hasRole('superadmin'))->toBeTrue();
});

it('sends Google accounts from another hosted domain to the not-in-records page', function () {
    $google = (new GoogleUser)->setRaw(['hd' => 'gmail.com'])->map(['id' => '1', 'email' => 'x@gmail.com', 'name' => 'X']);
    Socialite::shouldReceive('driver->user')->andReturn($google);

    $this->get('/auth/google/callback')->assertRedirect(route('not-in-records'));
    $this->assertGuest();
});

it('logs a staff member in through the Google callback', function () {
    Staff::create(['email' => 'lecturer@qiu.edu.my', 'name' => 'Dr Lecturer']);
    $google = (new GoogleUser)->setRaw(['hd' => 'qiu.edu.my'])->map(['id' => '9', 'email' => 'lecturer@qiu.edu.my', 'name' => 'Dr L', 'avatar' => 'https://p']);
    Socialite::shouldReceive('driver->user')->andReturn($google);

    $this->get('/auth/google/callback')->assertRedirect(route('clubs.index'));
    $this->assertAuthenticated();
});

it('does not register the dev login outside local', function () {
    $this->get('/dev/login-as')->assertNotFound();
});
