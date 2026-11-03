<?php

namespace App\Http\Requests\Events;

use App\Services\Events\ProposalForm;
use App\Services\Events\ProposalRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Every field of the 06 Event Proposal template. Drafts may be incomplete; submissions may not. */
class ProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('event'));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $submitting = $this->routeIs('proposals.submit');
        $required = $submitting ? 'required' : 'nullable';
        $cca = app(ProposalRules::class)->ccaLimit();
        $speakersNeeded = $submitting && ProposalForm::isWorkshop($this->all());

        return [
            'name' => ['required', 'string', 'max:150'],
            'summary' => [$required, 'string', 'max:1000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'venue' => [$required, 'string', 'max:255'],
            'mode' => ['required', Rule::in(['physical', 'virtual'])],
            'is_outdoor' => ['boolean'],
            'is_faculty_event' => ['boolean'],
            'expected_participants' => ['required', 'integer', 'min:1', 'max:5000'],
            'nature' => [$submitting ? 'required' : 'present', 'array'],
            'nature.*' => [Rule::in(array_keys(ProposalForm::NATURES))],
            'nature_other' => ['nullable', 'string', 'max:150', 'required_if:nature.*,others'],
            'cca_participant' => ['required', 'numeric', 'min:0', "max:{$cca}"],
            'cca_committee' => ['required', 'numeric', 'min:0', "max:{$cca}"],
            'sdgs' => ['present', 'array'],
            'sdgs.*' => ['integer', Rule::in(array_keys(ProposalForm::SDGS))],
            // Partners: clubs picked from the list, or any other organisation typed in.
            'collaboration' => ['nullable', 'array', 'max:20'],
            'collaboration.*' => ['string', 'max:150'],
            'source_of_fund' => [$submitting ? 'required' : 'present', 'array'],
            'source_of_fund.*' => [Rule::in(array_keys(ProposalForm::FUND_SOURCES))],
            'introduction' => [$required, 'string', 'max:5000'],
            'objectives' => ['present', 'array', $submitting ? 'min:2' : 'min:0'],
            'objectives.*' => ['nullable', 'string', 'max:500'],
            'eligibility' => [$required, 'string', 'max:500'],
            // Workshops: at least one speaker / external person, with photo, phone and designation (LinkedIn optional).
            'speakers' => [$speakersNeeded ? 'required' : 'nullable', 'array', $speakersNeeded ? 'min:1' : 'min:0', 'max:20'],
            'speakers.*.name' => [$required, 'string', 'max:150'],
            'speakers.*.phone' => [$required, 'string', 'max:30'],
            'speakers.*.designation' => [$required, 'string', 'max:150'],
            'speakers.*.linkedin' => ['nullable', 'string', 'max:255'],
            'speakers.*.photo' => [$required, 'string', 'regex:'.ProposalForm::IMAGE_PATH],
            'activities' => ['present', 'array'],
            'activities.*.name' => [$required, 'string', 'max:150'],
            'activities.*.description' => [$required, 'string', 'max:1000'],
            'itinerary' => ['present', 'array'],
            // Optional: list what you can; rows may be left incomplete.
            'itinerary.*.start' => ['nullable', 'date_format:H:i'],
            'itinerary.*.end' => ['nullable', 'date_format:H:i'],
            'itinerary.*.agenda' => ['nullable', 'string', 'max:255'],
            'ict_required' => ['boolean'],
            'ict_requirements' => ['nullable', 'required_if_accepted:ict_required', 'string', 'max:2000'],
            'transport_required' => ['boolean'],
            // One card per trip: departure, the places on the way (the last is the drop-off), and the return.
            'trips' => ['nullable', 'array', 'max:20'],
            'trips.*.arrangement' => [$required, 'string', 'max:500'],
            'trips.*.departure_at' => [$required, 'date'],
            'trips.*.departure_pickup' => [$required, 'string', 'max:255'],
            'trips.*.stops' => [$submitting ? 'required' : 'nullable', 'array', $submitting ? 'min:1' : 'min:0', 'max:15'],
            'trips.*.stops.*.name' => [$required, 'string', 'max:150'],
            'trips.*.stops.*.at' => [$required, 'date'],
            'trips.*.stops.*.point' => [$required, 'string', 'max:255'],
            'trips.*.return_at' => [$required, 'date'],
            'trips.*.return_pickup' => [$required, 'string', 'max:255'],
            'budget' => ['required', 'array'],
            'budget.revenue' => ['present', 'array'],
            'budget.expenses' => ['present', 'array'],
            'budget.*.*.description' => ['required', 'string', 'max:255'],
            'budget.*.*.price_sen' => ['required', 'integer', 'min:0', 'max:100000000'],
            'budget.*.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'conclusion' => [$required, 'string', 'max:5000'],
            // Extra parts after the conclusion: headings, paragraphs, bullet lists and tables the club adds itself.
            'extra_sections' => ['nullable', 'array', 'max:30'],
            'extra_sections.*.kind' => ['required', Rule::in(ProposalForm::EXTRA_KINDS)],
            'extra_sections.*.text' => ['nullable', 'string', 'max:5000'],
            'extra_sections.*.rows' => ['nullable', 'array', 'max:30'],
            'extra_sections.*.rows.*' => ['array', 'max:8'],
            'extra_sections.*.rows.*.*' => ['nullable', 'string', 'max:500'],
            'extra_sections.*.image' => ['nullable', 'string', 'regex:'.ProposalForm::IMAGE_PATH],
            // Exceptions SLD must approve: why it is submitted late, and why the budget does not break even.
            'late_reason' => ['nullable', 'string', 'max:1000'],
            'deficit_reason' => ['nullable', 'string', 'max:1000'],
            'signature' => [$submitting ? 'required' : 'prohibited', 'string'],
            'signature_strokes' => [$submitting ? 'required' : 'prohibited', 'string', 'max:307200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'objectives.min' => 'Write at least 2 objectives.',
            'speakers.*.photo.required' => 'Upload a photo of this speaker.',
            'trips.*.stops.required' => 'Add at least one destination (the last one is the drop-off point).',
            'trips.*.stops.min' => 'Add at least one destination (the last one is the drop-off point).',
            'cca_participant.max' => 'SLD allows at most :max CCA hours per event.',
            'cca_committee.max' => 'SLD allows at most :max CCA hours per event.',
            'budget.*.*.quantity.min' => 'Quantity must be at least 1.',
            'budget.*.*.quantity.integer' => 'Quantity must be a whole number.',
            'budget.*.*.price_sen.min' => 'Price cannot be negative.',
            'speakers.required' => 'A workshop lists at least one speaker or external person.',
            'speakers.min' => 'A workshop lists at least one speaker or external person.',
        ];
    }

    /**
     * Submitting: blank objective lines are unused rows (only written ones count), speakers belong to workshops only,
     * and trips only matter when transport is needed.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->routeIs('proposals.submit')) {
            return;
        }
        $merge = [];
        if (is_array($this->input('objectives'))) {
            $merge['objectives'] = array_values(array_filter($this->input('objectives'), fn ($o) => trim((string) $o) !== ''));
        }
        if (! ProposalForm::isWorkshop($this->all())) {
            $merge['speakers'] = [];
        }
        if (! $this->boolean('transport_required')) {
            $merge['trips'] = [];
        }
        $this->merge($merge);
    }

    /** Submitting: the event must at least break even (revenue covers expenses). @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->routeIs('proposals.submit')) {
                return;
            }
            $this->tripTimes($validator);
            // Every added part must be written (or deleted): no empty heading, paragraph, bullet list, table cell or picture.
            foreach ((array) $this->input('extra_sections', []) as $i => $block) {
                $kind = $block['kind'] ?? '';
                if ($kind === 'image') {
                    if (blank($block['image'] ?? null)) {
                        $validator->errors()->add("extra_sections.{$i}.image", 'Upload the picture, or delete this part.');
                    }
                } elseif ($kind === 'table') {
                    $cells = collect((array) ($block['rows'] ?? []))->flatten();
                    if ($cells->isEmpty() || $cells->contains(fn ($c) => trim((string) $c) === '')) {
                        $validator->errors()->add("extra_sections.{$i}.rows", 'Fill every cell of this table, or delete the table (or its empty rows and columns).');
                    }
                } elseif (trim((string) ($block['text'] ?? '')) === '') {
                    $validator->errors()->add("extra_sections.{$i}.text", 'This '.($kind === 'bullets' ? 'bullet list' : $kind).' is empty. Write it or delete it.');
                }
            }
            if ($validator->errors()->has('budget.*')) {
                return;
            }
            $totals = app(ProposalRules::class)->budgetTotals((array) $this->input('budget', []));
            if (! $totals['covers_expenses'] && trim((string) $this->input('deficit_reason')) === '') {
                $validator->errors()->add('deficit_reason', sprintf('Revenue (RM %s) does not cover expenses (RM %s). Break even, or explain the deficit; SLD decides whether to approve it.',
                    number_format($totals['revenue_sen'] / 100, 2), number_format($totals['expenses_sen'] / 100, 2)));
            }
        }];
    }

    /** Every trip time falls within the event, in order: departure, each destination, then the return. */
    private function tripTimes(Validator $validator): void
    {
        try {
            $start = CarbonImmutable::parse((string) $this->input('starts_at'));
            $end = CarbonImmutable::parse((string) $this->input('ends_at'));
        } catch (\Throwable) {
            return;
        }
        $window = $start->format('j M g:i A').' – '.$end->format('j M g:i A');
        foreach ((array) $this->input('trips', []) as $i => $trip) {
            $times = [["trips.{$i}.departure_at", $trip['departure_at'] ?? null]];
            foreach ((array) ($trip['stops'] ?? []) as $s => $stop) {
                $times[] = ["trips.{$i}.stops.{$s}.at", $stop['at'] ?? null];
            }
            $times[] = ["trips.{$i}.return_at", $trip['return_at'] ?? null];
            $previous = null;
            foreach ($times as [$field, $value]) {
                if (blank($value) || $validator->errors()->has($field)) {
                    continue;
                }
                $at = CarbonImmutable::parse((string) $value);
                if ($at->lt($start) || $at->gt($end)) {
                    $validator->errors()->add($field, "Must be within the event ({$window}).");
                } elseif ($previous && $at->lt($previous)) {
                    $validator->errors()->add($field, 'Must be after the time before it on this trip.');
                }
                $previous = $at;
            }
        }
    }

    /** @return array<string, mixed> */
    public function proposalData(): array
    {
        return collect($this->validated())->except(['signature', 'signature_strokes'])->all();
    }
}
