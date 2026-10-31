<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\EventRegistration;
use App\Models\Post;
use App\Models\User;
use App\Services\Profiles\Certificates;
use App\Services\Profiles\PublicProfile;
use App\Services\QrCodes;
use App\Services\Uploads\ImageUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request, PublicProfile $profile, QrCodes $qr, Certificates $certificates): Response
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
            'saved' => Inertia::defer(fn () => $user->savedPosts()->visible()->with('media', 'event')->latest('saves.created_at')->limit(30)->get()
                ->map(fn (Post $p) => ['id' => $p->id, 'url' => route('posts.show', $p), 'image' => $p->media->first()?->url() ?? $p->event?->posterUrl(), 'caption' => $p->caption])),
            'certificates' => $certificates->available($user),
            // Events they signed up for that have not happened yet, with anything still to do.
            'upcoming' => EventRegistration::with('event.club')->where('user_id', $user->id)
                ->whereHas('event', fn ($q) => $q->where('ends_at', '>', now()))->get()->sortBy('event.starts_at')->values()
                ->map(fn (EventRegistration $r) => [
                    'name' => $r->event->name, 'slug' => $r->event->slug, 'club' => $r->event->club->name,
                    'starts_at' => $r->event->starts_at->toIso8601String(), 'venue' => $r->event->venue, 'poster_url' => $r->event->posterUrl(),
                    'needs_payment' => $r->status === 'pending_payment',
                    'needs_indemnity' => $r->event->is_outdoor && ! $r->event->indemnities()->where('user_id', $user->id)->exists(),
                ]),
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
            $user->avatar_path = $request->hasFile('photo') ? app(ImageUpload::class)->store($request->file('photo'), 'avatars') : null;
        }

        // Photo-only saves must not wipe the other settings.
        if ($request->has('hidden_sections') || $request->has('personal_email')) {
            $user->fill([
                'hidden_sections' => $request->validated('hidden_sections', []),
                'personal_email' => $request->validated('personal_email'),
            ]);
        }
        $user->save();

        return back()->with('success', 'Profile updated.');
    }
}
