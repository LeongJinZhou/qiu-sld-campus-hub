<?php

namespace App\Services\Documents;

use App\Enums\ApprovalDecision;
use App\Enums\ChainRole;
use App\Models\Approval;
use App\Models\Document;
use App\Models\Signature;
use Closure;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
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
        foreach ($book->getAllSheets() as $sheet) {
            self::keepWordsWhole($sheet);
        }

        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0775, true);
        }
        IOFactory::createWriter($book, 'Xlsx')->save($outputPath);
        $book->disconnectWorksheets();

        return $outputPath;
    }

    /**
     * No value is ever split mid-word in the printed copy. Text too long for its cell is shrunk to fit when it is one
     * word (an email, ID, date or reference), or when it would otherwise be cut off by the next cell. Text with spaces in
     * a wrapping cell still wraps, but only between words.
     */
    public static function keepWordsWhole(Worksheet $sheet): void
    {
        $default = $sheet->getDefaultColumnDimension()->getWidth();
        $default = $default > 0 ? $default : 8.43;
        $merged = [];
        foreach ($sheet->getMergeCells() as $range) {
            [$start] = explode(':', $range);
            $merged[$start] = $range;
        }
        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            $cell = $sheet->getCell($coordinate);
            $value = $cell->getValue();
            if (! is_string($value) || $value === '' || str_starts_with($value, '=')) {
                continue;
            }
            $width = 0.0;
            [$from, $to] = Coordinate::rangeBoundaries($merged[$coordinate] ?? "{$coordinate}:{$coordinate}");
            for ($c = $from[0]; $c <= $to[0]; $c++) {
                $w = $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->getWidth();
                $width += $w > 0 ? $w : $default;
            }
            $style = $sheet->getStyle($coordinate);
            $size = (float) ($style->getFont()->getSize() ?: 11);
            $need = fn (string $text): float => mb_strlen($text) * $size / 11 * 1.05 + 1;
            $lines = preg_split('/\R/', $value) ?: [$value];
            $longestWord = max(array_map(fn (string $w) => $need($w), preg_split('/\s+/', $value) ?: [$value]));
            $longestLine = max(array_map($need, $lines));
            if ($longestLine <= $width) {
                continue;
            }
            $alignment = $style->getAlignment();
            $rightCell = Coordinate::stringFromColumnIndex($to[0] + 1).$from[1];
            $clipped = ! $alignment->getWrapText() && $sheet->cellExists($rightCell) && trim((string) $sheet->getCell($rightCell)->getValue()) !== '';
            if (($alignment->getWrapText() && $longestWord > $width) || $clipped) {
                $alignment->setWrapText(false)->setShrinkToFit(true);
            }
        }
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

    /** The approved signature for a chain role on a document, if signed. */
    public static function signed(?Document $document, ChainRole $role): ?Signature
    {
        return $document?->approvals->first(fn (Approval $a) => $a->role === $role && $a->decision === ApprovalDecision::Approved)?->signature;
    }

    /**
     * Fills "Prepared by" / "Reviewed by" blocks wherever inserted rows moved them: the drawn signature in the
     * blank rows above "Name:", the name, the preparer's position, and the server date with verification code.
     *
     * @param  list<Signer>  $signers  in the order the blocks appear
     */
    public function signers(Worksheet $sheet, array $signers): void
    {
        $names = [];
        for ($row = 1; $row <= $sheet->getHighestDataRow('A'); $row++) {
            if (trim((string) $sheet->getCell("A{$row}")->getValue()) === 'Name:') {
                $names[] = $row;
            }
        }
        foreach ($signers as $i => $signer) {
            $row = $names[$i] ?? null;
            if ($row === null) {
                continue;
            }
            $sheet->setCellValue("B{$row}", $signer->name);
            if ($i === 0) {
                $sheet->setCellValue('B'.($row + 1), $signer->position);
            }
            if ($signer->signature) {
                $sheet->setCellValue('B'.($row + 2), $signer->signature->signed_at->format('d/m/Y g:i A'));
                $image = Storage::disk('local')->path($signer->signature->image_path);
                if (is_file($image)) {
                    (new Drawing)->setPath($image)->setHeight(48)->setCoordinates('B'.($row - 3))->setOffsetY(4)->setWorksheet($sheet);
                }
            }
        }
    }
}
