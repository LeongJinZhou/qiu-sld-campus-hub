<?php

namespace App\Services\Documents;

use App\Enums\ApprovalDecision;
use App\Enums\ChainRole;
use App\Models\Approval;
use App\Models\Document;
use App\Models\Signature;
use Closure;
use Illuminate\Support\Facades\Storage;
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
                $sheet->setCellValue('B'.($row + 2), $signer->signature->signed_at->format('d/m/Y g:i A').' · Verify '.$signer->signature->verification_code);
                $image = Storage::disk('local')->path($signer->signature->image_path);
                if (is_file($image)) {
                    (new Drawing)->setPath($image)->setHeight(48)->setCoordinates('B'.($row - 3))->setOffsetY(4)->setWorksheet($sheet);
                }
            }
        }
    }
}
