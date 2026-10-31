<?php

namespace App\Services\Documents;

use App\Enums\ChainRole;
use App\Models\Document;
use App\Services\Agm\AgmReportForm;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

/** Renders the AGM Report into the official 01 template: Prepared (Secretary), Checked (President), Reviewed (Advisor). */
class AgmReportDocx
{
    public const TEMPLATE = '01. AGM Template.docx';

    private const SIGNERS = ['preparer' => 'Secretary', 'president' => 'President', 'advisor' => 'Advisor'];

    public function __construct(private readonly DocxRenderer $docx, private readonly AgmReportForm $form) {}

    public function render(Document $document): string
    {
        $document->loadMissing(['agm.club', 'approvals.signature']);
        $d = $document->data() ?: $this->form->current($document);
        // Submitted versions hold a snapshot of the computed sections; a draft shows today's records.
        $c = $d['computed'] ?? $this->form->computed($document->agm);
        $output = storage_path("app/private/generated/agm-{$document->id}-".now()->format('YmdHis').'.docx');

        return $this->docx->render(self::TEMPLATE, function (TemplateProcessor $t) use ($document, $d, $c): void {
            $t->setValues([
                'session' => $c['session'], 'next_session' => $c['next_session'], 'club' => $c['club'],
                'date' => $c['date'], 'time' => $c['time'], 'venue' => $c['venue'],
                'total_members' => (string) $c['total_members'], 'attended_members' => (string) $c['attended_members'],
                'absent_members' => (string) $c['absent_members'],
                'adjournment' => (string) ($d['adjournment'] ?? ''),
            ]);
            DocxRenderer::block($t, 'objectives', array_map(fn ($o) => ['objective' => (string) $o], array_values(array_filter($d['objectives'] ?? [], fn ($o) => trim((string) $o) !== ''))) ?: [['objective' => '']]);
            $speech = array_values(array_filter($d['speech'] ?? [], fn ($s) => trim((string) $s) !== ''));
            DocxRenderer::block($t, 'speech', array_map(fn ($s, $i) => ['speech_no' => '3.'.($i + 1), 'speech_point' => (string) $s], $speech, array_keys($speech)) ?: [['speech_no' => '', 'speech_point' => '']]);
            DocxRenderer::block($t, 'election', array_map(fn ($l) => ['election_line' => $l], $c['election']['election']));
            DocxRenderer::block($t, 'appointments', array_map(fn ($l) => ['appointment_line' => $l], $c['election']['appointments']));

            $t->cloneRowAndSetValues('att_no', array_map(fn ($r, $i) => [
                'att_no' => (string) ($i + 1), 'att_name' => $r['name'], 'att_position' => $r['position'], 'att_id' => $r['student_id'],
                'att_email' => $r['email'], 'att_year' => $r['year'], 'att_programme' => $r['programme'],
            ], $c['attendance'], array_keys($c['attendance'])) ?: [array_fill_keys(['att_no', 'att_name', 'att_position', 'att_id', 'att_email', 'att_year', 'att_programme'], '')]);
            $t->cloneRowAndSetValues('com_no', array_map(fn ($r, $i) => [
                'com_no' => (string) ($i + 1), 'com_name' => $r['name'], 'com_position' => $r['position'], 'com_id' => $r['student_id'],
                'com_email' => $r['email'], 'com_programme' => $r['programme'],
            ], $c['committee'], array_keys($c['committee'])));
            $agenda = array_values(array_filter($d['agenda'] ?? [], fn ($a) => trim((string) ($a['item'] ?? '')) !== ''));
            $t->cloneRowAndSetValues('agenda_start', array_map(fn ($a) => ['agenda_start' => (string) $a['start'], 'agenda_end' => (string) $a['end'], 'agenda_item' => (string) $a['item']], $agenda) ?: [['agenda_start' => '', 'agenda_end' => '', 'agenda_item' => '']]);

            $k = 0;
            foreach (self::SIGNERS as $role => $position) {
                $k++;
                $signed = XlsxRenderer::signed($document, ChainRole::from($role));
                $t->setValue("sig{$k}_name", $signed->signer_name ?? (string) ($c['signers'][$position] ?? ''));
                $t->setValue("sig{$k}_date", $signed ? $signed->signed_at->format('d/m/Y g:i A')." · Verify {$signed->verification_code}" : '');
                $image = $signed ? Storage::disk('local')->path($signed->image_path) : null;
                $image && is_file($image)
                    ? $t->setImageValue("sig{$k}_image", ['path' => $image, 'height' => 32, 'ratio' => true])
                    : $t->setValue("sig{$k}_image", '');
            }
        }, $output);
    }
}
