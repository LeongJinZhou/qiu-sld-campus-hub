<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\NotInRecordsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DevLoginRequest;
use App\Models\Staff;
use App\Models\Student;
use App\Services\Auth\SignInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/** Login-as for local testing without Google. Routes are only registered when APP_ENV=local. */
class DevLoginController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Auth/DevLogin', [
            'accounts' => [
                ...collect(config('sld.superadmin_emails'))->map(fn (string $email) => [
                    'email' => $email, 'name' => $email, 'detail' => 'Super Admin',
                ]),
                ...Student::orderBy('name')->get()->map(fn (Student $s) => [
                    'email' => $s->email, 'name' => $s->name, 'detail' => "{$s->student_number} · {$s->year_group}",
                ]),
                ...Staff::orderBy('name')->get()->map(fn (Staff $s) => [
                    'email' => $s->email, 'name' => $s->name, 'detail' => (string) $s->title,
                ]),
            ],
        ]);
    }

    public function store(DevLoginRequest $request, SignInService $signIn): RedirectResponse
    {
        try {
            $user = $signIn->signIn($request->validated('email'), $request->validated('email'), null, null, enforceDomain: false);
        } catch (NotInRecordsException) {
            return to_route('not-in-records');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('feed'));
    }
}
