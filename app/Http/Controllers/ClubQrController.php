<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\RedirectResponse;

/** Permanent club QR target. Behind `auth`, so guests sign in first and are sent back here. */
class ClubQrController extends Controller
{
    public function __invoke(string $token): RedirectResponse
    {
        return to_route('clubs.show', Club::where('qr_token', $token)->firstOrFail());
    }
}
