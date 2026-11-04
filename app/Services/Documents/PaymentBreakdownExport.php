<?php

namespace App\Services\Documents;

use App\Models\Club;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * A payments breakdown (one event, or a session's membership fees) as a plain spreadsheet, A4 landscape for PDF:
 * the summary, the fee groups, then every payment. Columns fit their content, so no value is cut off or split.
 */
class PaymentBreakdownExport
{
    private const COLUMNS = ['No', 'Name', 'Student ID', 'Email', 'Status', 'Date', 'Method', 'Order ID', 'Amount (RM)', 'Cash received by'];

    /** @param array<string, mixed> $breakdown from PaymentBreakdown */
    public function render(Club $club, array $breakdown): string
    {
        $book = new Spreadsheet;
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet = $book->getActiveSheet()->setTitle('Payments');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.5)->setBottom(0.5);

        foreach (["{$club->name} · {$breakdown['title']}", (string) $breakdown['subtitle'], 'Printed '.now()->format('j M Y, g:i A')] as $i => $line) {
            $sheet->setCellValue('A'.($i + 1), $line)->mergeCells('A'.($i + 1).':J'.($i + 1));
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A3')->getFont()->getColor()->setRGB('52525B');

        // Summary and fee groups sit in the Name / Student ID / Email columns, so column A stays narrow.
        $row = 5;
        foreach ($breakdown['tiles'] as $tile) {
            $sheet->setCellValue("B{$row}", $tile['label'])->setCellValue("C{$row}", isset($tile['sen']) ? $tile['sen'] / 100 : $tile['count']);
            if (isset($tile['sen'])) {
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $sheet->getStyle("B{$row}")->getFont()->setBold(true);
            $row++;
        }
        foreach ($breakdown['groups'] as $group) {
            if (! $group['items']) {
                continue;
            }
            $row++;
            $sheet->setCellValue("B{$row}", $group['title'])->getStyle("B{$row}")->getFont()->setBold(true);
            foreach ($group['items'] as $item) {
                $row++;
                $sheet->setCellValue("B{$row}", $item['label'])->setCellValue("C{$row}", $item['sen'] / 100)->setCellValue("D{$row}", "{$item['count']} paid");
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $row++;
        }

        $row += 1;
        $header = $row;
        $sheet->fromArray([self::COLUMNS], null, "A{$row}", true);
        $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D12A32']],
        ]);
        $widths = array_map(fn (string $h) => mb_strlen($h), self::COLUMNS);
        foreach ($breakdown['rows'] as $i => $r) {
            $row++;
            $line = [$i + 1, $r['name'], $r['student_number'], $r['email'], $r['status'], $r['date'], $r['method'] ? ucfirst($r['method']) : '', $r['order_id'], $r['amount_sen'] / 100, $r['collected_by']];
            $sheet->fromArray([$line], null, "A{$row}", true);
            foreach ($line as $c => $v) {
                $widths[$c] = max($widths[$c], mb_strlen((string) $v));
            }
        }
        $sheet->getStyle("I{$header}:I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A{$header}:J{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D4D4D8');
        $sheet->getStyle("A{$header}:A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($header, $header);

        // Summary labels share column B with the names; give it room for the longest of either.
        $labels = collect($breakdown['tiles'])->pluck('label')->merge(collect($breakdown['groups'])->flatMap(fn ($g) => [$g['title'], ...array_column($g['items'], 'label')]));
        $widths[1] = max($widths[1], (int) $labels->map(fn ($l) => mb_strlen((string) $l))->max());
        foreach (range('A', 'J') as $i => $col) {
            $sheet->getColumnDimension($col)->setWidth(min($widths[$i] + 3, 48));
        }

        $path = storage_path("app/private/generated/payments-{$club->id}-".now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.xlsx');
        @mkdir(dirname($path), 0775, true);
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return $path;
    }
}
