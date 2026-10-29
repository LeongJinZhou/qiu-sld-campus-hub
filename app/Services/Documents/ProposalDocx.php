<?php

namespace App\Services\Documents;

use App\Enums\ApprovalDecision;
use App\Enums\ChainRole;
use App\Enums\DocumentStatus;
use App\Models\Approval;
use App\Models\Document;
use App\Models\RoleAssignment;
use App\Models\Staff;
use App\Services\Events\ProposalForm;
use App\Services\Events\ProposalRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

/** Renders a proposal into the official 06 template (see resources/templates/maps/proposal.json). */
class ProposalDocx
{
    public const TEMPLATE = '06. Event Proposal Template 052026.docx';

    private const TICK = '/';

    /** Signature blocks printed on the template, in order. SLD Officer and Dean sign in the system only. */
    private const SIGNERS = [ChainRole::Preparer, ChainRole::Advisor, ChainRole::AssistantRegistrar, ChainRole::Registrar, ChainRole::Bursar, ChainRole::Coo];

    public function __construct(
        private readonly DocxRenderer $docx,
        private readonly ProposalForm $form,
        private readonly ProposalRules $rules,
    ) {}

    public function render(Document $document): string
    {
        $document->loadMissing(['club', 'event.committee.user', 'approvals.signature', 'approvals.approver.staff']);
        $data = $document->data() ?: $this->form->current($document);
        $output = storage_path("app/private/generated/proposal-{$document->id}-".now()->format('YmdHis').'.docx');

        return $this->docx->render(self::TEMPLATE, fn (TemplateProcessor $t) => $this->fill($t, $document, $data), $output);
    }

    /** @param array<string, mixed> $d */
    private function fill(TemplateProcessor $t, Document $document, array $d): void
    {
        $start = CarbonImmutable::parse($d['starts_at']);
        $end = CarbonImmutable::parse($d['ends_at']);
        $totals = $this->rules->budgetTotals($d['budget']);
        $locked = $this->form->lockedFields($document);
        $tick = fn (bool $on) => $on ? self::TICK : '';

        $values = [
            'to' => $locked['to'],
            'from' => $locked['from'],
            'submission_date' => $document->submitted_at?->format('d/m/Y') ?? '',
            'event_date' => $start->isSameDay($end) ? $start->format('d/m/Y') : $start->format('d/m/Y').' – '.$end->format('d/m/Y'),
            'event_time' => $start->format('g.i A').' – '.$end->format('g.i A'),
            'name' => $d['name'],
            'name_upper' => mb_strtoupper($d['name']),
            'summary' => $d['summary'],
            'nature_other' => in_array('others', $d['nature'], true) ? $d['nature_other'] : '',
            'cca_participant' => $this->number($d['cca_participant']),
            'cca_committee' => $this->number($d['cca_committee']),
            'duration' => $this->number($this->rules->defaultCca($start, $end)).' Hours',
            'venue' => $d['venue'],
            'mode_physical' => $tick($d['mode'] === 'physical'),
            'mode_virtual' => $tick($d['mode'] === 'virtual'),
            'collaboration' => $d['collaboration'] ?: '-',
            'total_budget' => 'RM '.$this->rm($totals['expenses_sen']),
            'tentative_date' => $start->format('d/m/Y').' – '.$end->format('d/m/Y'),
            'time_range' => $start->format('g.i A').' – '.$end->format('g.i A'),
            'introduction' => $d['introduction'],
            'expected_participants' => (string) $d['expected_participants'],
            'eligibility' => $d['eligibility'],
            'ict_requirements' => $d['ict_requirements'] ?: '-',
            'transport' => 'Transportation '.($d['transport_required'] ? 'required' : 'not required').($d['transport_arrangement'] ? '. '.$d['transport_arrangement'] : ''),
            'departure_time' => $d['departure_time'] ?: '-',
            'departure_pickup' => $d['departure_pickup'] ?: '-',
            'return_time' => $d['return_time'] ?: '-',
            'return_pickup' => $d['return_pickup'] ?: '-',
            'revenue_sum' => $this->rm($totals['revenue_sen']),
            'expense_sum' => $this->rm($totals['expenses_sen']),
            'profit_loss' => $this->rm($totals['profit_sen']),
            'conclusion' => $d['conclusion'],
            'cca_approved_committee' => $document->status === DocumentStatus::Approved ? $this->number($d['cca_committee']) : '',
            'cca_approved_helper' => '',
            'cca_approved_participant' => $document->status === DocumentStatus::Approved ? $this->number($d['cca_participant']) : '',
        ];
        foreach (array_keys(ProposalForm::NATURES) as $key) {
            $values["nature_{$key}"] = $tick(in_array($key, $d['nature'], true));
        }
        foreach (array_keys(ProposalForm::SDGS) as $n) {
            $values["sdg_{$n}"] = $tick(in_array($n, array_map('intval', $d['sdgs']), true));
        }
        foreach (array_keys(ProposalForm::FUND_SOURCES) as $key) {
            $values["fund_{$key}"] = $tick(in_array($key, $d['source_of_fund'], true));
        }
        $t->setValues($values);

        $t->cloneBlock('objectives', 0, true, false, array_map(fn (string $o) => ['objective' => $o], array_values(array_filter($d['objectives'])) ?: ['']));
        $this->rows($t, 'activity_name', array_map(fn ($a) => ['activity_name' => $a['name'], 'activity_description' => $a['description']], $d['activities']));
        $this->rows($t, 'itinerary_start', array_map(fn ($r) => ['itinerary_start' => $this->time($r['start']), 'itinerary_end' => $this->time($r['end']), 'itinerary_agenda' => $r['agenda']], $d['itinerary']));
        $this->rows($t, 'committee_name', $document->event->committee->map(fn (RoleAssignment $a) => [
            'committee_name' => $a->user->name, 'committee_role' => $a->position, 'committee_responsibility' => (string) $a->responsibility,
        ])->all());
        foreach (['revenue' => 'revenue', 'expense' => 'expenses'] as $prefix => $key) {
            $this->rows($t, "{$prefix}_no", array_map(fn ($item, $i) => [
                "{$prefix}_no" => (string) ($i + 1),
                "{$prefix}_description" => $item['description'],
                "{$prefix}_price" => $this->rm((int) $item['price_sen']),
                "{$prefix}_quantity" => (string) $item['quantity'],
                "{$prefix}_total" => $this->rm((int) $item['price_sen'] * (int) $item['quantity']),
            ], $d['budget'][$key], array_keys($d['budget'][$key])));
        }

        $this->signatures($t, $document);
    }

