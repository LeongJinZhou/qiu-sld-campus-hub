<?php

namespace App\Services\Documents;

use App\Enums\ScopedRole;
use App\Models\Club;
use App\Services\Finance\Ledger;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** 02 Finance Record: Date, Description, Income, Expenses, running Balance; Prepared by Treasurer, Reviewed by Advisor. */
class FinanceRecordExport
{
    public const TEMPLATE = '02. Finance Record.xlsx';

    public function __construct(private readonly XlsxRenderer $xlsx, private readonly Ledger $ledger, private readonly SessionResolver $sessions) {}

    /** @param list<array<string, mixed>>|null $rows rows to print (default: the whole ledger) */
    public function render(Club $club, ?array $rows = null): string
    {
        $rows ??= $this->ledger->rows($club);
        $session = $this->sessions->current();
        $treasurer = $club->roleAssignments()->where('session_id', $session->id)->where('position', 'Treasurer')->with('user')->first()?->user;
        $advisor = $club->roleAssignments()->where('session_id', $session->id)->where('role', ScopedRole::Advisor)->with('user')->first()?->user;
        $output = storage_path("app/private/generated/finance-{$club->id}-".now()->format('YmdHis').'.xlsx');

        return $this->xlsx->render(self::TEMPLATE, function (Spreadsheet $book) use ($rows, $treasurer, $advisor): void {
            $sheet = $book->getActiveSheet();
            // One page wide, as many pages tall as the ledger needs (the template's "fit to 1 page" made long ledgers unreadable).
            $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
            $sheet->setCellValue('A3', null); // the template's "Sample" marker
            // Blank the template's sample lines (rows 4-7) before writing the real ledger from row 4.
            foreach (range(4, 7) as $r) {
                foreach (['A', 'B', 'C', 'D'] as $col) {
                    $sheet->setCellValue("{$col}{$r}", null);
                }
            }
            $first = 4;
            $footer = 36;
            $capacity = $footer - 1 - $first; // keep the blank row above "Prepared by:"
            if (count($rows) > $capacity) {
                $sheet->insertNewRowBefore($footer - 1, count($rows) - $capacity);
            }
            foreach ($rows as $i => $row) {
                $r = $first + $i;
                $sheet->setCellValue("A{$r}", ExcelDate::PHPToExcel(CarbonImmutable::parse($row['date'])));
                $sheet->getStyle("A{$r}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                $sheet->setCellValue("B{$r}", $row['description']);
                $sheet->setCellValue("C{$r}", $row['income_sen'] / 100);
                $sheet->setCellValue("D{$r}", $row['expense_sen'] / 100);
                // Opening line carries the balance; later lines keep the template's running-balance formula.
                $sheet->setCellValue("E{$r}", $i === 0 ? $row['balance_sen'] / 100 : '=E'.($r - 1)."+C{$r}-D{$r}");
            }

            $names = [];
            for ($r = 1; $r <= $sheet->getHighestDataRow('A'); $r++) {
                if (trim((string) $sheet->getCell("A{$r}")->getValue()) === 'Name:') {
                    $names[] = $r;
                }
            }
            if (isset($names[0])) {
                $sheet->setCellValue("B{$names[0]}", $treasurer?->name)->setCellValue('B'.($names[0] + 1), 'Treasurer');
            }
            if (isset($names[1])) {
                $sheet->setCellValue("B{$names[1]}", $advisor?->name);
            }
        }, $output);
    }
}
