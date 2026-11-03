<?php

namespace App\Services\Ai;

use App\Enums\ApprovalDecision;
use App\Enums\DocumentStatus;
use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Models\AcademicSession;
use App\Models\Approval;
use App\Models\Club;
use App\Models\ClubApplication;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Dashboard\ClubTracking;
use App\Services\Finance\Ledger;
use App\Services\Membership\CommitteeFees;
use App\Services\Membership\MembershipService;
use App\Services\Time\SessionResolver;
use Illuminate\Support\Collection;

/**
 * What the assistant may know for one person — the access rules live here and nowhere else:
 *
 * - everyone: every club (what it is, its membership fee) and every approved upcoming event (date, venue, fee,
 *   CCA hours), plus the pages their role uses;
 * - a member: their own memberships, registrations and tickets, and their clubs' events;
 * - a club's committee or advisor: that club's internal facts (money, payments waiting, committee fees,
 *   documents in review, weekly reports, event packages) — never another club's;
 * - SLD: every club's internal facts, overdue transfers, new-club applications, every event in any state.
 *
 * Each fact is a small document (title, text, optional link the person can open). Retrieval picks the ones that
 * match the question, so the model only ever sees facts this person is allowed to see.
 */
class AssistantKnowledge
{
    public function __construct(
        private readonly SessionResolver $sessions,
        private readonly MembershipService $memberships,
        private readonly Ledger $ledger,
        private readonly CommitteeFees $committeeFees,
        private readonly ClubTracking $tracking,
        private readonly ApprovalEngine $engine,
    ) {}

    /** @return list<array{id: string, kind: string, title: string, text: string, url: ?string}> */
    public function for(User $user): array
    {
        $docs = [];
        $add = function (string $kind, string $title, string $text, ?string $url = null) use (&$docs): void {
            $docs[] = ['id' => 'r'.(count($docs) + 1), 'kind' => $kind, 'title' => $title, 'text' => $text, 'url' => $url];
        };
        $session = $this->sessions->current();
        $sld = $user->isSldAdmin();
        $roles = RoleAssignment::query()->acting($session, training: true)->where('user_id', $user->id)->get()->keyBy('scope_id');
        $internal = $sld ? Club::pluck('id') : $roles->keys(); // clubs whose internal facts this person may see
        $memberOf = Membership::where('user_id', $user->id)->get()->keyBy('club_id');

        $this->pages($add, $user, $sld);
        $tracker = $internal->isNotEmpty() ? collect($this->tracking->build($session)['clubs'])->keyBy('slug') : collect();

        // Every club: public facts, plus internal ones where allowed.
        foreach (Club::with('category')->orderBy('name')->get() as $club) {
            $fee = $this->memberships->fee($club);
            $feeText = $fee ? ($fee->amount_sen > 0 ? 'Membership fee this session: RM '.number_format($fee->amount_sen / 100, 2).'.' : 'Membership is free this session.') : 'No membership fee set yet this session.';
            $mine = $memberOf->get($club->id);
            $role = $roles->get($club->id);
            $add('club', $club->name, trim(($club->category?->name ? "{$club->category->name} club. " : '').($club->bio ? "{$club->bio} " : '')."{$feeText}"
                .($mine ? " You are a member ({$mine->status->value}".($mine->expires_on ? ', until '.$mine->expires_on->format('j M Y') : '').').' : '')
                .($role ? ' You are '.($role->position ?? 'the advisor').($role->handover_state === 'training' ? ' (handover training, view only)' : '').'.' : '')), route('clubs.show', $club));
            if ($internal->contains($club->id)) {
                $this->internal($add, $club, $session, $tracker->get($club->slug));
            }
        }

        // Events: approved upcoming ones for everyone; any state for the clubs this person may see inside.
        $events = Event::with('club')->where(fn ($q) => $q->where(fn ($q) => $q->where('status', DocumentStatus::Approved)->where('ends_at', '>', now()->subDays(30)))
            ->orWhere(fn ($q) => $q->whereIn('club_id', $internal)->where('ends_at', '>', now()->subYear())))->orderBy('starts_at')->limit($sld ? 200 : 80)->get();
        foreach ($events as $e) {
            $state = $e->status === DocumentStatus::Approved ? 'approved' : 'proposal '.$e->status->value;
            $add('event', $e->name, "{$e->club->name} · {$e->starts_at->format('D j M Y g:ia')}".($e->venue ? " · {$e->venue}" : '')
                .' · '.($e->fee_sen > 0 ? 'fee RM '.number_format($e->fee_sen / 100, 2) : 'free').' · CCA '.$e->cca_participant.' h · '.$state
                .($e->registration_open && $e->ends_at->isFuture() ? ' · registration open' : ''), $user->can('view', $e) ? route('events.show', $e) : null);
        }

        foreach (EventRegistration::with('event.club')->where('user_id', $user->id)->whereHas('event', fn ($q) => $q->where('ends_at', '>', now()->subDays(60)))->get() as $r) {
            $add('ticket', "My registration: {$r->event->name}", "{$r->event->club->name} · {$r->event->starts_at->format('D j M Y g:ia')} · status {$r->status}", route('events.show', $r->event));
        }

        $waiting = Approval::with('document.club')->where('decision', ApprovalDecision::Pending)
            ->whereHas('document', fn ($q) => $q->where('status', DocumentStatus::InReview))->get()
            ->filter(fn (Approval $a) => $this->engine->canAct($a, $user))->take(30);
        foreach ($waiting as $a) {
            $add('approval', "Waiting for my signature: {$a->document->type->label()} · {$a->document->club?->name}", 'Due '.($a->due_at?->format('j M Y') ?? 'soon').'.', route('approvals.show', $a));
        }

        if ($sld) {
            $this->sldOnly($add);
        }

        return $docs;
    }

