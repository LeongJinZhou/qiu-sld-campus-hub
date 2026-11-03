<?php

namespace App\Http\Requests\Events;

use App\Services\Events\ProposalForm;
use App\Services\Events\ProposalRules;
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
            'collaboration' => ['nullable', 'string', 'max:500'],
            'source_of_fund' => [$submitting ? 'required' : 'present', 'array'],
            'source_of_fund.*' => [Rule::in(array_keys(ProposalForm::FUND_SOURCES))],
            'introduction' => [$required, 'string', 'max:5000'],
            'objectives' => ['present', 'array', $submitting ? 'min:2' : 'min:0'],
            'objectives.*' => [$required, 'string', 'max:500'],
            'eligibility' => [$required, 'string', 'max:500'],
            // Workshops: at least one speaker / external person, with phone and designation (LinkedIn optional).
            'speakers' => [$speakersNeeded ? 'required' : 'nullable', 'array', $speakersNeeded ? 'min:1' : 'min:0', 'max:20'],
            'speakers.*.name' => [$required, 'string', 'max:150'],
            'speakers.*.phone' => [$required, 'string', 'max:30'],
            'speakers.*.designation' => [$required, 'string', 'max:150'],
            'speakers.*.linkedin' => ['nullable', 'string', 'max:255'],
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
            // One row per trip (different places or times): out, drop-off, back.
            'trips' => ['nullable', 'array', 'max:20'],
            'trips.*.arrangement' => ['nullable', $submitting ? 'required_if_accepted:transport_required' : 'nullable', 'string', 'max:500'],
            'trips.*.departure_time' => ['nullable', 'date_format:H:i'],
            'trips.*.departure_pickup' => ['nullable', 'string', 'max:255'],
            'trips.*.dropoff_time' => ['nullable', 'date_format:H:i'],
            'trips.*.dropoff_point' => ['nullable', 'string', 'max:255'],
            'trips.*.return_time' => ['nullable', 'date_format:H:i'],
            'trips.*.return_pickup' => ['nullable', 'string', 'max:255'],
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
            // Every added part must be written (or deleted): no empty heading, paragraph, bullet list or table cell.
            foreach ((array) $this->input('extra_sections', []) as $i => $block) {
                $kind = $block['kind'] ?? '';
                if ($kind === 'table') {
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

    /** @return array<string, mixed> */
    public function proposalData(): array
    {
        return collect($this->validated())->except(['signature', 'signature_strokes'])->all();
    }
}
