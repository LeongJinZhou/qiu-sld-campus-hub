<?php

namespace App\Services\Documents;

use App\Enums\ScopedRole;
use App\Models\Club;
use App\Services\Time\SessionResolver;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/** 10 Full Member List: Club Name, Advisor, then No / Name / Position / Student ID / Email / Year Group / Program. */
class MemberListExport
{
    public const TEMPLATE = '10. Full Member List.xlsx';

    public function __construct(private readonly XlsxRenderer $xlsx, private readonly SessionResolver $sessions) {}

    /** @param Collection<int, array<string, mixed>> $rows from MembersQuery */
    public function render(Club $club, Collection $rows): string
    {
        $advisor = $club->roleAssignments()->where('session_id', $this->sessions->current()->id)->where('role', ScopedRole::Advisor)->with('user')->first()?->user;
        $output = storage_path("app/private/generated/members-{$club->id}-".now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.xlsx');

        return $this->xlsx->render(self::TEMPLATE, function (Spreadsheet $book) use ($club, $rows, $advisor): void {
            $sheet = $book->getActiveSheet();
            // Column A is narrow and the labels overflow into B, so the value follows the label in the same cell.
            $sheet->setCellValue('A1', "Club Name: {$club->name}")->setCellValue('A2', 'Advisor: '.($advisor->name ?? ''));
            // Clear the template's sample rows (5-8), then write the members from row 5.
            foreach (range(5, 8) as $row) {
                foreach (range('A', 'G') as $col) {
                    $sheet->setCellValue("{$col}{$row}", null);
                }
            }
            $sheet->fromArray($rows->values()->map(fn (array $r, int $i) => [
                $i + 1, $r['name'], $r['position'], $r['student_number'], $r['email'],
                $r['status'] === 'alumni' ? 'Alumni' : $r['year_group'], $r['programme_short'],
            ])->all(), null, 'A5', true);
            // Every column on one page width; the email column is as wide as the longest address, so none is cut off.
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
            $longest = $rows->max(fn (array $r) => mb_strlen((string) $r['email'])) ?? 0;
            $sheet->getColumnDimension('E')->setWidth(max($sheet->getColumnDimension('E')->getWidth(), $longest * 1.05 + 2));
            $sheet->getColumnDimension('F')->setWidth(max($sheet->getColumnDimension('F')->getWidth(), 12));
        }, $output);
    }
}
