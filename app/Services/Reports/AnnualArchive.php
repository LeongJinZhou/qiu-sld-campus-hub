<?php

namespace App\Services\Reports;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Agm;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventDocumentItem;
use App\Models\GeneratedFile;
use App\Models\Meeting;
use App\Services\Documents\DocumentExporter;
use App\Services\Documents\FinanceWorkbookExport;
use App\Services\Documents\MemberListExport;
use App\Services\Finance\FinanceSections;
use App\Services\Membership\MembersQuery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * The club's year, archived as one ZIP when SLD acknowledges the AGM. Native files (Word, Excel, PowerPoint), so
 * it never depends on PDF conversion:
 *
 *   01 AGM/                 AGM Report, AGM photos
 *   02 Finance/             Finance workbook (session), 02 Finance Record is inside the workbook export
 *   03 Members/             Full Member List (10)
 *   04 Annual Report/       Annual Activities Report (12)
 *   05 Events/YYYY-MM-DD Event/   Proposal, Post mortem, Attendance list, uploaded post-event documents, photos/
 *   06 Weekly reports/YYYY-MM-DD Title/   Minutes, photos/
 *
 * Downloads are limited to the club's committee and advisor and SLD (the club's internal access).
 */
class AnnualArchive
{
    public function __construct(
        private readonly DocumentExporter $documents,
        private readonly FinanceWorkbookExport $workbook,
        private readonly FinanceSections $sections,
        private readonly MemberListExport $members,
        private readonly MembersQuery $memberRows,
        private readonly EvidencePhotos $photos,
    ) {}

    public function build(Agm $agm): GeneratedFile
    {
        $club = $agm->club;
        $session = $agm->session;
        $root = Str::of(Str::ascii("{$club->name} {$session->name} Archive"))->replace('/', '-')->toString().'/';
        $file = storage_path("app/private/archives/{$club->id}-".str_replace('/', '-', $session->name).'-'.Str::random(6).'.zip');
        @mkdir(dirname($file), 0775, true);
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $temp = [];
        $add = function (string $path, string $name) use ($zip, &$temp): void {
            if (is_file($path)) {
                $zip->addFile($path, $name);
                $temp[] = $path;
            }
        };
        $safe = fn (callable $make, string $what) => rescue($make, function (Throwable $e) use ($what, $club): ?string {
            Log::warning("Archive: {$what} skipped", ['club_id' => $club->id, 'error' => $e->getMessage()]);

            return null;
        }, false);

        // 01 AGM
        if ($agm->report && $agm->report->latestVersion) {
            $add((string) $safe(fn () => $this->documents->export($agm->report, 'docx'), 'AGM report'), $root.'01 AGM/AGM Report.docx');
        }
        $this->addPhotos($zip, $agm, $root.'01 AGM/photos/');
        // 02 Finance and 03 Members
        $add((string) $safe(fn () => $this->workbook->render($club, $this->sections->build($club, $session)), 'finance workbook'), $root.'02 Finance/Finance '.str_replace('/', '-', $session->name).'.xlsx');
        $add((string) $safe(fn () => $this->members->render($club, $this->memberRows->rows([$club->id])), 'member list'), $root.'03 Members/Full Member List.xlsx');
        // 04 Annual Activities Report (already generated; not a temp file)
        $annual = GeneratedFile::where(['club_id' => $club->id, 'session_id' => $session->id, 'kind' => GeneratedFile::ANNUAL_REPORT])->whereNotNull('pptx_path')->latest('generated_at')->first();
        if ($annual && is_file((string) $annual->pptx_path)) {
            $zip->addFile($annual->pptx_path, $root.'04 Annual Report/Annual Activities Report.pptx');
        }
        // 05 Events
        foreach (Event::where('club_id', $club->id)->where('session_id', $session->id)->where('status', DocumentStatus::Approved)->orderBy('starts_at')->get() as $event) {
            $folder = $root.'05 Events/'.$event->starts_at->format('Y-m-d').' '.$this->clean($event->name).'/';
            if ($event->proposal?->latestVersion) {
                $add((string) $safe(fn () => $this->documents->export($event->proposal, 'docx'), 'proposal'), $folder.'Proposal.docx');
            }
            $postMortem = Document::where('event_id', $event->id)->where('type', DocumentType::PostMortem)->first();
            if ($postMortem?->latestVersion) {
                $add((string) $safe(fn () => $this->documents->export($postMortem, 'xlsx'), 'post mortem'), $folder.'Post Mortem.xlsx');
            }
            $attendance = Document::where('event_id', $event->id)->where('type', DocumentType::AttendanceList)->first();
            if ($attendance) {
                $add((string) $safe(fn () => $this->documents->export($attendance, 'xlsx'), 'attendance'), $folder.'Attendance List.xlsx');
            }
            foreach (EventDocumentItem::where('event_id', $event->id)->whereNotNull('file_path')->get() as $item) {
                if (Storage::disk('local')->exists((string) $item->file_path)) {
                    $zip->addFile(Storage::disk('local')->path($item->file_path), $folder.'Post-event/'.$item->label().'.'.pathinfo((string) $item->file_path, PATHINFO_EXTENSION));
                }
            }
            $this->photos->addTo($zip, $event, $folder.'photos/');
        }
        // 06 Weekly reports
        foreach (Meeting::with('minutes')->where('club_id', $club->id)->where('session_id', $session->id)->orderBy('starts_at')->get() as $meeting) {
            $folder = $root.'06 Weekly reports/'.$meeting->starts_at->format('Y-m-d').' '.$this->clean($meeting->title).'/';
            if ($meeting->minutes?->latestVersion) {
                $add((string) $safe(fn () => $this->documents->export($meeting->minutes, 'docx'), 'minutes'), $folder.'Minutes.docx');
            }
            $this->addPhotos($zip, $meeting, $folder.'photos/');
        }
        $zip->close();
        foreach ($temp as $path) {
            @unlink($path);
        }

        $archive = GeneratedFile::firstOrNew(['club_id' => $club->id, 'session_id' => $session->id, 'kind' => GeneratedFile::ANNUAL_ARCHIVE]);
        if ($archive->zip_path && $archive->zip_path !== $file) {
            @unlink($archive->zip_path);
        }
        $archive->fill(['zip_path' => $file, 'generated_at' => now(), 'created_by' => $archive->created_by ?? $agm->acknowledged_by ?? $agm->organiser_id])->save();

        return $archive;
    }

    private function addPhotos(ZipArchive $zip, Agm|Meeting $subject, string $folder): void
    {
        foreach ($this->photos->paths($subject) as $i => $path) {
            if (Storage::disk('public')->exists($path)) {
                $zip->addFile(Storage::disk('public')->path($path), $folder.sprintf('%02d.%s', $i + 1, pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg'));
            }
        }
    }

    private function clean(string $name): string
    {
        return trim((string) preg_replace('/[\\\\\/:*?"<>|]+/', '-', Str::ascii($name))) ?: 'Untitled';
    }
}
