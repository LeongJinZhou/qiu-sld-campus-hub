<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubFee;
use App\Models\MembershipDrive;
use App\Services\AuditLogger;
use App\Services\Time\SessionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Treasurer sets the fee per session; SLD approves it and runs membership drives. */
class FeeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(Request $request, Club $club, SessionResolver $sessions): RedirectResponse
    {
        Gate::authorize('treasure', $club);
        $data = $request->validate([
            'amount_rm' => ['required', 'numeric', 'min:0', 'max:1000'],
            'payment_url' => ['nullable', 'url:https', 'max:1024', 'required_unless:amount_rm,0'],
            'account_name' => ['nullable', 'string', 'max:255', 'required_unless:amount_rm,0'],
        ]);
        $fee = ClubFee::updateOrCreate(['club_id' => $club->id, 'session_id' => $sessions->current()->id], [
            'amount_sen' => (int) round($data['amount_rm'] * 100),
            'payment_url' => $data['payment_url'] ?? null,
            'account_name' => $data['account_name'] ?? null,
            'set_by' => $request->user()->id,
            'approved_by' => null,
            'approved_at' => null,
        ]);
        $this->audit->log('fee.set', $club, ['amount_sen' => $fee->amount_sen]);

        return back()->with('success', 'Fee submitted to SLD for approval.');
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Fees/Index', [
            'fees' => ClubFee::query()->join('clubs', 'clubs.id', '=', 'club_fees.club_id')->join('sessions_academic', 'sessions_academic.id', '=', 'club_fees.session_id')
                ->orderByRaw('approved_at IS NOT NULL')->orderBy('clubs.name')
                ->get(['club_fees.*', 'clubs.name as club', 'sessions_academic.name as session'])
                ->map(fn ($f) => ['id' => $f->id, 'club' => $f->club, 'session' => $f->session, 'amount_sen' => $f->amount_sen, 'account_name' => $f->account_name, 'payment_url' => $f->payment_url, 'approved' => $f->approved_at !== null]),
            'drives' => MembershipDrive::with('club:id,name')->latest('starts_at')->limit(20)->get()
                ->map(fn ($d) => ['id' => $d->id, 'title' => $d->title, 'club' => $d->club->name ?? 'All clubs', 'starts_at' => $d->starts_at->toDayDateTimeString(), 'ends_at' => $d->ends_at->toDayDateTimeString()]),
            'clubs' => Club::orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
        ]);
    }

    public function approve(Request $request, ClubFee $fee): RedirectResponse
    {
        $fee->update(['approved_by' => $request->user()->id, 'approved_at' => now()]);
        $this->audit->log('fee.approved', $fee, ['amount_sen' => $fee->amount_sen]);

        return back()->with('success', 'Fee approved — students can join now.');
    }

    public function drive(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'club_id' => ['nullable', 'exists:clubs,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);
        MembershipDrive::create([...$data, 'set_by' => $request->user()->id]);

        return back()->with('success', 'Membership drive scheduled.');
    }
}
