<?php

namespace App\Services\Events;

use App\Enums\ApproverRole;
use App\Models\Document;
use App\Models\Event;
use App\Models\Staff;
use App\Services\Documents\Drafts;
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

    public function __construct(private readonly ProposalRules $rules, private readonly Drafts $drafts) {}

    /** @return array<string, mixed> */
    public function defaults(Event $event): array
    {
        $cca = min($this->rules->ccaLimit(), $this->rules->defaultCca($event->starts_at, $event->ends_at));

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
            'introduction' => '', 'objectives' => ['', ''], 'eligibility' => '',
            'speakers' => [],
            'activities' => [['name' => '', 'description' => '']],
            'itinerary' => [['start' => '', 'end' => '', 'agenda' => 'Arrival & Registration']],
            'ict_required' => false, 'ict_requirements' => '',
            'transport_required' => false,
            'trips' => [self::BLANK_TRIP],
            'budget' => ['revenue' => [], 'expenses' => []],
            'conclusion' => '',
            'extra_sections' => [],
            'late_reason' => '', 'deficit_reason' => '',
        ];
    }

    /** One leg of transport: out, drop-off, and back. An event with several places or times adds more. */
    public const BLANK_TRIP = [
        'arrangement' => '', 'departure_time' => '', 'departure_pickup' => '',
        'dropoff_time' => '', 'dropoff_point' => '', 'return_time' => '', 'return_pickup' => '',
    ];

    /** Kinds of block a club may add after the conclusion. */
    public const EXTRA_KINDS = ['heading', 'subheading', 'paragraph', 'bullets', 'table'];

    /** @return array<string, mixed> current draft, falling back to defaults for a new proposal */
    public function current(Document $document): array
    {
        return self::upgrade($this->drafts->current($document, $this->defaults($document->event)));
    }

    /**
     * Older proposals kept one trip in flat fields; they read as a single trip now.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function upgrade(array $data): array
    {
        if (array_key_exists('departure_time', $data) || array_key_exists('transport_arrangement', $data)) {
            $legacy = [
                'arrangement' => (string) ($data['transport_arrangement'] ?? ''),
                'departure_time' => (string) ($data['departure_time'] ?? ''), 'departure_pickup' => (string) ($data['departure_pickup'] ?? ''),
                'dropoff_time' => '', 'dropoff_point' => '',
                'return_time' => (string) ($data['return_time'] ?? ''), 'return_pickup' => (string) ($data['return_pickup'] ?? ''),
            ];
            $hasTrips = collect($data['trips'] ?? [])->contains(fn ($t) => collect((array) $t)->filter(fn ($v) => filled($v))->isNotEmpty());
            if (! $hasTrips) {
                $data['trips'] = [$legacy];
            }
            unset($data['transport_arrangement'], $data['departure_time'], $data['departure_pickup'], $data['return_time'], $data['return_pickup']);
        }
        $data['trips'] = array_values((array) ($data['trips'] ?? [])) ?: [self::BLANK_TRIP];
        $data['speakers'] = array_values((array) ($data['speakers'] ?? []));
        $data['extra_sections'] = array_values((array) ($data['extra_sections'] ?? []));

        return $data;
    }

    /** "Seminars/Conference/Workshop": speakers are listed, and two persons in charge replace the four fixed offices. @param array<string, mixed> $data */
    public static function isWorkshop(array $data): bool
    {
        return in_array('seminar', (array) ($data['nature'] ?? []), true);
    }

    /**
     * Saves a draft version (number 0 is reused until the first submission) and syncs the event row.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDraft(Document $document, array $data, int $userId): void
    {
        $this->drafts->save($document, $data, $userId);
        $this->syncEvent($document->event, $data);
    }

    /**
     * Keeps the event row in step with the draft. Half-typed values (an empty name, a date being typed) are skipped,
     * so the event keeps its last good value until the field is finished.
     *
     * @param  array<string, mixed>  $data
     */
    public function syncEvent(Event $event, array $data): void
    {
        $date = function (mixed $value): ?CarbonImmutable {
            try {
                return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
            } catch (\Throwable) {
                return null;
            }
        };
        $starts = $date($data['starts_at'] ?? null);
        $ends = $date($data['ends_at'] ?? null);
        $values = [
            'name' => filled($data['name'] ?? null) ? $data['name'] : null,
            'summary' => $data['summary'] ?? null,
            'starts_at' => $starts && $ends && $ends->gt($starts) ? $starts : null,
            'ends_at' => $starts && $ends && $ends->gt($starts) ? $ends : null,
            'venue' => $data['venue'] ?? null,
            'mode' => in_array($data['mode'] ?? null, ['physical', 'virtual'], true) ? $data['mode'] : null,
            'expected_participants' => is_numeric($data['expected_participants'] ?? null) && $data['expected_participants'] >= 1 ? (int) $data['expected_participants'] : null,
            'is_outdoor' => (bool) ($data['is_outdoor'] ?? false),
            'is_faculty_event' => (bool) ($data['is_faculty_event'] ?? false),
            'cca_participant' => is_numeric($data['cca_participant'] ?? null) ? $data['cca_participant'] : null,
            'cca_committee' => is_numeric($data['cca_committee'] ?? null) ? $data['cca_committee'] : null,
        ];
        $event->update(array_filter($values, fn ($v, $k) => $v !== null || in_array($k, ['summary', 'venue'], true), ARRAY_FILTER_USE_BOTH));
    }

    /** Fields the template prints that come from records and cannot be edited. */
    public function lockedFields(Document $document): array
    {
        $coo = Staff::where('approver_role', ApproverRole::Coo)->where('active', true)->first();

        return [
            'to' => $coo ? "{$coo->name}, COO" : 'COO',
            'from' => $document->club->name,
            'submitted_on' => $document->submitted_at?->format('j M Y'),
        ];
    }
}
