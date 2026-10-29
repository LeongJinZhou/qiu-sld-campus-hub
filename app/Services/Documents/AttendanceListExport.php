<?php

namespace App\Services\Documents;

use App\Enums\ScopedRole;
use App\Models\Attendance;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** 03 Event Attendance List: Participant, Committee (with CCA points) and Pictures sheets. */
class AttendanceListExport
{
    public const TEMPLATE = '03. Event Attendance List Template.xlsx';

    public function __construct(private readonly XlsxRenderer $xlsx) {}

    public function render(Event $event): string
    {
        $event->load(['club', 'attendance.user', 'committee.user']);
        $present = $event->attendance->whereNotNull('clock_in_at')->sortBy(fn (Attendance $a) => $a->user->name)->values();
        [$committee, $participants] = $present->partition(fn (Attendance $a) => $a->role === 'committee');
        $secretary = $event->committee->firstWhere('position', 'Secretary')?->user;
        $advisor = $event->club->roleAssignments()->where('session_id', $event->session_id)->where('role', ScopedRole::Advisor)->first()?->user;

        $output = storage_path("app/private/generated/attendance-{$event->id}-".now()->format('YmdHis').'.xlsx');

        return $this->xlsx->render(self::TEMPLATE, function (Spreadsheet $book) use ($event, $participants, $committee, $secretary, $advisor): void {
            $sheet = $book->getSheetByName('Participant');
            $sheet->setCellValue('B1', $event->name)->setCellValue('D1', $event->venue)
                ->setCellValue('B2', $event->club->name)->setCellValue('D2', $event->cca_participant)
                ->setCellValue('B3', $event->starts_at->format('d/m/Y'))
                ->setCellValue('D3', $event->starts_at->format('g.i A').' – '.$event->ends_at->format('g.i A'));
            $this->xlsx->fillRows($sheet, 6, 19, $participants->values()->map(fn (Attendance $a, int $i) => [$i + 1, $a->user->name, $a->user->email])->all());
            $this->signers($sheet, $secretary?->name, 'Secretary', $advisor?->name);

            $sheet = $book->getSheetByName('Committee');
            $this->xlsx->fillRows($sheet, 3, 17, $committee->values()->map(fn (Attendance $a, int $i) => [
                $i + 1, $a->user->name, $a->user->email, $event->committee->firstWhere('user_id', $a->user_id)?->position, $event->cca_committee,
            ])->all());
            $this->signers($sheet, $secretary?->name, 'Secretary', $advisor?->name);

            $this->pictures($book, $event);
        }, $output);
    }

    /** Fills the "Name:" lines under Prepared by / Reviewed by, wherever inserted rows moved them. */
    private function signers(Worksheet $sheet, ?string $preparer, string $position, ?string $reviewer): void
    {
        $names = [];
        for ($row = 1; $row <= $sheet->getHighestDataRow('A'); $row++) {
            if (trim((string) $sheet->getCell("A{$row}")->getValue()) === 'Name:') {
                $names[] = $row;
            }
        }
        if (isset($names[0])) {
            $sheet->setCellValue("B{$names[0]}", $preparer)->setCellValue('B'.($names[0] + 1), $position);
        }
        if (isset($names[1])) {
            $sheet->setCellValue("B{$names[1]}", $reviewer);
        }
    }

    private function pictures(Spreadsheet $book, Event $event): void
    {
        $sheet = $book->getSheetByName('Pictures');
        $photos = $event->club->albums()->where('event_id', $event->id)->with('photos')->get()->flatMap->photos->take(12);
        foreach ($photos->values() as $i => $photo) {
            $path = Storage::disk('public')->path($photo->path);
            if (! is_file($path)) {
                continue;
            }
            $drawing = new Drawing;
            $drawing->setPath($path)->setHeight(220)->setCoordinates(($i % 2 === 0 ? 'A' : 'H').(1 + intdiv($i, 2) * 13))->setWorksheet($sheet);
        }
    }
}
