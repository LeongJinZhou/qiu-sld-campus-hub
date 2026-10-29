<?php

namespace App\Services\Reports;

use App\Enums\DocumentStatus;
use App\Models\AcademicSession;
use App\Models\AlbumPhoto;
use App\Models\Club;
use App\Models\Event;
use App\Models\GeneratedFile;
use App\Models\ReportTheme;
use App\Models\User;
use App\Services\Documents\PdfConverter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Annual Activities Report (12 template): cover, summary table, then per event a details slide and a photos
 * slide. Details are AI-drafted and editable before generating; tools/pptx/annual_report.py builds the PPTX.
 */
class AnnualReports
{
    public const TEMPLATE = '12. Annual Activities Report.pptx';

    public const MAX_PHOTOS = 6;

    public function __construct(private readonly PdfConverter $pdf) {}

    public function for(Club $club, AcademicSession $session, User $user): GeneratedFile
    {
        return GeneratedFile::firstOrCreate(
            ['club_id' => $club->id, 'session_id' => $session->id, 'kind' => GeneratedFile::ANNUAL_REPORT],
            ['data' => ['details' => []], 'created_by' => $user->id, 'report_theme_id' => ReportTheme::where('is_default', true)->value('id')],
        );
    }

    /** @return Collection<int, Event> approved events of the session, in date order */
    public function events(GeneratedFile $file): Collection
    {
        return Event::where('club_id', $file->club_id)->where('session_id', $file->session_id)
            ->where('status', DocumentStatus::Approved)->orderBy('starts_at')->get();
    }

    /** @param array<int|string, string> $details event id => text */
    public function saveDetails(GeneratedFile $file, array $details, ?int $themeId): void
    {
        $ids = $this->events($file)->pluck('id')->all();
        $file->update([
            'data' => ['details' => array_intersect_key(array_map(fn ($d) => trim((string) $d), $details), array_flip($ids))],
            'report_theme_id' => $themeId,
        ]);
    }

    public function generate(GeneratedFile $file): void
    {
        $file->loadMissing(['club', 'theme']);
        $dir = storage_path('app/private/generated');
        is_dir($dir) || mkdir($dir, 0775, true);
        $base = "{$dir}/annual-{$file->club_id}-{$file->session_id}-".now()->format('YmdHis');
        $theme = $file->theme;

        $job = [
            'template' => resource_path('templates/original/'.self::TEMPLATE),
            'output' => "{$base}.pptx",
            'club' => $file->club->name,
            'events' => $this->events($file)->map(fn (Event $e) => [
                'name' => $e->name,
                'date' => $e->starts_at->format('jS F Y'),
                'time' => $e->starts_at->format('g.i A').' – '.$e->ends_at->format('g.i A'),
                'venue' => (string) $e->venue,
                'details' => (string) ($file->data['details'][$e->id] ?? ''),
                'photos' => $this->photos($e),
            ])->values()->all(),
            'theme' => $theme ? [
                ...$theme->only('background_color', 'title_color', 'body_color', 'font'),
                'background_path' => $theme->background_path ? Storage::disk('public')->path($theme->background_path) : null,
            ] : null,
        ];
        file_put_contents("{$base}.json", json_encode($job, JSON_THROW_ON_ERROR));

        $process = new Process([config('sld.python'), base_path('tools/pptx/annual_report.py'), "{$base}.json"]);
        $process->setTimeout(120)->run();
        @unlink("{$base}.json");
        if (! $process->isSuccessful() || ! is_file("{$base}.pptx")) {
            throw new RuntimeException('Annual report generation failed: '.trim($process->getErrorOutput()));
        }
        $pdf = $this->pdf->convert("{$base}.pptx");

        foreach (array_filter([$file->pptx_path, $file->pdf_path]) as $old) {
            @unlink($old);
        }
        $file->update(['pptx_path' => "{$base}.pptx", 'pdf_path' => $pdf, 'generated_at' => now()]);
    }

    /** @return list<string> up to 6 raster photos from the event's albums */
    private function photos(Event $event): array
    {
        return AlbumPhoto::whereIn('album_id', $event->club->albums()->where('event_id', $event->id)->select('id'))->limit(20)->get()
            ->map(fn (AlbumPhoto $p) => Storage::disk('public')->path($p->path))
            ->filter(fn (string $path) => is_file($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true))
            ->take(self::MAX_PHOTOS)->values()->all();
    }
}
