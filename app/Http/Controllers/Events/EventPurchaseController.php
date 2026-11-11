<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\StorePostRequest;
use App\Models\Event;
use App\Models\EventPurchase;
use App\Models\User;
use App\Services\Events\EventPurchases;
use App\Services\Finance\FundWithdrawals;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Purchase claims page: snap, confirm items, verify, pay back, and budget against spending. */
class EventPurchaseController extends Controller
{
    public function __construct(private readonly EventPurchases $purchases) {}

    public function index(Request $request, Event $event, FundWithdrawals $funds): Response
    {
        $user = $request->user();
        abort_unless($this->purchases->canLog($user, $event), 403);
        $organiser = $user->can('organises', $event);
        $list = EventPurchase::with('buyer', 'verifier', 'payer')->where('event_id', $event->id)
            ->when(! $organiser, fn ($q) => $q->where(fn ($w) => $w->where('bought_by', $user->id)->orWhere('verifier_id', $user->id)))
            ->latest()->get();

        return Inertia::render('Events/Purchases', [
            'event' => [...$event->only('slug', 'name'), 'ended' => $event->ends_at->isPast(), 'club' => $event->club->only('slug', 'name')],
            'purchases' => $list->map(fn (EventPurchase $p) => [
                'id' => $p->id, 'status' => $p->status, 'paid_with' => $p->paid_with, 'items' => $p->items ?? [], 'total_sen' => $p->total_sen,
                'shop' => $p->shop, 'purchased_on' => $p->purchased_on?->toDateString(), 'budget_line' => $p->budget_line, 'ai_confidence' => $p->ai_confidence,
                'buyer' => ['id' => $p->bought_by, 'name' => $p->buyer->name, 'qr_token' => $organiser && $p->status === EventPurchase::OWED ? $p->buyer->qr_token : null],
                'verifier' => $p->verifier?->name, 'payer' => $p->payer?->name, 'review_note' => $p->review_note,
                'settle_when' => $p->settle_when, 'settle_method' => $p->settle_method, 'payout_check' => $p->payout_check, 'payout_payee' => $p->payout_payee,
                'mine' => $p->bought_by === $user->id, 'i_paid' => $p->paid_back_by === $user->id, 'to_verify' => $p->status === EventPurchase::PENDING && $p->verifier_id === $user->id,
                'receipt_url' => URL::temporarySignedRoute('purchases.file', now()->addMinutes(30), [$p, 'receipt']),
                'payout_url' => $p->payout_path ? URL::temporarySignedRoute('purchases.file', now()->addMinutes(30), [$p, 'payout']) : null,
                'at' => $p->created_at?->diffForHumans(),
            ]),
            'budget' => $this->purchases->budget($event),
            'budgetLines' => array_column($funds->budgetLines($event), 'description'),
            'verifiers' => $this->purchases->verifiers($event)->reject(fn (User $u) => $u->is($user))->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'can' => ['organise' => $organiser],
        ]);
    }

    public function snap(Request $request, Event $event): RedirectResponse
    {
        $request->validate(['receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:10240']]);

        return $this->attempt(fn () => $this->purchases->snap($event, $request->user(), $request->file('receipt')), 'Reading the receipt…');
    }

    public function submit(Request $request, EventPurchase $purchase): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.name' => ['required', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_sen' => ['required', 'integer', 'min:0', 'max:100000000'],
            'shop' => ['nullable', 'string', 'max:150'],
            'purchased_on' => ['nullable', 'date', 'before_or_equal:today'],
            'budget_line' => ['nullable', 'string', 'max:255'],
            'paid_with' => ['required', 'in:club,own'],
            'verifier_id' => ['nullable', 'required_if:paid_with,own', 'integer'],
        ]);

        return $this->attempt(fn () => $this->purchases->submit($purchase, $request->user(), $data), $data['paid_with'] === 'club' ? 'Recorded in the club finance.' : 'Sent for verification.');
    }

    public function verify(Request $request, EventPurchase $purchase): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'note' => ['nullable', 'required_if:approve,false', 'string', 'max:500'], 'settle_when' => ['nullable', 'in:now,after_event']]);

        return $this->attempt(fn () => $this->purchases->verify($purchase, $request->user(), $data['approve'], $data['note'] ?? null, $data['settle_when'] ?? null), $data['approve'] ? 'Verified.' : 'Rejected.');
    }

    public function payCash(Request $request, EventPurchase $purchase): RedirectResponse
    {
        $request->validate(['photo' => ['required', ...StorePostRequest::IMAGE_RULES]]);

        return $this->attempt(fn () => $this->purchases->payCash($purchase, $request->user(), $request->file('photo')), 'Photo checked. Waiting for the buyer to confirm they got the cash.');
    }

    public function payOnline(Request $request, EventPurchase $purchase): RedirectResponse
    {
        $request->validate(['receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240']]);

        return $this->attempt(fn () => $this->purchases->payOnline($purchase, $request->user(), $request->file('receipt')), 'Checking the transfer receipt…');
    }

    public function received(Request $request, EventPurchase $purchase): RedirectResponse
    {
        return $this->attempt(fn () => $this->purchases->confirmReceived($purchase, $request->user()), 'Thank you. The payment is recorded in the finance records of the club.');
    }

    public function confirmTransfer(Request $request, EventPurchase $purchase): RedirectResponse
    {
        return $this->attempt(fn () => $this->purchases->confirmTransfer($purchase, $request->user()), 'Recorded in the club finance.');
    }

    /** Receipts are private: the buyer, the verifier and the organisers. */
    public function file(Request $request, EventPurchase $purchase, string $kind): StreamedResponse
    {
        $user = $request->user();
        abort_unless(in_array($kind, ['receipt', 'payout'], true), 404);
        abort_unless(in_array($user->id, [$purchase->bought_by, $purchase->verifier_id], true) || $user->can('organises', $purchase->event), 403);
        $path = $kind === 'receipt' ? $purchase->receipt_path : $purchase->payout_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
