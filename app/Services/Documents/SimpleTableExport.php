<?php

namespace App\Services\Documents;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/** A titled table as a plain spreadsheet (landscape, fit to width, so the PDF conversion reads well). */
class SimpleTableExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<list<mixed>>  $rows
     */
    public function render(string $title, string $subtitle, array $headers, iterable $rows): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->fromArray([[$title], [$subtitle], ['Printed '.now()->format('j M Y, g:i A')]], null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->fromArray([array_merge(['No'], $headers)], null, 'A5');
        $last = chr(ord('A') + count($headers));
        $sheet->getStyle("A5:{$last}5")->getFont()->setBold(true);
        $row = 5;
        foreach ($rows as $i => $values) {
            $row++;
            $sheet->fromArray([array_merge([$i + 1], $values)], null, "A{$row}");
        }
        foreach (range('A', $last) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $path = storage_path('app/private/generated/table-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.xlsx');
        @mkdir(dirname($path), 0775, true);
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return $path;
    }
}
