<?php

namespace App\Services\Documents;

use App\Enums\ApprovalDecision;
use App\Enums\ChainRole;
use App\Enums\DocumentStatus;
use App\Enums\ScopedRole;
use App\Models\Approval;
use App\Models\Document;
use App\Models\RoleAssignment;
use App\Models\Staff;
use App\Models\User;
use App\Services\Events\ProposalForm;
use App\Services\Events\ProposalRules;
use App\Services\Roles\CompulsoryPositionsCheck;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\ComplexType\TblWidth;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Shared\XMLWriter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\TemplateProcessor;

/** Renders a proposal into the official 06 template (see resources/templates/maps/proposal.json). */
class ProposalDocx
{
    public const TEMPLATE = '06. Event Proposal Template 052026.docx';

    private const TICK = '/';

    /** An empty paragraph: keeps tables apart (Word joins tables that touch) and gives room under one. */
    private const SPACER = '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr></w:p>';

    /** Signature blocks printed on the template, in order. SLD Officer and Dean sign in the system only. */
    private const SIGNERS = [ChainRole::Preparer, ChainRole::Advisor, ChainRole::AssistantRegistrar, ChainRole::Registrar, ChainRole::Bursar, ChainRole::Coo];

    /** Pictures waiting to replace their ${placeholder} once the parts holding them are in the document. @var array<string, array<string, mixed>> */
    private array $images = [];

    public function __construct(
        private readonly DocxRenderer $docx,
        private readonly ProposalForm $form,
        private readonly ProposalRules $rules,
    ) {}

