<?php

namespace App\Services\Documents;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * A titled table as a plain spreadsheet (A4 landscape, one page wide, header repeated on every page), so the PDF
 * conversion reads well. Columns are sized to their content: dates, numbers, IDs and other single words always stay on
 * one line; only long text (titles, names, lists) wraps, and only between words.
 */
class SimpleTableExport
{
    private const MAX_WIDTH = 42;

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<mixed>>  $rows
     */
    public function render(string $title, string $subtitle, array $headers, iterable $rows): string
    {
        $book = new Spreadsheet;
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet = $book->getActiveSheet();
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.5)->setBottom(0.5);

        $columns = array_merge(['No'], $headers);
        $last = Coordinate::stringFromColumnIndex(count($columns));
        // Title lines are merged across the table so they never widen the first column.
        foreach ([$title, $subtitle, 'Printed '.now()->format('j M Y, g:i A')] as $i => $line) {
            $row = $i + 1;
            $sheet->setCellValue("A{$row}", $line)->mergeCells("A{$row}:{$last}{$row}");
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A3')->getFont()->getColor()->setRGB('52525B');

        $head = 5;
        $sheet->fromArray([$columns], null, "A{$head}", true);
        $sheet->getStyle("A{$head}:{$last}{$head}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D12A32']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        // Widest single word and widest full value per column decide its width.
        $longest = array_map(fn (string $h) => max(array_map('mb_strlen', explode(' ', $h))), $columns);
        $numeric = array_fill(0, count($columns), true);
        $full = array_map('mb_strlen', $columns);
        $row = $head;
        foreach ($rows as $i => $values) {
            $row++;
            $line = array_merge([$i + 1], array_values($values));
            // Strict null comparison: a 0 (no one present, RM 0.00) is printed, not left blank.
            $sheet->fromArray([$line], null, "A{$row}", true);
            foreach ($line as $c => $value) {
                $text = (string) $value;
                $full[$c] = max($full[$c] ?? 0, mb_strlen($text));
                $longest[$c] = max($longest[$c] ?? 0, ...array_map('mb_strlen', preg_split('/\s+/', $text) ?: [$text]));
                $numeric[$c] = ($numeric[$c] ?? true) && ($text === '' || $text === '-' || is_numeric($text));
            }
        }

        foreach ($columns as $c => $_) {
            $letter = Coordinate::stringFromColumnIndex($c + 1);
            $width = min(max($full[$c], 4) + 2, self::MAX_WIDTH);
            $width = max($width, $longest[$c] + 2); // a single word is never narrower than its column
            $sheet->getColumnDimension($letter)->setWidth($width);
            if ($numeric[$c] && $row > $head) {
                $sheet->getStyle("{$letter}{$head}:{$letter}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            if ($width < $full[$c] + 2) {
                $sheet->getStyle("{$letter}".($head + 1).":{$letter}{$row}")->getAlignment()->setWrapText(true);
            }
        }
        if ($row > $head) {
            $sheet->getStyle("A{$head}:{$last}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D4D4D8');
            $sheet->getStyle('A'.($head + 1).":{$last}{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A{$head}:A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($head, $head);
        $sheet->freezePane('A'.($head + 1));

        $path = storage_path('app/private/generated/table-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.xlsx');
        @mkdir(dirname($path), 0775, true);
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return $path;
    }
}
