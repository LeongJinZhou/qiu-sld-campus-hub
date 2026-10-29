<?php

namespace App\Services\Events;

use App\Enums\ApproverRole;
use App\Models\Document;
use App\Models\Event;
use App\Models\Staff;
use Carbon\CarbonImmutable;

/** Builds and stores the proposal JSON (06 template fields) and keeps the event row in step with it. */
class ProposalForm
{
    public const NATURES = [
        'sports' => 'Sports/Recreational', 'competition' => 'Competition', 'humanity' => 'Humanity/Volunteerism',
        'social' => 'Social Events/Festivals', 'fundraising' => 'Fundraising', 'environmental' => 'Environmental Initiatives',
        'seminar' => 'Seminars/Conference/Workshop', 'exhibition' => 'Exhibition/Convention', 'others' => 'Others',
    ];

    public const SDGS = [
        1 => 'No Poverty', 2 => 'Zero Hunger', 3 => 'Good Health and Well-being', 4 => 'Quality Education',
        5 => 'Gender Equality', 6 => 'Clean Water & Sanitation', 7 => 'Affordable & Clean Energy',
        8 => 'Decent Work and Economic Growth', 9 => 'Industry, Innovation & Infrastructure', 10 => 'Reducing Inequality',
        11 => 'Sustainable Cities and Communities', 12 => 'Responsible Consumption and Production', 13 => 'Climate Action',
        14 => 'Life Below Water', 15 => 'Life on Land', 16 => 'Peace, Justice and Strong Institutions', 17 => 'Partnership for the Goals',
    ];

    public const FUND_SOURCES = ['own' => 'Own budget', 'club' => 'Club Fund', 'qiu' => 'Request budget from QIU'];

    public function __construct(private readonly ProposalRules $rules) {}

    /** @return array<string, mixed> */
    public function defaults(Event $event): array
    {
        $cca = $this->rules->defaultCca($event->starts_at, $event->ends_at);

        return [
            'name' => $event->name,
            'summary' => $event->summary ?? '',
            'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $event->ends_at->format('Y-m-d\TH:i'),
            'venue' => $event->venue ?? '',
            'mode' => $event->mode,
            'is_outdoor' => $event->is_outdoor,
            'is_faculty_event' => $event->is_faculty_event,
            'expected_participants' => $event->expected_participants,
            'nature' => [], 'nature_other' => '',
            'cca_participant' => $cca, 'cca_committee' => $cca,
            'sdgs' => [], 'collaboration' => '', 'source_of_fund' => [],
            'introduction' => '', 'objectives' => ['', '', ''], 'eligibility' => '',
            'activities' => [['name' => '', 'description' => '']],
            'itinerary' => [['start' => '', 'end' => '', 'agenda' => 'Arrival & Registration']],
            'ict_requirements' => '',
            'transport_required' => false, 'transport_arrangement' => '',
            'departure_time' => '', 'departure_pickup' => '', 'return_time' => '', 'return_pickup' => '',
            'budget' => ['revenue' => [], 'expenses' => []],
            'conclusion' => '',
        ];
    }

    /** @return array<string, mixed> current draft, falling back to defaults for a new proposal */
    public function current(Document $document): array
    {
        $draft = $document->versions()->where('number', 0)->first()?->data;

        return array_replace($this->defaults($document->event), $draft ?? $document->data());
    }

    /**
     * Saves a draft version (number 0 is reused until the first submission) and syncs the event row.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDraft(Document $document, array $data, int $userId): void
    {
        $document->versions()->updateOrCreate(['number' => 0], ['data' => $data, 'created_by' => $userId]);
        $this->syncEvent($document->event, $data);
    }

    /** @param array<string, mixed> $data */
    public function syncEvent(Event $event, array $data): void
    {
        $event->update([
            'name' => $data['name'],
            'summary' => $data['summary'],
            'starts_at' => CarbonImmutable::parse($data['starts_at']),
            'ends_at' => CarbonImmutable::parse($data['ends_at']),
            'venue' => $data['venue'],
            'mode' => $data['mode'],
            'expected_participants' => $data['expected_participants'],
            'is_outdoor' => $data['is_outdoor'],
            'is_faculty_event' => $data['is_faculty_event'],
            'cca_participant' => $data['cca_participant'],
            'cca_committee' => $data['cca_committee'],
        ]);
    }

    /** Fields the template prints that come from records and cannot be edited. */
    public function lockedFields(Document $document): array
    {
        $coo = Staff::where('approver_role', ApproverRole::Coo)->where('active', true)->first();

        return [
            'to' => $coo ? "{$coo->name}, COO" : 'COO',
            'from' => $document->club->name,
            'submitted_on' => $document->submitted_at?->toFormattedDateString(),
        ];
    }
}
