<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Profiles\PublicProfile;
use Inertia\Inertia;
use Inertia\Response;

/** Personal permanent QR target: anyone who scans can view the public profile. */
class PublicProfileController extends Controller
{
    public function __invoke(string $token, PublicProfile $profile): Response
    {
        return Inertia::render('Profile/Public', [
            'profile' => $profile->for(User::where('qr_token', $token)->firstOrFail()),
        ]);
    }
}