    /** One club's internal facts: only ever called for its committee/advisor or SLD. */
    /** @param array<string, mixed>|null $row the club's submissions-tracker row */
    private function internal(callable $add, Club $club, AcademicSession $session, ?array $row): void
    {
        $money = $this->tracking->money($session, $club)['monthly'];
        $in = array_sum(array_column($money, 'income_sen'));
        $out = array_sum(array_column($money, 'expense_sen'));
        $add('finance', "{$club->name}: money", 'Balance RM '.number_format($this->ledger->balance($club) / 100, 2).'. This session: income RM '.number_format($in / 100, 2)
            .', expenses RM '.number_format($out / 100, 2).'.', route('finance.show', $club));
        $waiting = MembershipPayment::where('club_id', $club->id)->whereIn('status', [PaymentStatus::PendingReview, PaymentStatus::CashPending])->count();
        $members = Membership::where('club_id', $club->id)->whereIn('status', [MembershipStatus::Active, MembershipStatus::Grace])->count();
        $add('members', "{$club->name}: members and payments", "{$members} active members. {$waiting} payment(s) waiting to be checked.", route('members.club', $club));
        $fees = collect($this->committeeFees->forClub($club));
        if ($fees->isNotEmpty()) {
            $add('committee', "{$club->name}: committee club fees", $fees->map(fn ($f) => "{$f['position']} {$f['name']}: ".str_replace('_', ' ', $f['status']))->implode('; ').'.', route('members.club', $club));
        }
        $docs = Document::where('club_id', $club->id)->whereIn('status', [DocumentStatus::InReview, DocumentStatus::Returned])->get();
        if ($docs->isNotEmpty()) {
            $add('documents', "{$club->name}: documents in progress", $docs->map(fn (Document $d) => "{$d->type->label()} ({$d->status->value})")->implode('; ').'.', route('clubs.show', $club));
        }
        if ($row) {
            $add('reports', "{$club->name}: weekly reports and event packages", "{$row['weekly_missing']} weekly report(s) missing; {$row['events_incomplete']} finished event(s) with an incomplete package"
                .($row['agm'] ? '; AGM report '.$row['agm']['report'].($row['agm']['package'] ? ', package submitted' : '') : '; no AGM yet').'.', route('meetings.index', $club));
        }
    }

