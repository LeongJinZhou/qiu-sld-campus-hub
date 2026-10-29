<?php

namespace App\Services\Documents;

use App\Enums\ApprovalDecision;
use App\Enums\ChainRole;
use App\Models\Approval;
use App\Models\Document;
use App\Services\Meetings\MinutesForm;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

/** Renders minutes into the official 13 template: Prepared by, President's and Advisor's signatures. */
class MinutesDocx
{
    public const TEMPLATE = '13. Meeting Minutes Template.docx';

    private const SIGNERS = [ChainRole::Preparer, ChainRole::President, ChainRole::Advisor];

    public function __construct(private readonly DocxRenderer $docx, private readonly MinutesForm $form) {}

    public function render(Document $document): string
    {
        $document->loadMissing(['club', 'meeting', 'approvals.signature']);
        $d = $document->data() ?: $this->form->current($document);
        $meeting = $document->meeting;
        $output = storage_path("app/private/generated/minutes-{$document->id}-".now()->format('YmdHis').'.docx');

        return $this->docx->render(self::TEMPLATE, function (TemplateProcessor $t) use ($document, $d, $meeting): void {
            $logo = $document->club->logo_path ? Storage::disk('public')->path($document->club->logo_path) : null;
            // Raster logos only; SVG cannot be embedded, so the club name stands in.
            $logo && is_file($logo) && in_array(strtolower(pathinfo($logo, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg'], true)
                ? $t->setImageValue('club_logo', ['path' => $logo, 'height' => 60, 'ratio' => true])
                : $t->setValue('club_logo', '');
            $t->setValues([
                'club' => $document->club->name,
                'date' => $meeting->starts_at->format('j F Y'),
                'venue' => $meeting->meet_link ? 'Google Meet' : (string) $meeting->venue,
                'time' => $meeting->starts_at->format('g:i A').' – '.$meeting->ends_at->format('g:i A'),
                'purpose' => (string) ($d['purpose'] ?? ''),
                'next_date' => $d['next_date'] ? date('j F Y', (int) strtotime($d['next_date'])) : 'N/A',
                'next_time' => $d['next_time'] ?: 'N/A',
                'next_venue' => $d['next_venue'] ?: 'N/A',
            ]);
            DocxRenderer::block($t, 'summary', array_map(fn ($p) => ['summary_point' => (string) $p], array_values(array_filter($d['summary'] ?? [])) ?: ['N/A']));
            $actions = array_map(fn ($a) => ['action_task' => $a['task'], 'action_pic' => $a['pic'], 'action_deadline' => $a['deadline'] ?: 'N/A'], $d['actions'] ?? []);
            $t->cloneRowAndSetValues('action_task', $actions ?: [['action_task' => 'N/A', 'action_pic' => 'N/A', 'action_deadline' => 'N/A']]);

            $approvals = $document->approvals->keyBy(fn (Approval $a) => $a->role->value);
            foreach (self::SIGNERS as $i => $role) {
                $k = $i + 1;
                $approval = $approvals[$role->value] ?? null;
                $signed = $approval?->decision === ApprovalDecision::Approved ? $approval->signature : null;
                $t->setValue("sig{$k}_name", $signed->signer_name ?? '');
                if ($k > 1) {
                    $t->setValue("sig{$k}_date", $signed ? $signed->signed_at->format('d/m/Y g:i A')." · Verify {$signed->verification_code}" : '');
                }
                $image = $signed ? Storage::disk('local')->path($signed->image_path) : null;
                $image && is_file($image)
                    ? $t->setImageValue("sig{$k}_image", ['path' => $image, 'height' => 32, 'ratio' => true])
                    : $t->setValue("sig{$k}_image", '');
            }
        }, $output);
    }
}
