<?php

namespace App\Services\Reminders;

use App\Enums\ApprovalDecision;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ScopeType;
use App\Models\Agm;
use App\Models\Approval;
use App\Models\Club;
use App\Models\Document;
use App\Models\Event;
use App\Models\RoleAssignment;
use App\Models\SldRequest;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Agm\AgmPeriods;
use App\Services\Approvals\ApproverResolver;
use App\Services\Events\IndemnityService;
use App\Services\Events\ProposalRules;
use App\Services\Meetings\Meetings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Daily deadline reminders not covered elsewhere: approval steps, proposal lead time, indemnity
 * closing, AGM deadlines and replies to SLD. Post-event documents (events:send-reminders) and membership grace
 * (MembershipLifecycle) send their own. Each reminder is sent once, keyed in reminder_log.
 */
class Reminders
{
    /** Days before a deadline on which to remind. */
    public const LEAD_DAYS = [7, 1];

    private int $sent = 0;

    public function __construct(
        private readonly ApproverResolver $approvers,
        private readonly ProposalRules $rules,
        private readonly IndemnityService $indemnities,
        private readonly AgmPeriods $periods,
        private readonly Meetings $meetings,
    ) {}

    public function run(CarbonImmutable $today): int
    {
        $this->sent = 0;
        $this->approvals($today);
        $this->proposalLeadTime($today);
        $this->indemnityClosing($today);
        $this->agmDeadlines($today);
        $this->sldRequests($today);

        return $this->sent;
    }

    /** Approvers: the day before a step is due, and each day it is overdue. */
    private function approvals(CarbonImmutable $today): void
    {
        $steps = Approval::with('document.club', 'document.event')->where('decision', ApprovalDecision::Pending)->whereNotNull('due_at')
            ->whereDate('due_at', '<=', $today->addDay())
            ->whereHas('document', fn ($q) => $q->where('status', DocumentStatus::InReview))->get();
        foreach ($steps as $step) {
            $overdue = $step->due_at->lt($today);
            $this->send("approval:{$step->id}:".($overdue ? $today->toDateString() : 'due'),
                $this->approvers->candidates($step->document, $step->role),
                ($overdue ? 'Overdue: ' : 'Due tomorrow: ').$step->document->type->label().' · '.($step->document->event->name ?? $step->document->club->name),
                "Your approval as {$step->role->label()} was due {$step->due_at->format('D j M')}.",
                route('approvals.show', $step));
        }
    }

    /** Draft or returned proposals whose last day to submit (1.5 or 3 months ahead) is 7 days or 1 day away. */
    private function proposalLeadTime(CarbonImmutable $today): void
    {
        $drafts = Document::with('event')->where('type', DocumentType::Proposal)
            ->whereIn('status', [DocumentStatus::Draft, DocumentStatus::Returned])
            ->whereHas('event', fn ($q) => $q->where('starts_at', '>', $today))->get();
        foreach ($drafts as $doc) {
            $event = $doc->event;
            $large = $event->expected_participants >= ProposalRules::LARGE_EVENT_PARTICIPANTS;
            $lastDay = CarbonImmutable::parse($event->starts_at)->startOfDay()->subMonthsNoOverflow($large ? 3 : 1)->subDays($large ? 0 : 15);
            foreach (self::LEAD_DAYS as $days) {
                if ($lastDay->equalTo($today->addDays($days))) {
                    $this->send("proposal-lead:{$doc->id}:{$days}", $this->eventCommittee($event),
                        "Submit the proposal for {$event->name}",
                        "The last day to submit is {$lastDay->format('D j M Y')} (".($large ? '3 months' : '1.5 months').' before the event).',
                        route('proposals.edit', $event));
                }
            }
        }
    }

    /** Outdoor events: people who still have not signed, 2 days before signing closes. */
    private function indemnityClosing(CarbonImmutable $today): void
    {
        $events = Event::where('is_outdoor', true)->where('status', DocumentStatus::Approved)
            ->whereBetween('starts_at', [$today->addDays(3), $today->addDays(6)])->get();
        foreach ($events as $event) {
            $closes = CarbonImmutable::parse($event->indemnityClosesAt());
            if ($closes->startOfDay()->diffInDays($today, absolute: true) > 2) {
                continue;
            }
            $signed = $event->indemnities()->pluck('user_id');
            $this->send("indemnity:{$event->id}", $this->indemnities->mustSign($event)->whereNotIn('id', $signed),
                "Sign the indemnity for {$event->name}",
                "Signing closes {$closes->format('D j M')}. Without it you cannot clock in.",
                route('events.indemnity', $event));
        }
    }

    /** Clubs that have not scheduled the AGM, or not submitted the package, 7 days and 1 day before each deadline. */
    private function agmDeadlines(CarbonImmutable $today): void
    {
        $period = $this->periods->current();
        if (! $period) {
            return;
        }
        $agms = Agm::where('agm_period_id', $period->id)->get()->keyBy('club_id');
        foreach (self::LEAD_DAYS as $days) {
            foreach (Club::all() as $club) {
                $agm = $agms[$club->id] ?? null;
                if (! $agm && $period->agm_deadline->isSameDay($today->addDays($days))) {
                    $this->send("agm-schedule:{$period->id}:{$club->id}:{$days}", $this->meetings->committee($club),
                        'Schedule your AGM', "Hold the AGM by {$period->agm_deadline->format('D j M Y')}.", route('agm.show', $club));
                }
                if (! $agm?->package_submitted_at && $period->report_deadline->isSameDay($today->addDays($days))) {
                    $this->send("agm-package:{$period->id}:{$club->id}:{$days}", $this->meetings->committee($club),
                        'Submit the AGM package', "AGM Report, Financial Report, Full Member List and Annual Activities Report are due {$period->report_deadline->format('D j M Y')}.", route('agm.show', $club));
                }
            }
        }
    }

    /** Clubs: the day before an SLD request is due, then daily while overdue (SLD sees it on the dashboard). */
    private function sldRequests(CarbonImmutable $today): void
    {
        foreach (SldRequest::with('club')->whereNull('replied_at')->whereDate('due_on', '<=', $today->addDay())->get() as $request) {
            $overdue = $request->due_on->lt($today);
            $this->send("sld-request:{$request->id}:".($overdue ? $today->toDateString() : 'due'), $this->meetings->committee($request->club),
                ($overdue ? 'Overdue reply to SLD: ' : 'Reply to SLD by tomorrow: ').$request->subject,
                "SLD asked on {$request->created_at->format('j M')}; replies are due within 7 working days.",
                route('clubs.show', $request->club));
        }
    }

    /** @return Collection<int, User> */
    private function eventCommittee(Event $event): Collection
    {
        return User::whereIn('id', RoleAssignment::where('scope_type', ScopeType::Event)->where('scope_id', $event->id)->select('user_id'))->get();
    }

    /** @param Collection<int, User> $people */
    private function send(string $key, Collection $people, string $title, string $body, string $url): void
    {
        if ($people->isEmpty() || ! DB::table('reminder_log')->insertOrIgnore(['key' => $key, 'sent_at' => now()])) {
            return;
        }
        Notification::send($people, new DocumentNotification($title, $body, $url));
        $this->sent++;
    }
}
