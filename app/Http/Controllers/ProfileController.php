<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Services\Profiles\PublicProfile;
use App\Services\QrCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request, PublicProfile $profile, QrCodes $qr): Response
    {
        $user = $request->user();
        $student = $user->student;
        $publicUrl = route('profile.public', $user->qr_token);

        return Inertia::render('Profile/Me', [
            'profile' => $profile->for($user, includeHidden: true),
            'account' => [
                'email' => $user->email,
                'student_number' => $student?->student_number,
                'status' => $student?->status->value,
                'has_photo_override' => $user->avatar_path !== null,
                'personal_email' => $user->personal_email,
                'hidden_sections' => $user->hidden_sections ?? [],
            ],
            'hideableSections' => User::HIDEABLE_SECTIONS,
            'publicUrl' => $publicUrl,
            'qrSvg' => $qr->svg($publicUrl),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($request->hasFile('photo') || $request->boolean('remove_photo')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('photo')?->store('avatars', 'public');
        }

        $user->fill([
            'hidden_sections' => $request->validated('hidden_sections', []),
            'personal_email' => $request->validated('personal_email'),
        ])->save();

        return back()->with('success', 'Profile updated.');
    }
}
