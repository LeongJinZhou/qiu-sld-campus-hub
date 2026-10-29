<?php

namespace App\Services\Documents;

use Closure;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Opens an official XLSX template, lets the caller fill blanks, and saves a copy. The original is never written. */
class XlsxRenderer
{
    /** @param Closure(Spreadsheet): void $fill */
    public function render(string $template, Closure $fill, string $outputPath): string
    {
        // The official templates format 1,000 rows x 26 columns per sheet, which outgrows PHP's default 128 MB.
        ini_set('memory_limit', '512M');
        $book = IOFactory::load(resource_path("templates/original/{$template}"));
        $fill($book);

        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0775, true);
        }
        IOFactory::createWriter($book, 'Xlsx')->save($outputPath);
        $book->disconnectWorksheets();

        return $outputPath;
    }

    /**
     * Writes rows starting at $firstRow, inserting extra rows (copying the first row's style) before
     * $footerRow when the template's blank rows run out, so the signature block moves down intact.
     *
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function fillRows(Worksheet $sheet, int $firstRow, int $footerRow, array $rows, int $gap = 0): void
    {
        $capacity = $footerRow - $gap - $firstRow;
        if (count($rows) > $capacity) {
            $sheet->insertNewRowBefore($footerRow - $gap, count($rows) - $capacity);
        }
        foreach ($rows as $i => $row) {
            $sheet->fromArray([$row], null, 'A'.($firstRow + $i), true);
        }
    }
}
