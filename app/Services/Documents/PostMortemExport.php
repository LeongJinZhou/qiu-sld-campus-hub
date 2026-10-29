<?php

namespace App\Services\Documents;

use App\Enums\ChainRole;
use App\Models\Document;
use App\Services\Events\PostMortemForm;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** 09 Event Post Mortem: header (B2–B8), A–G rows (B–D, rows 12–18), recommendations (row 21+), sign-off. */
class PostMortemExport
{
    public const TEMPLATE = '09. Event Post Mortem Template.xlsx';

    public function __construct(private readonly XlsxRenderer $xlsx, private readonly PostMortemForm $form) {}

    public function render(Document $document): string
    {
        $document->loadMissing(['event.club', 'approvals.signature']);
        $event = $document->event;
        $data = $document->data() ?: $this->form->current($document);
        $header = $this->form->header($event);
        $preparer = XlsxRenderer::signed($document, ChainRole::Preparer);
        $advisor = XlsxRenderer::signed($document, ChainRole::Advisor);
        $output = storage_path("app/private/generated/post-mortem-{$document->id}-".now()->format('YmdHis').'.xlsx');

        return $this->xlsx->render(self::TEMPLATE, function (Spreadsheet $book) use ($header, $data, $preparer, $advisor, $document): void {
            $sheet = $book->getActiveSheet();
            $sheet->setCellValue('B2', $header['event'])->setCellValue('B3', $header['club'])->setCellValue('B4', $header['date'])
                ->setCellValue('B5', $header['time'])->setCellValue('B6', $header['venue'])->setCellValue('B7', $header['participants'])
                ->setCellValue('B8', implode("\n", $header['committee']));
            $sheet->getStyle('B8')->getAlignment()->setWrapText(true);

            foreach (array_keys(PostMortemForm::AREAS) as $i => $key) {
                $row = $data['rows'][$key] ?? [];
                $r = 12 + $i;
                foreach (['B' => 'well', 'C' => 'challenges', 'D' => 'improvement'] as $col => $field) {
                    $sheet->setCellValue("{$col}{$r}", trim((string) ($row[$field] ?? '')) ?: 'N/A');
                }
            }

            $recommendations = array_values(array_filter($data['recommendations'] ?? [], fn ($r) => trim((string) $r) !== '')) ?: ['N/A'];
            $this->xlsx->fillRows($sheet, 21, 25, array_map(fn ($text, $i) => [$i + 1, $text], $recommendations, array_keys($recommendations)), gap: 1);

            $this->xlsx->signers($sheet, [
                new Signer($preparer?->signer_name ?? $document->creator->name, $preparer?->position_at_time ?? 'Committee', $preparer),
                new Signer($advisor?->signer_name, 'Club Advisor', $advisor),
            ]);
        }, $output);
    }
}
