<?php

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Payee;
use App\Models\Student;
use App\Services\Agm\AgmReportForm;
use App\Services\Agm\Agms;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Documents\AgmReportDocx;
use App\Services\Documents\AttendanceListExport;
use App\Services\Documents\DocumentExporter;
use App\Services\Documents\DocxRenderer;
use App\Services\Documents\FinanceRecordExport;
use App\Services\Documents\IndemnityPdf;
use App\Services\Documents\MemberListExport;
use App\Services\Documents\PdfConverter;
use App\Services\Events\AttendanceService;
use App\Services\Finance\Ledger;
use App\Services\Meetings\Meetings;
use App\Services\Membership\MembersQuery;
use App\Services\Reports\AnnualReports;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Process;

/*
 * Template fidelity check (BUILD_PROMPT Phase 5.6). Renders every official template with full sample data,
 * converts to PDF and saves each next to a PDF of the untouched original in storage/qa/, with REPORT.md.
 * Slow (LibreOffice), so it runs only on request:  QA_TEMPLATES=1 php artisan test --filter=TemplateFidelity
 */
it('renders every official template for side-by-side comparison', function () {
    Notification::fake();
    $qa = storage_path('qa');
    File::ensureDirectoryExists($qa);
    File::cleanDirectory($qa);
    $pdf = app(PdfConverter::class);
    $outputs = [];

    $f = proposalFixture();
    $this->club = $club = $f['club'];
    $this->secretary = $secretary = $club->roleAssignments()->where('position', 'Secretary')->first()->user;
    $this->sld = sldOfficer();
    $treasurer = $club->roleAssignments()->where('position', 'Treasurer')->first()->user;
    $event = $f['event'];
    $event->update(['is_outdoor' => true, 'venue' => 'Pantai Remis, Perak']);

    // 06 Proposal, through the full chain.
    app(ApprovalEngine::class)->submit($f['document'], $f['president'], completeProposal($f, ['budget' => ['expenses' => [
        ['description' => 'Gloves', 'price_sen' => 500, 'quantity' => 40], ['description' => 'Refreshments & water', 'price_sen' => 1000, 'quantity' => 30],
    ]]]), signaturePng(), signatureStrokes());
    approveRemainingSteps($f['document']);
    $outputs['06 Event Proposal'] = ['06. Event Proposal Template 052026.docx', app(DocumentExporter::class)->export($f['document']->fresh(), 'docx')];

    // 05 Indemnity: a registered participant signs.
    $student = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    $event->registrations()->create(['user_id' => $student->id]);
    $crew = $event->committee()->first()->user;
    foreach ([$student, $crew] as $signer) {
        $this->actingAs($signer)->post("/events/{$event->slug}/indemnity", [
            'emergency_name' => 'Mother', 'emergency_mobile_phone' => '012-3456789', 'emergency_relationship' => 'Mother',
            'address' => '12 Jalan Test, Ipoh', 'consent' => '1', 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
        ])->assertSessionHas('success');
    }
    $outputs['05 Indemnity'] = ['05. SLD Indemnity Form .pdf', app(IndemnityPdf::class)->render($event->fresh())];

    // 07 Fund withdrawal.
    $payee = Payee::create(['club_id' => $club->id, 'kind' => 'treasurer', 'name' => $treasurer->name, 'bank_name' => 'CIMB', 'account_holder' => $treasurer->name, 'account_number' => '7001234567']);
    $this->actingAs($secretary)->post("/clubs/{$club->slug}/fund-requests", [
        'event_id' => $event->id, 'purpose' => 'Beach cleanup supplies',
        'items' => [['description' => 'Gloves', 'unit_sen' => 500, 'quantity' => 40, 'method' => 'vendor'], ['description' => 'Refreshments & water', 'unit_sen' => 1000, 'quantity' => 30, 'method' => 'self']],
        'payees' => [['payee_id' => $payee->id, 'amount_sen' => 50000]],
        'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
    ])->assertRedirect();
    $fund = Document::where('type', DocumentType::FundWithdrawal)->firstOrFail();
    approveRemainingSteps($fund);
    $outputs['07 Fund Withdrawal'] = ['07. Student Organization Fund Withdrawal and Transfer Form template.docx', app(DocumentExporter::class)->export($fund->fresh(), 'docx')];

    // 13 Minutes.
    $meeting = app(Meetings::class)->create($club, $secretary, ['kind' => 'committee', 'title' => 'Weekly committee meeting', 'starts_at' => now()->addDay()->setTime(20, 0)->toDateTimeString(),
        'ends_at' => now()->addDay()->setTime(21, 30)->toDateTimeString(), 'venue' => 'Student Lounge', 'online' => false]);
    app(ApprovalEngine::class)->submit($meeting->minutes, $secretary, ['purpose' => 'Plan the beach cleanup', 'summary' => ['Attendance was taken.', 'Budget & tasks discussed.', 'Poster design agreed.'],
        'actions' => [['task' => 'Design poster', 'pic' => 'Wei Zhang', 'deadline' => '10 Nov 2026'], ['task' => 'Book bus', 'pic' => 'Treasurer', 'deadline' => 'N/A']],
        'next_date' => '2026-11-12', 'next_time' => '20:00', 'next_venue' => 'Student Lounge', 'notes' => ''], signaturePng(), signatureStrokes());
    approveRemainingSteps($meeting->minutes);
    $outputs['13 Meeting Minutes'] = ['13. Meeting Minutes Template.docx', app(DocumentExporter::class)->export($meeting->minutes->fresh(), 'docx')];

    // 02 Finance Record.
    $ledger = app(Ledger::class);
    $ledger->setOpeningBalance($club, 120000, CarbonImmutable::parse('2026-07-01'), 'Carried forward', $this->sld);
    $ledger->income($club, today(), 'Membership fee (online) – Student A', 3000);
    $ledger->income($club, today(), 'Membership fee (cash) – Student B', 3000);
    $outputs['02 Finance Record'] = ['02. Finance Record.xlsx', app(FinanceRecordExport::class)->render($club->fresh())];

    // 03 Attendance and 09 Post mortem, at and after the event.
    $this->travelTo($event->fresh()->starts_at);
    $attendance = app(AttendanceService::class);
    $attendance->addManually($event->fresh(), $student, $f['president']);
    $attendance->addManually($event->fresh(), $crew, $f['president']);
    $outputs['03 Attendance List'] = ['03. Event Attendance List Template.xlsx', app(AttendanceListExport::class)->render($event->fresh())];
    $this->travelTo($event->fresh()->ends_at->addDay());
    $rows = array_fill_keys(['A', 'B', 'C', 'D', 'E', 'F', 'G'], ['well' => 'Went smoothly', 'challenges' => 'N/A', 'improvement' => 'N/A']);
    $rows['C'] = ['well' => 'QR scan was fast', 'challenges' => 'Queue at 8am', 'improvement' => 'Open two scan points'];
    $this->actingAs($f['president'])->post("/events/{$event->slug}/post-mortem/submit", [
        'rows' => $rows, 'recommendations' => ['Start registration earlier', 'Add a second water station', 'Invite a marine biologist'],
        'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
    ])->assertSessionHas('success');
    $postMortem = Document::where('type', DocumentType::PostMortem)->firstOrFail();
    approveRemainingSteps($postMortem);
    $outputs['09 Post Mortem'] = ['09. Event Post Mortem Template.xlsx', app(DocumentExporter::class)->export($postMortem->fresh(), 'xlsx')];

    // 10 Full member list.
    $members = plainMembers($this, 4);
    $outputs['10 Full Member List'] = ['10. Full Member List.xlsx', app(MemberListExport::class)->render($club, app(MembersQuery::class)->rows([$club->id]))];

    // 01 AGM Report and 12 Annual Activities Report.
    $agm = openPeriodAndAgm($this);
    $this->travelTo($agm->starts_at->addMinutes(10));
    foreach ([$secretary, $f['president'], $members[0], $members[1]] as $u) {
        app(Agms::class)->attend($agm, $u, 'manual');
    }
    app(ApprovalEngine::class)->submit($agm->report, $secretary, [
        'objectives' => ['To review the activities of the session.', 'To present the financial report.', 'To elect the new committee.'],
        'speech' => ['The President welcomed all members.', 'Highlights of the Beach Cleanup were shared.'],
        'agenda' => [['start' => '4.00PM', 'end' => '4.10PM', 'item' => 'Members arrival & taking attendance'], ['start' => '4.10PM', 'end' => '4.20PM', 'item' => 'Opening speech by the President'], ['start' => '4.20PM', 'end' => '5.30PM', 'item' => 'Election of the new committee']],
        'adjournment' => 'The meeting was adjourned at 6.00 PM.', 'computed' => app(AgmReportForm::class)->computed($agm->fresh()),
    ], signaturePng(), signatureStrokes());
    approveRemainingSteps($agm->report);
    $outputs['01 AGM Report'] = ['01. AGM Template.docx', app(AgmReportDocx::class)->render($agm->report->fresh())];
    $annual = app(AnnualReports::class);
    $file = $annual->for($club, $agm->session, $secretary);
    $annual->saveDetails($file, [$event->id => 'Members cleaned 2 km of Pantai Remis beach and learned about marine litter.'], null);
    $annual->generate($file->fresh());
    $outputs['12 Annual Activities Report'] = ['12. Annual Activities Report.pptx', $file->fresh()->pptx_path];

    // Save filled + original PDFs side by side, and compare page counts.
    $pages = fn (string $path) => preg_match_all('#/Type\s*/Page[^s]#', (string) file_get_contents($path));
    $report = ["# Template fidelity check\n", 'Generated '.now()->toDayDateTimeString().". Filled and original PDFs are side by side in this folder.\n",
        '| Template | Original pages | Filled pages | Placeholders left |', '| --- | --- | --- | --- |'];
    foreach ($outputs as $label => [$template, $native]) {
        $slug = str($label)->slug();
        $original = resource_path("templates/original/{$template}");
        copy($original, "{$qa}/{$slug}-original.".pathinfo($template, PATHINFO_EXTENSION));
        $originalPdf = str_ends_with($template, '.pdf') ? "{$qa}/{$slug}-original.pdf" : $pdf->convert("{$qa}/{$slug}-original.".pathinfo($template, PATHINFO_EXTENSION));
        $filledNative = "{$qa}/{$slug}-filled.".pathinfo($native, PATHINFO_EXTENSION);
        copy($native, $filledNative);
        $filledPdf = str_ends_with($native, '.pdf') ? $filledNative : $pdf->convert($filledNative);
        $left = str_ends_with($native, '.docx') ? DocxRenderer::remainingPlaceholders($native) : [];
        expect($left)->toBe([], "{$label} still has placeholders");
        $report[] = "| {$label} | {$pages($originalPdf)} | {$pages($filledPdf)} | ".($left ? implode(', ', $left) : 'none').' |';
    }
    file_put_contents("{$qa}/REPORT.md", implode("\n", $report)."\n");
    expect(count($outputs))->toBe(10);
})->skip(fn () => ! env('QA_TEMPLATES') || ! is_executable((string) config('sld.soffice')) || (new Process([config('sld.python'), '-c', 'import pptx']))->run() !== 0, 'Set QA_TEMPLATES=1 (needs LibreOffice and python-pptx)');
