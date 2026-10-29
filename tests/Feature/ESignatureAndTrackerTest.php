<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Signature;
use App\Models\Student;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Approvals\SignatureStore;
use App\Services\Documents\AttendanceListExport;
use App\Services\Documents\DocumentPresenter;
use App\Services\Events\AttendanceService;
use App\Services\Events\ProposalForm;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(fn () => $this->f = proposalFixture());

it('stores the recorded strokes and the input device with the signature', function () {
    $signature = app(SignatureStore::class)->store($this->f['president'], signaturePng(), 'President', [], signatureStrokes('pen'));

    expect($signature->input_device)->toBe('pen')
        ->and($signature->strokes['strokes'][0])->toHaveCount(25)
        ->and($signature->toArray())->not->toHaveKey('strokes');
});

it('rejects empty or malformed signature recordings', function (string $json) {
    app(SignatureStore::class)->store($this->f['president'], signaturePng(), 'President', [], $json);
})->with([
    'too few points' => json_encode(['w' => 1, 'h' => 1, 'device' => 'mouse', 'strokes' => [[[1, 1, 0, 0.5]]]]),
    'unknown device' => json_encode(['w' => 1, 'h' => 1, 'device' => 'robot', 'strokes' => [[[1, 1, 0, 0.5]]]]),
    'bad point' => json_encode(['w' => 1, 'h' => 1, 'device' => 'touch', 'strokes' => [[['x', 1, 0, 0.5]]]]),
    'not json' => 'nope',
])->throws(DomainException::class);

it('requires a drawn signature recording to submit a proposal over HTTP', function () {
    $data = app(ProposalForm::class)->defaults($this->f['event']);

    $this->actingAs($this->f['president'])
        ->post("/events/{$this->f['event']->slug}/proposal/submit", [...$data, 'signature' => signaturePng()])
        ->assertSessionHasErrors('signature_strokes');
});

it('reports where the proposal is and whose approval it waits for', function () {
    app(ApprovalEngine::class)->submit($this->f['document'], $this->f['president'], ['budget' => [], 'starts_at' => 'a', 'ends_at' => 'b'], signaturePng(), signatureStrokes());

    $progress = app(DocumentPresenter::class)->progress($this->f['document']->fresh());

    expect($progress)->toMatchArray([
        'done' => 1,
        'total' => 7,
        'status' => 'in_review',
        'current_role' => 'Advisor',
        'waiting_on' => ['Dr Lim Siew Ling'],
    ])->and($progress['due_at'])->not->toBeNull();
});

it('signs off the attendance list and prints both signatures on the 03 template', function () {
    $event = $this->f['event'];
    $event->update(['status' => DocumentStatus::Approved, 'attendance_open' => true]);
    $this->travelTo($event->starts_at);
    $student = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    app(AttendanceService::class)->addManually($event, $student, $this->f['president']);

    $this->actingAs($this->f['president'])
        ->post("/events/{$event->slug}/attendance/submit", ['signature' => signaturePng(), 'signature_strokes' => signatureStrokes('mouse')])
        ->assertSessionHas('success');
    $document = Document::where('event_id', $event->id)->where('type', DocumentType::AttendanceList)->firstOrFail();
    $engine = app(ApprovalEngine::class);
    $engine->approve($engine->currentStep($document->fresh()), $this->f['advisor'], signaturePng(), null, signatureStrokes());

    $path = app(AttendanceListExport::class)->render($event->fresh());
    $sheet = IOFactory::load($path)->getSheetByName('Participant');
    $codes = Signature::where('document_id', $document->id)->pluck('verification_code')->all();

    expect(count($sheet->getDrawingCollection()))->toBe(2)
        ->and($sheet->getCell('B23')->getValue())->toBe($this->f['president']->name)
        ->and($sheet->getCell('B25')->getValue())->toContain($codes[0])
        ->and($sheet->getCell('B31')->getValue())->toBe('Dr Lim Siew Ling');
    unlink($path);
});

it('downloads a signed attendance list as PDF in the official layout', function () {
    $event = $this->f['event'];
    $event->update(['status' => DocumentStatus::Approved, 'attendance_open' => true]);
    $this->actingAs($this->f['president'])->post("/events/{$event->slug}/attendance/submit", ['signature' => signaturePng(), 'signature_strokes' => signatureStrokes()]);
    $document = Document::where('type', DocumentType::AttendanceList)->firstOrFail();

    $response = $this->actingAs($this->f['president'])->get("/documents/{$document->id}/download/pdf");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
})->skip(fn () => ! is_executable((string) config('sld.soffice')), 'LibreOffice not installed');