    private function signatures(TemplateProcessor $t, Document $document): void
    {
        $approvals = $document->approvals->keyBy(fn (Approval $a) => $a->role->value);
        foreach (self::SIGNERS as $i => $role) {
            $k = $i + 1;
            $approval = $approvals[$role->value] ?? null;
            $signed = $approval?->decision === ApprovalDecision::Approved ? $approval->signature : null;
            $staff = $role->approverRole() ? Staff::where('approver_role', $role->approverRole())->where('active', true)->first() : null;
            $signerStaff = $approval?->approver?->staff;

            $t->setValues([
                "sig{$k}_name" => $signed->signer_name ?? $staff->name ?? '',
                "sig{$k}_designation" => match ($role) {
                    ChainRole::Preparer => $signed->position_at_time ?? '',
                    ChainRole::Advisor => 'Club Advisor',
                    ChainRole::Coo => 'Chief Operating Officer',
                    default => $role->label(),
                },
                "sig{$k}_department" => match ($role) {
                    ChainRole::Preparer => $document->club->name,
                    ChainRole::Advisor => $signerStaff->department ?? $document->club->name,
                    default => (string) ($signerStaff->department ?? $staff?->department),
                },
                "sig{$k}_date" => $signed ? $signed->signed_at->format('d/m/Y g:i A')." · Verify {$signed->verification_code}" : '',
            ]);

            $image = $signed ? Storage::disk('local')->path($signed->image_path) : null;
            $image && is_file($image)
                ? $t->setImageValue("sig{$k}_image", ['path' => $image, 'height' => 40, 'ratio' => true])
                : $t->setValue("sig{$k}_image", '');
        }
    }

    /** @param list<array<string, string>> $rows */
    private function rows(TemplateProcessor $t, string $key, array $rows): void
    {
        $t->cloneRowAndSetValues($key, $rows ?: [array_fill_keys($this->rowKeys($t, $key), '')]);
    }

    /** @return list<string> */
    private function rowKeys(TemplateProcessor $t, string $key): array
    {
        $prefix = explode('_', $key)[0];

        return array_values(array_filter($t->getVariables(), fn (string $v) => str_starts_with($v, "{$prefix}_")));
    }

    private function rm(int $sen): string
    {
        return number_format($sen / 100, 2);
    }

    private function number(float|int|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1), '0'), '.');
    }

    private function time(string $hhmm): string
    {
        return $hhmm ? CarbonImmutable::createFromFormat('H:i', $hhmm)->format('g.ia') : '';
    }
}
