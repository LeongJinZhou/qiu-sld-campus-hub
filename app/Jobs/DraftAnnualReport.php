<?php

namespace App\Jobs;

use App\Models\GeneratedFile;
use App\Services\Ai\AnnualReportWriter;
use App\Services\Reports\AnnualReports;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** AI writes the Details of every event that has none yet; the club edits them before generating. */
class DraftAnnualReport implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $fileId) {}

    public static function statusKey(int $fileId): string
    {
        return "annual-draft:{$fileId}";
    }

    public function handle(AnnualReportWriter $writer, AnnualReports $reports): void
    {
        $file = GeneratedFile::with('club')->find($this->fileId);
        if (! $file) {
            return;
        }
        $details = $file->data['details'] ?? [];
        $missing = $reports->events($file)->filter(fn ($e) => trim((string) ($details[$e->id] ?? '')) === '');
        $written = $writer->write($file->club->name, $missing);
        if ($written) {
            $reports->saveDetails($file, array_replace($details, $written), $file->report_theme_id);
        }
        Cache::put(self::statusKey($file->id), $written === null ? 'failed' : 'done', now()->addMinutes(10));
    }
}
