<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Models\CashHandover;
use App\Services\Membership\CashHandovers;
use App\Services\QrCodes;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'expires_at' => $handover->expires_at->format('D, j M Y, g:i A'),
                'cash_check' => $handover->cash_check,
                'photo_url' => $handover->photo_path ? URL::temporarySignedRoute('handovers.photo', now()->addMinutes(30), $handover) : null,
            ],
            // The member's camera captures only when it sees this student's own QR code.
            'expectToken' => $side === 'committee' ? $handover->student->qr_token : null,
            'qrSvg' => $token ? $qr->svg($token) : null,
        ]);
    }

    public function act(Request $request, CashHandover $handover): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:accept,decline,ready,photo,retake,confirm_cash,done'],
            'photo' => ['required_if:action,photo', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ]);
        $user = $request->user();

        try {
            match ($data['action']) {
                'accept' => $this->handovers->respond($handover, $user, true),
                'decline' => $this->handovers->respond($handover, $user, false),
                'ready' => $this->handovers->ready($handover, $user),
                'photo' => $this->handovers->photo($handover, $user, $data['photo']),
                'confirm_cash' => $this->handovers->confirmCash($handover, $user),
                'retake' => $this->handovers->retake($handover, $user),
                'done' => $this->handovers->done($handover, $user),
            };
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    /** The cash photo: the two people in the handover, or the club's committee, advisor and SLD. */
    public function photo(Request $request, CashHandover $handover): StreamedResponse
    {
        $handover->loadMissing('payment.club');
        abort_unless($this->handovers->sideOf($handover, $request->user()) !== null || Gate::allows('viewInternal', $handover->payment->club), 403);
        abort_unless($handover->photo_path && Storage::disk('local')->exists($handover->photo_path), 404);

        return Storage::disk('local')->response($handover->photo_path, "cash-payment-{$handover->payment_id}.jpg");
    }
}
