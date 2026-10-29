<?php

use App\Enums\DocumentStatus;
use App\Models\CcaRecord;
use App\Models\Student;
use App\Models\User;
use App\Services\Documents\AttendanceListExport;
use App\Services\Documents\XlsxRenderer;
use App\Services\Events\AttendanceQr;
use App\Services\Events\AttendanceService;
use App\Services\Events\IndemnityService;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->event = $this->f['event'];
    $this->event->update(['status' => DocumentStatus::Approved, 'attendance_open' => true, 'registration_open' => true, 'cca_participant' => 4.5, 'cca_committee' => 6]);
    $this->student = User::whereHas('student', fn ($q) => $q->where('student_number', 'STD20240010'))->first()
        ?? Student::where('student_number', 'STD20240010')->first()->ensureUser();
    $this->event->registrations()->create(['user_id' => $this->student->id]);
});

function indemnityData(): array
{
    return [
        'emergency_name' => 'Mother', 'emergency_mobile_phone' => '012-3456789', 'emergency_relationship' => 'Mother',
        'address' => '12 Jalan Test, Ipoh', 'consent' => '1', 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
    ];
}

it('accepts the rotating QR for the current and previous minute only', function () {
    $qr = app(AttendanceQr::class);
    $this->travelTo($this->event->starts_at);
    $code = $qr->code($this->event, 'in');

    expect($qr->isValid($this->event, 'in', $code))->toBeTrue()
        ->and($qr->isValid($this->event, 'out', $code))->toBeFalse();

    $this->travel(61)->seconds();
    expect($qr->isValid($this->event, 'in', $code))->toBeTrue();
    $this->travel(60)->seconds();
    expect($qr->isValid($this->event, 'in', $code))->toBeFalse();
});

it('clocks a student in by scanning the event QR and records CCA on clock-out', function () {
    $this->travelTo($this->event->starts_at);
    $qr = app(AttendanceQr::class);

    $this->actingAs($this->student)->get("/events/{$this->event->slug}/attend?action=in&code=".$qr->code($this->event, 'in'))->assertSessionHas('success');
    $this->actingAs($this->student)->get("/events/{$this->event->slug}/attend?action=out&code=".$qr->code($this->event, 'out'))->assertSessionHas('success');

    expect(CcaRecord::where('user_id', $this->student->id)->value('hours'))->toBe(4.5);
});

it('gives committee members the committee CCA', function () {
    $this->travelTo($this->event->starts_at);
    $member = $this->event->committee()->first()->user;
    app(AttendanceService::class)->addManually($this->event, $member, $this->f['president']);

    expect(CcaRecord::where('user_id', $member->id)->value('hours'))->toBe(6.0)
        ->and(CcaRecord::where('user_id', $member->id)->value('role'))->toBe('committee');
});

it('rejects expired codes and attendance outside the event window', function () {
    $this->travelTo($this->event->starts_at);
    $this->actingAs($this->student)->get("/events/{$this->event->slug}/attend?action=in&code=wrong")->assertSessionHas('error', fn ($m) => str_contains($m, 'expired'));

    $this->travelTo($this->event->starts_at->copy()->subHours(3));
    expect(fn () => app(AttendanceService::class)->clock($this->event, $this->student, 'in', 'qr'))->toThrow(DomainException::class, 'open from');
});

it('blocks clock-in at outdoor events until the indemnity is signed', function () {
    $this->event->update(['is_outdoor' => true]);

    $this->travelTo($this->event->starts_at->copy()->subDays(10));
    app(IndemnityService::class);
    $this->travelTo($this->event->starts_at);
    expect(fn () => app(AttendanceService::class)->clock($this->event, $this->student, 'in', 'qr'))->toThrow(DomainException::class, 'indemnity');
});

it('lets a registered student sign the indemnity, saves the emergency contact and encrypts personal data', function () {
    $this->event->update(['is_outdoor' => true]);
    $this->travelTo($this->event->starts_at->copy()->subDays(10));

    $this->actingAs($this->student)->post("/events/{$this->event->slug}/indemnity", indemnityData())->assertSessionHas('success');

    $raw = DB::table('indemnities')->first();
    expect($raw->address)->not->toContain('Jalan Test')
        ->and($this->student->emergencyContact->mobile_phone)->toBe('012-3456789');

    $this->travelTo($this->event->starts_at);
    app(AttendanceService::class)->clock($this->event, $this->student, 'in', 'qr');
    expect($this->event->attendance()->count())->toBe(1);
});

it('closes indemnity signing 3 days before the event', function () {
    $this->event->update(['is_outdoor' => true]);
    $this->travelTo($this->event->starts_at->copy()->subDays(2));

    $this->actingAs($this->student)->post("/events/{$this->event->slug}/indemnity", indemnityData())->assertSessionHas('error', fn ($m) => str_contains($m, '3 days'));
});

it('shows the signed/not-signed list to organisers only', function () {
    $this->event->update(['is_outdoor' => true]);

    $this->actingAs($this->student)->get("/events/{$this->event->slug}/indemnities")->assertForbidden();
    $this->actingAs($this->f['president'])->get("/events/{$this->event->slug}/indemnities")->assertOk();
});

it('exports the attendance list into the official 03 template sheets', function () {
    $this->travelTo($this->event->starts_at);
    $service = app(AttendanceService::class);
    $service->addManually($this->event, $this->student, $this->f['president']);
    $service->addManually($this->event, $this->event->committee()->first()->user, $this->f['president']);

    $path = app(AttendanceListExport::class)->render($this->event->fresh());
    $book = IOFactory::load($path);
    $participants = $book->getSheetByName('Participant');
    $committee = $book->getSheetByName('Committee');

    expect($book->getSheetNames())->toBe(['Participant', 'Committee', 'Pictures'])
        ->and($participants->getCell('A1')->getValue())->toBe('Event Name: ')
        ->and($participants->getCell('B1')->getValue())->toBe('Beach Cleanup')
        ->and($participants->getCell('B6')->getValue())->toBe($this->student->name)
        ->and($participants->getCell('A19')->getValue())->toBe('Prepared by:')
        ->and($committee->getCell('E3')->getValue())->toBe(6.0)
        ->and($participants->getCell('B23')->getValue())->toBe($this->event->committee()->where('position', 'Secretary')->first()->user->name);
    unlink($path);
});

it('keeps the signature block intact when there are more participants than blank rows', function () {
    $this->travelTo($this->event->starts_at);
    $sheet = IOFactory::load(resource_path('templates/original/03. Event Attendance List Template.xlsx'))->getSheetByName('Participant');
    $rows = array_map(fn ($i) => [$i, "Student {$i}", "s{$i}@qiu.edu.my"], range(1, 20));

    app(XlsxRenderer::class)->fillRows($sheet, 6, 19, $rows);

    expect($sheet->getCell('B25')->getValue())->toBe('Student 20')
        ->and($sheet->getCell('A26')->getValue())->toBe('Prepared by:');
});
