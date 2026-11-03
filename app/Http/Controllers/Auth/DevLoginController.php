<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\NotInRecordsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DevLoginRequest;
use App\Models\Staff;
use App\Models\Student;
use App\Services\Auth\SignInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login-as for testing without Google. Routes exist only when DEMO_LOGIN is on (always locally). On any hosted
 * server the page is locked: the passcode must be entered first, and only then is the account list sent.
 */
class DevLoginController extends Controller
{
    public static function unlocked(Request $request): bool
    {
        return app()->environment('local') || $request->session()->get('demo_unlocked') === true;
    }

    public function unlock(Request $request): RedirectResponse
    {
        $passcode = (string) $request->validate(['passcode' => ['required', 'string', 'max:200']])['passcode'];
        $hash = (string) config('sld.demo_passcode_hash');
        if ($hash === '' || ! Hash::check($passcode, $hash)) {
            return back()->withErrors(['passcode' => 'Wrong passcode.']);
        }
        $request->session()->regenerate();
        $request->session()->put('demo_unlocked', true);

        return redirect('/dev/login-as');
    }

    public function index(Request $request): Response
    {
        if (! self::unlocked($request)) {
            return Inertia::render('Auth/DevLogin', ['locked' => true, 'accounts' => []]);
        }

        return Inertia::render('Auth/DevLogin', [
            'locked' => false,
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
            // Google supplies the real name; locally, "jinzhou.leong@…" becomes "Jinzhou Leong".
            $email = $request->validated('email');
            $user = $signIn->signIn($email, Str::headline(str_replace('.', ' ', Str::before($email, '@'))), null, null, enforceDomain: false);
        } catch (NotInRecordsException) {
            return to_route('not-in-records');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('feed'));
    }
}