    public function render(Document $document): string
    {
        $document->loadMissing(['club', 'event.committee.user.student', 'event.committee.user.staff', 'approvals.signature', 'approvals.approver.staff']);
        $data = ProposalForm::upgrade($document->data() ?: $this->form->current($document));
        $this->images = [];
        $output = storage_path("app/private/generated/proposal-{$document->id}-".now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.docx');

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
            'collaboration' => implode(', ', array_filter(array_map('trim', (array) $d['collaboration']))) ?: '-',
            'total_budget' => 'RM '.$this->rm($totals['expenses_sen']),
            'tentative_date' => $start->format('d/m/Y').' – '.$end->format('d/m/Y'),
            'time_range' => $start->format('g.i A').' – '.$end->format('g.i A'),
            'introduction' => $d['introduction'],
            'expected_participants' => (string) $d['expected_participants'],
            'eligibility' => $d['eligibility'],
            // Not ticked (or nothing written) = "Not required" on the form.
            'ict_requirements' => ! empty($d['ict_required']) && filled($d['ict_requirements'] ?? null) ? $d['ict_requirements'] : 'Not required',
            'transport' => $d['transport_required'] ? 'Transportation required' : 'Not required',
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

        DocxRenderer::block($t, 'objectives', array_map(fn (string $o) => ['objective' => $o], array_values(array_filter($d['objectives'])) ?: ['']));
        $this->rows($t, 'activity_name', array_map(fn ($a) => ['activity_name' => $a['name'], 'activity_description' => $a['description']], $d['activities']));
        // The itinerary is optional: rows left half-done print what was given.
        $itinerary = array_values(array_filter($d['itinerary'] ?? [], fn ($r) => filled($r['start'] ?? null) || filled($r['end'] ?? null) || filled($r['agenda'] ?? null)));
        $this->rows($t, 'itinerary_start', array_map(fn ($r) => ['itinerary_start' => filled($r['start'] ?? null) ? $this->time($r['start']) : '', 'itinerary_end' => filled($r['end'] ?? null) ? $this->time($r['end']) : '', 'itinerary_agenda' => (string) ($r['agenda'] ?? '')], $itinerary));
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

        $this->speakers($t, $d);
        $this->trips($t, $d);
        $this->chart($t, $document, $d);
        $this->extras($t, $d['extra_sections'] ?? []);
        foreach ($this->images as $key => $image) {
            $t->setImageValue($key, $image);
        }
        $this->signatures($t, $document);
    }

    /** @param array<string, mixed> $d */
    private function speakers(TemplateProcessor $t, array $d): void
    {
        $speakers = ProposalForm::isWorkshop($d) ? array_values($d['speakers'] ?? []) : [];
        $this->optional($t, 'speakers', $speakers ? ['speakers_table' => $this->grid(
            ['No', 'Photo', 'Name', 'Designation', 'Phone', 'LinkedIn'],
            array_map(fn (array $p, int $i) => [
                (string) ($i + 1), $this->picture("speaker_photo_{$i}", $p['photo'] ?? null, 2.2, 2.8), $p['name'] ?? '', $p['designation'] ?? '', $p['phone'] ?? '', ($p['linkedin'] ?? '') ?: '-',
            ], $speakers, array_keys($speakers)),
            [550, 1500, 1950, 1950, 1400, 1550],
        )] : []);
    }

    /** @param array<string, mixed> $d */
    private function trips(TemplateProcessor $t, array $d): void
    {
        $trips = $d['transport_required'] ? array_values($d['trips'] ?? []) : [];
        if (! $trips) {
            $t->setValue('trips_table', '');

            return;
        }
        $at = fn (?string $when, ?string $place) => (filled($when) ? $this->when((string) $when) : '-')."\n".(filled($place) ? $place : '-');
        $t->setComplexBlock('trips_table', $this->grid(
            ['No', 'Arrangement', 'Departure / Pickup Point', 'Destinations (last = Drop-off Point)', 'Est. Return / Pickup Point'],
            array_map(function (array $trip, int $i) use ($at): array {
                $stops = array_values((array) ($trip['stops'] ?? []));
                $route = array_map(fn (array $stop, int $s) => ($s === count($stops) - 1 ? 'Drop-off: ' : ($s + 1).'. ').(($stop['name'] ?? '') ?: '-')
                    ."\n".(filled($stop['at'] ?? null) ? $this->when($stop['at']) : '-').(filled($stop['point'] ?? null) ? " · {$stop['point']}" : ''), $stops, array_keys($stops));

                return [
                    (string) ($i + 1), ($trip['arrangement'] ?? '') ?: '-',
                    $at($trip['departure_at'] ?? null, $trip['departure_pickup'] ?? null),
                    implode("\n", $route) ?: '-',
                    $at($trip['return_at'] ?? null, $trip['return_pickup'] ?? null),
                ];
            }, $trips, array_keys($trips)),
            [550, 1700, 1950, 2800, 1900],
        ));
    }

    /**
     * Who leads the event, top-down: the club advisor, President, Vice President, Secretary and Treasurer, then every
     * other role (a workshop's persons in charge). Each box: designation, name, student or employee ID from records.
     *
     * @param  array<string, mixed>  $d
     */
    private function chart(TemplateProcessor $t, Document $document, array $d): void
    {
        $person = fn (?User $user, string $designation) => [
            'designation' => $designation,
            'name' => $user->name ?? '',
            'id' => $user?->student ? "Student ID: {$user->student->student_number}" : ($user?->staff?->staff_number ? "Employee ID: {$user->staff->staff_number}" : ''),
        ];
        $advisors = RoleAssignment::with('user.staff', 'user.student')->actingFor($document->club_id, $document->session)
            ->where('role', ScopedRole::Advisor)->get()->map(fn (RoleAssignment $a) => $person($a->user, 'Club Advisor'))->all();
        $committee = $document->event->committee;
        $holding = fn (array $positions) => $committee->whereIn('position', $positions)->sortBy(fn (RoleAssignment $a) => array_search($a->position, $positions, true))
            ->map(fn (RoleAssignment $a) => $person($a->user, $a->position))->values()->all();
        $others = $committee->whereNotIn('position', CompulsoryPositionsCheck::POSITIONS)
            ->map(fn (RoleAssignment $a) => $person($a->user, ProposalForm::isWorkshop($d) && blank($a->position) ? 'Person in Charge' : $a->position))->values()->all();
        $tiers = array_values(array_filter([$advisors, $holding(['President']), $holding(['Vice President']), $holding(['Secretary', 'Treasurer']), $others]));
        if (count($tiers) <= ($advisors ? 1 : 0)) {
            $this->optional($t, 'chart', []);

            return;
        }

        $items = [];
        foreach ($tiers as $i => $tier) {
            foreach (array_chunk($tier, 4) as $boxes) {
                $items[] = $this->boxes($boxes, $i === 0 && $advisors ? 'FFF4CC' : ($i <= ($advisors ? 1 : 0) ? 'DDEBF7' : 'F2F2F2'));
            }
            if ($i < count($tiers) - 1) {
                $arrow = new TextRun(['alignment' => Jc::CENTER, 'spaceBefore' => 0, 'spaceAfter' => 0]);
                $arrow->addText('▼', ['name' => 'Arial', 'size' => 10, 'color' => '7F7F7F']);
                $items[] = $arrow;
            }
        }
        $this->optional($t, 'chart', $items, 'chart_item');
    }

    /** @param list<array{designation: string, name: string, id: string}> $people one row of boxes, centred */
    private function boxes(array $people, string $fill): Table
    {
        $table = new Table(['alignment' => JcTable::CENTER, 'cellSpacing' => 80, 'borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 80]);
        $table->addRow();
        foreach ($people as $p) {
            $cell = $table->addCell(2200, ['bgColor' => $fill, 'borderSize' => 6, 'borderColor' => '404040', 'valign' => 'center']);
            $centre = ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'spaceBefore' => 0];
            $cell->addText($p['designation'], ['name' => 'Arial', 'size' => 9, 'bold' => true], $centre);
            $cell->addText($p['name'], ['name' => 'Arial', 'size' => 9], $centre);
            if ($p['id'] !== '') {
                $cell->addText($p['id'], ['name' => 'Arial', 'size' => 8, 'color' => '595959'], $centre);
            }
        }

        return $table;
    }

    /**
     * The club's own parts after the conclusion, laid out like the template body (Arial, 567 twip indent).
     *
     * @param  list<array{kind?: string, text?: string, rows?: list<list<string>>}>  $blocks
     */
    private function extras(TemplateProcessor $t, array $blocks): void
    {
        $items = [];
        foreach ($blocks as $b => $block) {
            $text = trim((string) ($block['text'] ?? ''));
            $kind = $block['kind'] ?? '';
            if ($kind === 'image') {
                // Centred, sized to read clearly on A4 without filling the page; the caption (if any) underneath.
                $placeholder = $this->picture("extra_image_{$b}", $block['image'] ?? null, 12, 9);
                if ($placeholder !== '') {
                    $items[] = $this->paragraph($placeholder, [], ['alignment' => Jc::CENTER, 'indentation' => ['left' => 567], 'spaceBefore' => 120, 'spaceAfter' => $text === '' ? 160 : 40, 'keepNext' => $text !== '']);
                    if ($text !== '') {
                        $items[] = $this->paragraph($text, ['italic' => true, 'size' => 10, 'color' => '404040'], ['alignment' => Jc::CENTER, 'indentation' => ['left' => 567], 'spaceAfter' => 160]);
                    }
                }

                continue;
            }
            $item = match (true) {
                $kind === 'heading' && $text !== '' => $this->paragraph($text, ['bold' => true, 'size' => 12], ['spaceBefore' => 240, 'spaceAfter' => 120, 'keepNext' => true]),
                $kind === 'subheading' && $text !== '' => $this->paragraph($text, ['bold' => true], ['spaceBefore' => 120, 'spaceAfter' => 80, 'keepNext' => true, 'indentation' => ['left' => 567]]),
                $kind === 'paragraph' && $text !== '' => $this->paragraph($text, [], ['spaceAfter' => 120, 'lineHeight' => 1.5, 'alignment' => Jc::BOTH, 'indentation' => ['left' => 567]]),
                $kind === 'bullets' && $text !== '' => $this->bullets(array_values(array_filter(array_map('trim', explode("\n", $text))))),
                $kind === 'table' && collect($block['rows'] ?? [])->flatten()->filter(fn ($c) => trim((string) $c) !== '')->isNotEmpty() => $this->userTable($block['rows']),
                default => null,
            };
            if ($item) {
                $items[] = $item;
            }
        }
        $this->optional($t, 'extra', $items, 'extra_item');
    }

    /**
     * A picture from the public disk, fitted inside a box (cm) keeping its shape: returns the ${placeholder} to print
     * where it goes (set once the part is in the document), or '' when there is no usable picture.
     */
    private function picture(string $key, ?string $path, float $maxWidthCm, float $maxHeightCm): string
    {
        $file = is_string($path) && preg_match(ProposalForm::IMAGE_PATH, $path) ? Storage::disk('public')->path($path) : null;
        $size = $file && is_file($file) ? @getimagesize($file) : false;
        if (! $size || ! $size[0] || ! $size[1]) {
            return '';
        }
        $points = 28.35;
        $scale = min($maxWidthCm * $points / $size[0], $maxHeightCm * $points / $size[1]);
        $this->images[$key] = ['path' => $file, 'width' => round($size[0] * $scale, 1).'pt', 'height' => round($size[1] * $scale, 1).'pt', 'ratio' => false];

        return '${'.$key.'}';
    }

    /** @param array<string, mixed> $font @param array<string, mixed> $paragraph */
    private function paragraph(string $text, array $font, array $paragraph): TextRun
    {
        $run = new TextRun($paragraph);
        foreach (explode("\n", $text) as $i => $line) {
            if ($i > 0) {
                $run->addTextBreak();
            }
            $run->addText($line, ['name' => 'Arial', 'size' => 11, ...$font]);
        }

        return $run;
    }

    /** Bullets as a borderless two-column table, so wrapped lines stay aligned with the text. @param list<string> $lines */
    private function bullets(array $lines): Table
    {
        $table = new Table(['borderSize' => 0, 'borderColor' => 'FFFFFF', 'indent' => new TblWidth(567), 'cellMargin' => 20]);
        foreach ($lines as $line) {
            $table->addRow();
            $table->addCell(360)->addText('•', ['name' => 'Arial', 'size' => 11], ['spaceAfter' => 40]);
            $table->addCell(8200)->addText($line, ['name' => 'Arial', 'size' => 11], ['spaceAfter' => 40, 'alignment' => Jc::BOTH]);
        }

        return $table;
    }

    /** A table the club built: first row is the header; columns share the width evenly. @param list<list<string>> $rows */
    private function userTable(array $rows): Table
    {
        $columns = max(array_map('count', $rows)) ?: 1;

        return $this->grid(array_pad($rows[0], $columns, ''), array_map(fn (array $r) => array_pad($r, $columns, ''), array_slice($rows, 1)), array_fill(0, $columns, intdiv(8900, $columns)), numbered: false);
    }

    /**
     * A bordered table in the template's style: shaded bold header, Arial 10, indented like the template's own tables.
     *
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     * @param  list<int>  $widths  twips per column
     */
    private function grid(array $header, array $rows, array $widths, bool $numbered = true): Table
    {
        $table = new Table(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80, 'indent' => new TblWidth(567)]);
        foreach ([$header, ...$rows] as $r => $row) {
            $table->addRow();
            foreach ($row as $c => $value) {
                $cell = $table->addCell($widths[$c] ?? 1500, $r === 0 ? ['bgColor' => 'D9D9D9', 'valign' => 'center'] : ['valign' => 'top']);
                foreach (explode("\n", (string) $value) as $line) {
                    $cell->addText($line, ['name' => 'Arial', 'size' => 10, 'bold' => $r === 0], ['spaceAfter' => 0, 'alignment' => $r === 0 || ($numbered && $c === 0) || str_starts_with($line, '${') ? Jc::CENTER : Jc::START]);
                }
            }
        }

        return $table;
    }

    /**
     * An optional part printed only when it has items; otherwise removed with its heading. Items are keyed by their
     * placeholder, or (with $itemKey) a list that replaces that one placeholder paragraph in order.
     *
     * @param  array<string, AbstractElement>|list<AbstractElement>  $items
     */
    private function optional(TemplateProcessor $t, string $block, array $items, ?string $itemKey = null): void
    {
        $t->cloneBlock($block, $items === [] ? 0 : 1, true, false);
        if ($items === []) {
            return;
        }
        if ($itemKey === null) {
            // A table directly above the next heading gets a little room.
            foreach ($items as $key => $element) {
                $t->replaceXmlBlock((string) $key, $this->xml($element).($element instanceof Table ? self::SPACER : ''));
            }

            return;
        }
        // Word joins tables that touch, so a thin empty paragraph keeps neighbouring tables apart.
        $xml = '';
        $previous = null;
        foreach ($items as $element) {
            $xml .= ($previous instanceof Table && $element instanceof Table ? self::SPACER : '').$this->xml($element);
            $previous = $element;
        }
        $t->replaceXmlBlock($itemKey, $xml);
    }

    /** WordprocessingML for one element, as setComplexBlock() writes it. */
    private function xml(AbstractElement $element): string
    {
        $writer = 'PhpOffice\\PhpWord\\Writer\\Word2007\\Element\\'.class_basename($element);
        $xml = new XMLWriter;
        (new $writer($xml, $element, false))->write();

        return $xml->getData();
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
                "sig{$k}_date" => $signed ? $signed->signed_at->format('d/m/Y g:i A') : '',
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

    private function when(string $dateTime): string
    {
        return CarbonImmutable::parse($dateTime)->format('j M Y, g.ia');
    }

    private function time(string $hhmm): string
    {
        return $hhmm ? CarbonImmutable::createFromFormat('H:i', $hhmm)->format('g.ia') : '';
    }
}
