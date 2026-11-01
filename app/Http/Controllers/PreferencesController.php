<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Personal settings: theme, and which guided tours have been seen (seen = false replays a tour). */
class PreferencesController extends Controller
{
    public const TOURS = ['student', 'committee'];

    public function theme(Request $request): RedirectResponse
    {
        $data = $request->validate(['theme' => ['required', Rule::in(['light', 'dark', 'system'])]]);
        $request->user()->forceFill(['theme' => $data['theme']])->save();

        return back();
    }

    public function tour(Request $request): RedirectResponse
    {
        $data = $request->validate(['tour' => ['required', Rule::in(self::TOURS)], 'seen' => ['required', 'boolean']]);
        $user = $request->user();
        $seen = collect($user->tours_seen ?? [])->reject(fn ($t) => $t === $data['tour']);
        $user->forceFill(['tours_seen' => $data['seen'] ? $seen->push($data['tour'])->values()->all() : $seen->values()->all()])->save();

        return back();
    }
}