    private function sldOnly(callable $add): void
    {
        foreach ($this->tracking->overdueTransfers() as $t) {
            $add('transfer', "Overdue transfer: {$t['club']} · {$t['for']}", 'RM '.number_format($t['total_sen'] / 100, 2)." due {$t['due_on']}, {$t['days_late']} day(s) late.", url("/withdrawals/{$t['id']}"));
        }
        foreach (ClubApplication::whereIn('status', ['advisor', 'sld'])->get() as $a) {
            $add('application', "New club application: {$a->data['name']}", ClubApplication::STATUSES[$a->status].'.', route('club-applications.show', $a));
        }
        $total = Club::all()->sum(fn (Club $c) => $this->ledger->balance($c));
        $add('finance', 'All clubs: total money', 'All club balances together: RM '.number_format($total / 100, 2).'.', url('/admin/finance'));
    }

    private function pages(callable $add, User $user, bool $sld): void
    {
        $add('page', 'Activities', 'My tickets and the clubs I run or joined.', route('activities'));
        $add('page', 'Campus calendar', 'Approved events, my meetings, weekly-report due dates and AGMs; month and year views.', route('calendar'));
        $add('page', 'Start a new club', 'Apply as President; the advisor signs; SLD approves.', route('club-applications.create'));
        $add('page', 'My profile and settings', 'Profile, CCA, certificates, dark mode, guided tour.', route('profile.me'));
        if ($user->staff()->exists() || $sld) {
            $add('page', 'Approvals inbox', 'Documents waiting for my signature.', route('approvals.index'));
        }
        if ($sld) {
            $add('page', 'Submissions tracker', 'Missing weekly reports, event packages, AGMs, overdue transfers for every club.', url('/admin/tracking'));
            $add('page', 'Club finance (all clubs)', 'Find any transaction; monthly and yearly charts; balances.', url('/admin/finance'));
            $add('page', 'SLD settings', 'Lead time, minimum photos, transfer deadline, handover overlap.', url('/admin/settings'));
        }
    }

    /**
     * The documents that best match the question (word overlap, title words count double).
     *
     * @param  list<array{id: string, kind: string, title: string, text: string, url: ?string}>  $docs
     * @return Collection<int, array{id: string, kind: string, title: string, text: string, url: ?string}>
     */
    public function retrieve(array $docs, string $question, int $k = 24): Collection
    {
        $words = $this->words($question);

        return collect($docs)->map(function (array $d) use ($words): array {
            $title = $this->words($d['title']);
            $body = $this->words($d['text'].' '.$d['kind']);
            $score = 0;
            foreach ($words as $w) {
                $score += (in_array($w, $title, true) ? 2 : 0) + (in_array($w, $body, true) ? 1 : 0)
                    + (collect([...$title, ...$body])->contains(fn ($t) => strlen($w) > 3 && (str_starts_with($t, $w) || str_starts_with($w, $t))) ? 0.5 : 0);
            }

            return [...$d, 'score' => $score];
        })->filter(fn ($d) => $d['score'] > 0)->sortByDesc('score')->take($k)->map(fn ($d) => collect($d)->except('score')->all())->values();
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        $stop = ['the', 'and', 'for', 'what', 'when', 'where', 'how', 'is', 'are', 'my', 'me', 'of', 'to', 'a', 'an', 'in', 'on', 'do', 'i', 'can', 'which', 'who', 'all', 'any', 'this', 'that', 'with', 'about'];

        return array_values(array_diff(preg_split('/[^a-z0-9]+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [], $stop));
    }
}
