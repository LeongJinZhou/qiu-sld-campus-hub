<?php

namespace App\Http\Controllers\Membership;

use App\Enums\PaymentStatus;
use App\Enums\ScopedRole;
use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Membership;
use App\Models\MembershipDrive;
use App\Models\MembershipPayment;
use App\Models\User;
use App\Services\Membership\CashHandovers;
use App\Services\Membership\MembershipService;
use App\Services\Membership\OnlinePayments;
use App\Services\Time\SessionResolver;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MembershipController extends Controller
{
    public function __construct(private readonly MembershipService $memberships) {}

    public function show(Request $request, Club $club, SessionResolver $sessions): Response
    {
        $user = $request->user();
        $membership = Membership::where('club_id', $club->id)->where('user_id', $user->id)->first();
        $fee = $this->memberships->fee($club);
        $latest = $membership?->payments()->with('handover.committee')->latest('id')->first();

        return Inertia::render('Membership/Join', [
            'club' => ['slug' => $club->slug, 'name' => $club->name, 'logo_url' => $club->logoUrl()],
            'fee' => $fee ? ['amount_sen' => $fee->amount_sen, 'payment_url' => $fee->payment_url, 'account_name' => $fee->account_name] : null,
            'drive' => MembershipDrive::running()->where(fn ($q) => $q->whereNull('club_id')->orWhere('club_id', $club->id))->first(['title', 'ends_at']),
            'membership' => $membership ? [
                'status' => $membership->status->value,
                'expires_on' => $membership->expires_on?->format('j M Y'),
                'grace_ends_on' => $membership->grace_ends_on?->format('j M Y'),
            ] : null,
            'payment' => $latest ? [
                'method' => $latest->method,
                'status' => $latest->status->value,
                'flags' => $latest->flags ?? [],
                'review_note' => $latest->review_note,
                'handover_id' => $latest->handover?->id,
                'handover_status' => $latest->handover?->status->value,
            ] : null,
            'committee' => $club->roleAssignments()->where('session_id', $sessions->current()->id)->where('role', ScopedRole::ClubCommittee)
                ->with('user')->get()->map(fn ($a) => ['id' => $a->user_id, 'name' => $a->user->name, 'position' => $a->position, 'photo_url' => $a->user->photoUrl()]),
        ]);
    }

    public function join(Request $request, Club $club): RedirectResponse
    {
        try {
            $membership = $this->memberships->join($club, $request->user(), $request->boolean('acknowledged'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $membership->status->value === 'active' ? "Welcome to {$club->name}!" : 'Now pay online or in cash to activate your membership.');
    }

    public function receipt(Request $request, Club $club, OnlinePayments $payments): RedirectResponse
    {
        $data = $request->validate(['receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192']]);
        $membership = Membership::where('club_id', $club->id)->where('user_id', $request->user()->id)->firstOrFail();

        try {
            $payments->submit($membership, $data['receipt']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Receipt uploaded. We are checking it now.');
    }

    public function cash(Request $request, Club $club, CashHandovers $handovers): RedirectResponse
    {
        $data = $request->validate(['committee_user_id' => ['required', 'exists:users,id']]);
        $membership = Membership::where('club_id', $club->id)->where('user_id', $request->user()->id)->firstOrFail();

        try {
            $handover = $handovers->request($membership, User::findOrFail($data['committee_user_id']));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('handovers.show', $handover);
    }

    /** Treasurer review queue: AI reading shown beside the receipt image. */
    public function reviewQueue(Club $club): Response
    {
        Gate::authorize('treasure', $club);

        return Inertia::render('Membership/Review', [
            'club' => $club->only('slug', 'name'),
            'fee_sen' => $this->memberships->fee($club)?->amount_sen,
            'items' => MembershipPayment::with('user.student')->where('club_id', $club->id)
                ->whereIn('status', [PaymentStatus::PendingReview, PaymentStatus::AutoConfirmed])->latest()->limit(50)->get()
                ->map(fn (MembershipPayment $p) => [
                    'id' => $p->id,
                    'student' => $p->user->name,
                    'student_number' => $p->user->student?->student_number,
                    'status' => $p->status->value,
                    'amount_sen' => $p->amount_sen,
                    'ai' => ['amount_sen' => $p->ai_amount_sen, 'date' => $p->ai_date?->toDateString(), 'reference' => $p->ai_reference, 'payee' => $p->ai_payee, 'confidence' => $p->ai_confidence],
                    'flags' => $p->flags ?? [],
                    'receipt_url' => URL::temporarySignedRoute('payments.receipt', now()->addMinutes(30), $p),
                    'at' => $p->created_at?->diffForHumans(),
                ]),
        ]);
    }

    public function decide(Request $request, MembershipPayment $payment): RedirectResponse
    {
        Gate::authorize('treasure', $payment->club);
        $data = $request->validate(['approve' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:500']]);

        try {
            $this->memberships->review($payment, $request->user(), $data['approve'], $data['note'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['approve'] ? 'Payment confirmed — membership active.' : 'Payment rejected.');
    }

    /** Receipts are private: the payer, the Treasurer and SLD only. */
    public function receiptFile(Request $request, MembershipPayment $payment): StreamedResponse
    {
        abort_unless($payment->user_id === $request->user()->id || $request->user()->can('treasure', $payment->club), 403);

        return Storage::disk('local')->response((string) $payment->receipt_path);
    }
}
