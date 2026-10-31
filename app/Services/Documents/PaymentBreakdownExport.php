<?php

namespace App\Services\Documents;

use App\Models\Club;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/** A payments breakdown (one event, or a session's membership fees) as a plain spreadsheet, landscape for PDF. */
class PaymentBreakdownExport
{
    private const COLUMNS = ['No', 'Name', 'Student ID', 'Email', 'Status', 'Date', 'Method', 'Order ID', 'Amount (RM)', 'Cash received by'];

    /** @param array<string, mixed> $breakdown from PaymentBreakdown */
    public function render(Club $club, array $breakdown): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Payments');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);

        $sheet->fromArray([["{$club->name} · {$breakdown['title']}"], [$breakdown['subtitle']], ['Printed '.now()->format('j M Y, g:i A')]], null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $row = 5;
        foreach ($breakdown['tiles'] as $tile) {
            $sheet->fromArray([$tile['label'], isset($tile['sen']) ? $tile['sen'] / 100 : $tile['count']], null, "A{$row}");
            if (isset($tile['sen'])) {
                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $row++;
        }
        foreach ($breakdown['groups'] as $group) {
            if (! $group['items']) {
                continue;
            }
            $row++;
            $sheet->setCellValue("A{$row}", $group['title'])->getStyle("A{$row}")->getFont()->setBold(true);
            foreach ($group['items'] as $item) {
                $row++;
                $sheet->fromArray([$item['label'], $item['sen'] / 100, "{$item['count']} paid"], null, "A{$row}");
                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $row++;
        }

        $row += 1;
        $header = $row;
        $sheet->fromArray(self::COLUMNS, null, "A{$row}");
        $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
        foreach ($breakdown['rows'] as $i => $r) {
            $row++;
            $sheet->fromArray([$i + 1, $r['name'], $r['student_number'], $r['email'], $r['status'], $r['date'], $r['method'] ? ucfirst($r['method']) : '', $r['order_id'], $r['amount_sen'] / 100, $r['collected_by']], null, "A{$row}");
        }
        $sheet->getStyle("I{$header}:I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $path = storage_path("app/private/generated/payments-{$club->id}-".now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.xlsx');
        @mkdir(dirname($path), 0775, true);
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return $path;
    }
}
