<?php

namespace App\Services\Documents;

use App\Models\Club;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The club finance workbook: an Overview sheet first (every chosen section with its totals), then one sheet per
 * section: membership drive, normal registration, then each event in date order, then other transactions.
 * The PDF is the same workbook, one sheet after another.
 */
class FinanceWorkbookExport
{
    private const COLUMNS = ['No', 'Date', 'Transaction ID', 'Description', 'Name', 'Student ID', 'Method', 'Handled by', 'Income (RM)', 'Expense (RM)', 'Balance (RM)'];

    private const MONEY = '#,##0.00';

    /**
     * @param  array{period: string, brought_forward_sen: int, closing_sen: int, sections: list<array{key: string, title: string, rows: list<array<string, mixed>>}>}  $data
     * @param  list<string>|null  $keys  sections to include (null: all with transactions)
     */
    public function render(Club $club, array $data, ?array $keys = null): string
    {
        $sections = array_values(array_filter($data['sections'], fn (array $s) => $keys === null ? $s['rows'] !== [] : in_array($s['key'], $keys, true)));
        $book = new Spreadsheet;
        $this->overview($book->getActiveSheet(), $club, $data, $sections);
        foreach ($sections as $section) {
            $this->section($book->createSheet(), $club, $data['period'], $section);
        }
        $book->setActiveSheetIndex(0);

        $path = storage_path("app/private/generated/finance-book-{$club->id}-".now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.xlsx');
        @mkdir(dirname($path), 0775, true);
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return $path;
    }

    /** @param list<array{key: string, title: string, rows: list<array<string, mixed>>}> $sections */
    private function overview(Worksheet $sheet, Club $club, array $data, array $sections): void
    {
        $sheet->setTitle('Overview');
        $this->page($sheet);
        $this->heading($sheet, "{$club->name} · Finance overview", $data['period']);

        $sheet->fromArray([['Balance brought forward', $data['brought_forward_sen'] / 100]], null, 'A5');
        $row = 7;
        $sheet->fromArray([['Section', 'Transactions', 'Income (RM)', 'Expense (RM)', 'Net (RM)']], null, "A{$row}");
        $this->headerRow($sheet, "A{$row}:E{$row}");
        $first = $row + 1;
        foreach ($sections as $section) {
            $row++;
            $rows = collect($section['rows']);
            $sheet->fromArray([[$this->sheetName($section), $rows->count(), $rows->sum('income_sen') / 100, $rows->sum('expense_sen') / 100]], null, "A{$row}");
            $sheet->setCellValue("E{$row}", "=C{$row}-D{$row}");
        }
        $row++;
        $sheet->fromArray([['Total', "=SUM(B{$first}:B".($row - 1).')', "=SUM(C{$first}:C".($row - 1).')', "=SUM(D{$first}:D".($row - 1).')', "=C{$row}-D{$row}"]], null, "A{$row}");
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $row += 2;
        $sheet->fromArray([['Closing balance', $data['closing_sen'] / 100]], null, "A{$row}");
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
        $sheet->getStyle('B5')->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("C{$first}:E{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->setCellValue('A'.($row + 2), 'Each section has its own sheet, in this order. Closing balance covers every transaction in the period, including sections not exported.');
        $sheet->getStyle('A'.($row + 2))->getFont()->setItalic(true)->setSize(9);
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /** @param array{key: string, title: string, rows: list<array<string, mixed>>} $section */
    private function section(Worksheet $sheet, Club $club, string $period, array $section): void
    {
        $sheet->setTitle($this->safeTitle($sheet, $this->sheetName($section)));
        $this->page($sheet);
        $this->heading($sheet, "{$club->name} · {$this->sheetName($section)}", $period);
        $columns = self::COLUMNS;
        if ($section['key'] === 'drive') {
            array_splice($columns, 6, 0, ['Drive']);
        }
        $row = 5;
        $sheet->fromArray([$columns], null, "A{$row}");
        $last = chr(ord('A') + count($columns) - 1);
        $this->headerRow($sheet, "A{$row}:{$last}{$row}");
        $first = $row + 1;
        foreach (collect($section['rows'])->sortBy([['date', 'asc'], ['id', 'asc']])->values() as $i => $r) {
            $row++;
            $values = [$i + 1, $r['date'], $r['order_id'], $r['description'], $r['name'], $r['student_number'], $r['method'] ? ucfirst((string) $r['method']) : '', $r['handled_by'], $r['income_sen'] / 100, $r['expense_sen'] / 100];
            if ($section['key'] === 'drive') {
                array_splice($values, 6, 0, [$r['drive']]);
            }
            $sheet->fromArray([$values], null, "A{$row}");
            $in = chr(ord($last) - 2);
            $out = chr(ord($last) - 1);
            $sheet->setCellValue("{$last}{$row}", $row === $first ? "={$in}{$row}-{$out}{$row}" : "={$last}".($row - 1)."+{$in}{$row}-{$out}{$row}");
        }
        $in = chr(ord($last) - 2);
        $out = chr(ord($last) - 1);
        $row++;
        $sheet->setCellValue("A{$row}", 'Total');
        $sheet->setCellValue("{$in}{$row}", $row > $first ? "=SUM({$in}{$first}:{$in}".($row - 1).')' : 0);
        $sheet->setCellValue("{$out}{$row}", $row > $first ? "=SUM({$out}{$first}:{$out}".($row - 1).')' : 0);
        $sheet->setCellValue("{$last}{$row}", "={$in}{$row}-{$out}{$row}");
        $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$last}{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("{$in}{$first}:{$last}{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        foreach (range('A', $last) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(45);
        $sheet->getStyle("D{$first}:D{$row}")->getAlignment()->setWrapText(true);
        $sheet->freezePane("A{$first}");
    }

    /** @param array{key: string, title: string} $section */
    private function sheetName(array $section): string
    {
        return str_starts_with($section['key'], 'event:') ? "Event · {$section['title']}" : $section['title'];
    }

    /** Excel sheet names: at most 31 characters, no []:*?/\ and unique in the book. */
    private function safeTitle(Worksheet $sheet, string $name): string
    {
        $base = mb_substr(trim((string) preg_replace('/[\[\]:*?\/\\\\]/', '-', $name)), 0, 28);
        $title = $base;
        for ($n = 2; $sheet->getParent()?->sheetNameExists($title); $n++) {
            $title = "{$base} {$n}";
        }

        return $title;
    }

    private function heading(Worksheet $sheet, string $title, string $period): void
    {
        $sheet->fromArray([[$title], [$period], ['Printed '.now()->format('j M Y, g:i A')]], null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A3')->getFont()->setSize(10);
    }

    private function headerRow(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F2937');
    }

    private function page(Worksheet $sheet): void
    {
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.5)->setBottom(0.5);
    }
}
