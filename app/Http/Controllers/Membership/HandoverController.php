<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Models\CashHandover;
use App\Services\Membership\CashHandovers;
use App\Services\QrCodes;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The two live screens of a cash handover (student and committee member), kept in sync by polling. */
class HandoverController extends Controller
{
    public function __construct(private readonly CashHandovers $handovers) {}

    public function show(Request $request, CashHandover $handover, QrCodes $qr): Response
    {
        $side = $this->handovers->sideOf($handover, $request->user()) ?? abort(403);
        $handover->load('student', 'committee', 'payment.club');
        $token = $side === 'student' ? $this->handovers->qrPayload($handover, $request->user()) : null;

        return Inertia::render('Membership/Handover', [
            'side' => $side,
            'handover' => [
                'id' => $handover->id,
                'status' => $handover->status->value,
                'club' => $handover->payment->club->name,
                'club_slug' => $handover->payment->club->slug,
                'amount_sen' => $handover->payment->amount_sen,
                'student' => ['name' => $handover->student->name, 'photo_url' => $handover->student->photoUrl()],
                'committee' => ['name' => $handover->committee->name, 'photo_url' => $handover->committee->photoUrl()],
                'student_ready' => $handover->student_ready_at !== null,
                'committee_ready' => $handover->committee_ready_at !== null,
                'student_done' => $handover->student_done_at !== null,
                'committee_done' => $handover->committee_done_at !== null,
                'expires_at' => $handover->expires_at->toDayDateTimeString(),
            ],
            'qrSvg' => $token ? $qr->svg($token) : null,
        ]);
    }

    public function act(Request $request, CashHandover $handover): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:accept,decline,ready,photo,done'],
            'photo' => ['required_if:action,photo', 'nullable', 'image', 'max:12288'],
        ]);
        $user = $request->user();

        try {
            match ($data['action']) {
                'accept' => $this->handovers->respond($handover, $user, true),
                'decline' => $this->handovers->respond($handover, $user, false),
                'ready' => $this->handovers->ready($handover, $user),
                'photo' => $this->handovers->photo($handover, $user, $data['photo']),
                'done' => $this->handovers->done($handover, $user),
            };
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }
}
