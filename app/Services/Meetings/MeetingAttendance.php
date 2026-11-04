<?php

namespace App\Services\Meetings;

use App\Enums\ScopedRole;
use App\Models\Meeting;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Time\SessionResolver;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * A meeting's attendance checklist: everyone the meeting is for, with their role, whether they were present, and
 * their QR check-in / check-out times. Shown to approvers with the minutes and exported to Excel (and PDF).
 */
class MeetingAttendance
{
    public const HEADERS = ['No', 'Name', 'Student / Staff ID', 'Role', 'Present', 'Check-in', 'Check-out', 'Recorded by'];

    public function __construct(private readonly Meetings $meetings, private readonly SessionResolver $sessions) {}

    /** @return list<array{user_id: int, name: string, id_number: string, role: string, present: bool, in: ?string, out: ?string, method: string}> */
    public function rows(Meeting $meeting): array
    {
        $recorded = $meeting->attendees()->get()->keyBy('id');
        $roles = RoleAssignment::query()->actingFor($meeting->club_id, $this->sessions->current(), training: true)
            ->whereIn('role', [ScopedRole::ClubCommittee, ScopedRole::Advisor])->get()->keyBy('user_id');
        $people = $this->meetings->invitees($meeting)->merge($recorded->values())->unique('id')->load(['student', 'staff']);
        $time = fn (?string $at): ?string => $at ? Carbon::parse($at)->format('g:i A') : null;

        return $people->sortBy([
            fn (User $a, User $b) => $this->rank($roles[$a->id] ?? null) <=> $this->rank($roles[$b->id] ?? null),
            fn (User $a, User $b) => strcasecmp($a->name, $b->name),
        ])->values()->map(function (User $u) use ($recorded, $roles, $time): array {
            $pivot = $recorded[$u->id]->pivot ?? null;
            $role = $roles[$u->id] ?? null;

            return [
                'user_id' => $u->id,
                'name' => $u->name,
                'id_number' => (string) ($u->student->student_number ?? $u->staff->staff_number ?? ''),
                'role' => $role ? ($role->role === ScopedRole::Advisor ? 'Advisor' : ($role->position ?: 'Committee')) : 'Member',
                'present' => (bool) ($pivot->present ?? false),
                'in' => $time($pivot->checked_in_at ?? null),
                'out' => $time($pivot->checked_out_at ?? null),
                'method' => match ($pivot->method ?? null) {
                    'qr' => 'QR scan',
                    'manual' => 'Committee',
                    default => '',
                },
            ];
        })->all();
    }

    /** The checklist as an .xlsx file (A4 landscape, one page wide), ready for PDF conversion. */
    public function export(Meeting $meeting): string
    {
        $meeting->loadMissing('club');
        $rows = $this->rows($meeting);
        $present = count(array_filter($rows, fn (array $r) => $r['present']));
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Attendance');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.5)->setBottom(0.5);
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $sheet->setCellValue('A1', 'Attendance Checklist')->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $details = [
            ['Club', $meeting->club->name, 'Type', Meeting::KINDS[$meeting->kind] ?? $meeting->kind],
            ['Title', $meeting->title, 'For', Meeting::AUDIENCES[$meeting->kind === 'activity' ? $meeting->audience : 'committee'] ?? ''],
            ['Date', $meeting->starts_at->format('j F Y'), 'Time', $meeting->starts_at->format('g:i A').' – '.$meeting->ends_at->format('g:i A')],
            ['Venue', $meeting->venue ?: ($meeting->meet_link ? 'Google Meet' : '-'), 'Present', "{$present} of ".count($rows)],
        ];
        foreach ($details as $i => [$l1, $v1, $l2, $v2]) {
            $r = $i + 3;
            $sheet->setCellValue("A{$r}", $l1)->setCellValue("B{$r}", $v1)->mergeCells("B{$r}:D{$r}")
                ->setCellValue("E{$r}", $l2)->setCellValue("F{$r}", $v2)->mergeCells("F{$r}:H{$r}");
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $sheet->getStyle("E{$r}")->getFont()->setBold(true);
        }

        $head = 8;
        $sheet->fromArray([self::HEADERS], null, "A{$head}");
        $sheet->getStyle("A{$head}:H{$head}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D12A32']],
        ]);
        $r = $head;
        foreach ($rows as $i => $row) {
            $r++;
            $sheet->fromArray([[$i + 1, $row['name'], $row['id_number'], $row['role'], $row['present'] ? 'Yes' : 'No', $row['in'] ?? '-', $row['out'] ?? '-', $row['method'] ?: '-']], null, "A{$r}");
        }
        $sheet->getStyle("A{$head}:H{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D4D4D8');
        $sheet->getStyle("A{$head}:H{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$head}:A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E{$head}:G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        // Fixed widths wide enough for every value: names wrap at spaces only; IDs, times and dates never split.
        foreach (['A' => 6, 'B' => 34, 'C' => 20, 'D' => 22, 'E' => 10, 'F' => 12, 'G' => 12, 'H' => 15] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getStyle("B3:B{$r}")->getAlignment()->setWrapText(true);
        $sheet->getStyle('F3:F6')->getAlignment()->setWrapText(true);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($head, $head);

        $sign = $r + 3;
        $sheet->setCellValue("B{$sign}", 'Prepared by (Secretary)')->setCellValue("F{$sign}", 'Verified by (Club Advisor)');
        $sheet->setCellValue('B'.($sign + 3), 'Name & date: ______________________')->setCellValue('F'.($sign + 3), 'Name & date: ______________________');
        $sheet->getStyle("B{$sign}:F{$sign}")->getFont()->setBold(true);

        $path = storage_path('app/private/generated/meeting-attendance-'.$meeting->id.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.xlsx');
        @mkdir(dirname($path), 0775, true);
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return $path;
    }

    /** Committee first (President, Vice, Secretary, Treasurer, others), then advisors, then members. */
    private function rank(?RoleAssignment $role): int
    {
        if (! $role) {
            return 3;
        }

        return $role->role === ScopedRole::Advisor ? 2 : (in_array($role->position, ['President', 'Vice President', 'Secretary', 'Treasurer'], true) ? 0 : 1);
    }
}
