<?php

namespace App\Http\Requests\Events;

use App\Services\Events\ProposalForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'cca_participant' => ['required', 'numeric', 'min:0', 'max:100'],
            'cca_committee' => ['required', 'numeric', 'min:0', 'max:100'],
            'sdgs' => ['present', 'array'],
            'sdgs.*' => ['integer', Rule::in(array_keys(ProposalForm::SDGS))],
            'collaboration' => ['nullable', 'string', 'max:500'],
            'source_of_fund' => [$submitting ? 'required' : 'present', 'array'],
            'source_of_fund.*' => [Rule::in(array_keys(ProposalForm::FUND_SOURCES))],
            'introduction' => [$required, 'string', 'max:5000'],
            'objectives' => ['present', 'array', $submitting ? 'min:1' : 'min:0'],
            'objectives.*' => [$required, 'string', 'max:500'],
            'eligibility' => [$required, 'string', 'max:500'],
            'activities' => ['present', 'array'],
            'activities.*.name' => [$required, 'string', 'max:150'],
            'activities.*.description' => [$required, 'string', 'max:1000'],
            'itinerary' => ['present', 'array'],
            'itinerary.*.start' => [$required, 'date_format:H:i'],
            'itinerary.*.end' => [$required, 'date_format:H:i'],
            'itinerary.*.agenda' => [$required, 'string', 'max:255'],
            'ict_requirements' => ['nullable', 'string', 'max:2000'],
            'transport_required' => ['boolean'],
            'transport_arrangement' => ['nullable', 'string', 'max:500'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'departure_pickup' => ['nullable', 'string', 'max:255'],
            'return_time' => ['nullable', 'date_format:H:i'],
            'return_pickup' => ['nullable', 'string', 'max:255'],
            'budget' => ['required', 'array'],
            'budget.revenue' => ['present', 'array'],
            'budget.expenses' => ['present', 'array'],
            'budget.*.*.description' => ['required', 'string', 'max:255'],
            'budget.*.*.price_sen' => ['required', 'integer', 'min:0', 'max:100000000'],
            'budget.*.*.quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'conclusion' => [$required, 'string', 'max:5000'],
            'signature' => [$submitting ? 'required' : 'prohibited', 'string'],
            'signature_strokes' => [$submitting ? 'required' : 'prohibited', 'string', 'max:307200'],
        ];
    }

    /** @return array<string, mixed> */
    public function proposalData(): array
    {
        return collect($this->validated())->except(['signature', 'signature_strokes'])->all();
    }
}
