<?php

namespace App\Services\Profiles;

use App\Enums\DocumentStatus;
use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Club;
use App\Models\Event;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Documents\PdfConverter;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Lifelong profile certificates: club committee positions, event committee positions
 * and event participation. Only what the records prove can be issued; each PDF carries a public check code.
 */
class Certificates
{
    public function __construct(private readonly PdfConverter $pdf) {}

    /**
     * Everything this person can download, newest first.
     *
     * @return list<array{kind: string, source_id: int, title: string, statement: string, date: string, code: ?string}>
     */
    public function available(User $user): array
    {
        $issued = Certificate::where('user_id', $user->id)->get()->keyBy(fn (Certificate $c) => "{$c->kind}:{$c->source_id}");
        $items = [];
        foreach ($this->sources($user) as $item) {
            $items[] = [...$item, 'code' => $issued["{$item['kind']}:{$item['source_id']}"]->code ?? null];
        }

        return $items;
    }

    public function issue(User $user, string $kind, int $sourceId): Certificate
    {
        $source = collect($this->sources($user))->first(fn ($i) => $i['kind'] === $kind && $i['source_id'] === $sourceId);
        abort_unless($source, 404);

        return Certificate::firstOrCreate(
            ['user_id' => $user->id, 'kind' => $kind, 'source_id' => $sourceId],
            ['title' => $source['title'], 'statement' => $source['statement'], 'code' => strtoupper(Str::random(12)), 'issued_at' => now()],
        );
    }

    /** A4 landscape certificate (DOCX built with PhpWord, then LibreOffice PDF). */
    public function render(Certificate $certificate): string
    {
        Settings::setOutputEscapingEnabled(true);
        $word = new PhpWord;
        // Arial: Poppins is the brand font but is rarely installed where LibreOffice renders the PDF.
        $word->setDefaultFontName('Arial');
        $section = $word->addSection(['orientation' => 'landscape', 'marginTop' => 900, 'marginBottom' => 700, 'marginLeft' => 1200, 'marginRight' => 1200,
            'borderSize' => 24, 'borderColor' => '1CBDFD']);
        $center = ['alignment' => Jc::CENTER, 'spaceAfter' => 120];
        $section->addImage(public_path('brand/QIU_Student_Activity_Black.png'), ['height' => 120, 'alignment' => Jc::CENTER]);
        $section->addText('STUDENT LIFE DIVISION · QUEST INTERNATIONAL UNIVERSITY', ['size' => 10, 'color' => '666666'], $center);
        $section->addTextBreak();
        $section->addText(strtoupper($certificate->title), ['size' => 28, 'bold' => true, 'color' => 'E01CEB'], $center);
        $section->addText('This is to certify that', ['size' => 13], $center);
        $section->addText($certificate->user->name, ['size' => 26, 'bold' => true, 'color' => '14213D'], $center);
        if ($number = $certificate->user->student?->student_number) {
            $section->addText($number, ['size' => 11, 'color' => '666666'], $center);
        }
        $section->addTextBreak();
        $section->addText($certificate->statement, ['size' => 14], $center);
        $section->addTextBreak(3);
        $section->addText('Issued '.$certificate->issued_at->format('j F Y').' from the records of the Student Life Division Portal.', ['size' => 10, 'color' => '666666'], $center);
        $section->addText("Verify at {$this->verifyUrl($certificate)} · Code {$certificate->code}", ['size' => 10, 'color' => '666666'], $center);

        $dir = storage_path('app/private/generated');
        is_dir($dir) || mkdir($dir, 0775, true);
        $docx = "{$dir}/certificate-{$certificate->code}-".now()->format('YmdHis').'.docx';
        $word->save($docx);
        $pdf = $this->pdf->convert($docx);
        @unlink($docx);

        return $pdf;
    }

    public function verifyUrl(Certificate $certificate): string
    {
        return route('certificates.verify', $certificate->code);
    }

    /** @return list<array{kind: string, source_id: int, title: string, statement: string, date: string}> */
    private function sources(User $user): array
    {
        $roles = RoleAssignment::with('session')->where('user_id', $user->id)->where('role', ScopedRole::ClubCommittee)->where('scope_type', ScopeType::Club)->get();
        $clubs = Club::whereIn('id', $roles->pluck('scope_id'))->pluck('name', 'id');
        $eventRoles = RoleAssignment::where('user_id', $user->id)->where('scope_type', ScopeType::Event)->get();
        // Event certificates only once an approved event has taken place.
        $events = Event::with('club')->whereIn('id', $eventRoles->pluck('scope_id'))->where('status', DocumentStatus::Approved)->where('ends_at', '<', now())->get()->keyBy('id');
        $attended = Attendance::with('event.club')->where('user_id', $user->id)->whereNotNull('clock_in_at')->whereNotNull('clock_out_at')->get();

        return collect([
            ...$roles->filter(fn ($r) => isset($clubs[$r->scope_id]))->map(fn (RoleAssignment $r) => [
                'kind' => 'club_committee', 'source_id' => $r->id, 'title' => 'Certificate of Appointment',
                'statement' => "served as {$r->position} of {$clubs[$r->scope_id]} for the {$r->session->name} session.",
                'date' => $r->session->starts_on->toDateString(),
            ]),
            ...$eventRoles->filter(fn ($r) => isset($events[$r->scope_id]))->map(fn (RoleAssignment $r) => [
                'kind' => 'event_committee', 'source_id' => $r->id, 'title' => 'Certificate of Appreciation',
                'statement' => "served as {$r->position} of the organising committee of {$events[$r->scope_id]->name} by {$events[$r->scope_id]->club->name} on {$events[$r->scope_id]->starts_at->format('j F Y')}.",
                'date' => $events[$r->scope_id]->starts_at->toDateString(),
            ]),
            ...$attended->map(fn (Attendance $a) => [
                'kind' => 'participation', 'source_id' => $a->id, 'title' => 'Certificate of Participation',
                'statement' => "participated in {$a->event->name} organised by {$a->event->club->name} on {$a->event->starts_at->format('j F Y')}.",
                'date' => $a->event->starts_at->toDateString(),
            ]),
        ])->sortByDesc('date')->values()->all();
    }
}
