<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Student;
use App\Models\User;
use App\Services\Ai\PostMortemAdvisor;
use App\Services\Documents\DocumentExporter;
use App\Services\Events\AttendanceService;
use App\Services\Events\PostMortemForm;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->event = $this->f['event'];
    $this->event->update(['status' => DocumentStatus::Approved, 'attendance_open' => true]);
    $this->event->documentItems()->create(['kind' => 'post_mortem', 'due_on' => now()->addWeeks(2)]);
});

function postMortemPayload(int $recommendations = 2): array
{
    $rows = array_fill_keys(array_keys(PostMortemForm::AREAS), ['well' => '', 'challenges' => '', 'improvement' => '']);
    $rows['C'] = ['well' => 'QR scan was fast', 'challenges' => 'Queue at 8am', 'improvement' => 'Open two scan points'];

    return ['rows' => $rows, 'recommendations' => $recommendations ? array_map(fn ($i) => "Recommendation {$i}", range(1, $recommendations)) : []];
}

it('fills the header from records: participants from attendance and the committee with positions', function () {
    $this->travelTo($this->event->starts_at);
    $student = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    app(AttendanceService::class)->addManually($this->event, $student, $this->f['president']);

    $this->actingAs($this->f['president'])->get("/events/{$this->event->slug}/post-mortem")
        ->assertInertia(fn (Assert $p) => $p->component('Events/PostMortem')->where('header.participants', 1)->where('header.club', 'Fixture Club')->has('header.committee', 4));
});

it('submits, ticks the post-event checklist and prints the official 09 sheet with N/A and extra recommendations', function () {
    $this->actingAs($this->f['president'])->post("/events/{$this->event->slug}/post-mortem/submit", [...postMortemPayload(5), 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes()])
        ->assertSessionHas('success');

    $document = Document::where('type', 'post_mortem')->firstOrFail();
    expect($this->event->documentItems()->where('kind', 'post_mortem')->value('submitted_at'))->not->toBeNull();
    approveRemainingSteps($document);

    $path = app(DocumentExporter::class)->export($document->fresh(), 'xlsx');
    $sheet = IOFactory::load($path)->getActiveSheet();

    expect($sheet->getCell('B2')->getValue())->toBe('Beach Cleanup')
        ->and($sheet->getCell('B14')->getValue())->toBe('QR scan was fast')
        ->and($sheet->getCell('D14')->getValue())->toBe('Open two scan points')
        ->and($sheet->getCell('B12')->getValue())->toBe('N/A')
        ->and($sheet->getCell('B25')->getValue())->toBe('Recommendation 5')
        ->and($sheet->getCell('A27')->getValue())->toBe('Prepared by:')
        ->and(count($sheet->getDrawingCollection()))->toBe(2);
    unlink($path);
});

it('lets AI fill only empty improvements and recommendations', function () {
    $this->mock(PostMortemAdvisor::class)->shouldReceive('suggest')->andReturn([
        'improvements' => array_fill_keys(array_keys(PostMortemForm::AREAS), 'AI idea'),
        'recommendations' => ['AI rec'],
    ]);
    $payload = postMortemPayload(0);

    $this->actingAs($this->f['president'])->post("/events/{$this->event->slug}/post-mortem/suggest", $payload)->assertSessionHas('success');

    $data = app(PostMortemForm::class)->current(Document::where('type', 'post_mortem')->firstOrFail());
    expect($data['rows']['C']['improvement'])->toBe('Open two scan points')
        ->and($data['rows']['A']['improvement'])->toBe('AI idea')
        ->and($data['recommendations'])->toBe(['AI rec']);
});

it('is only for organisers of approved events', function () {
    $this->actingAs(User::factory()->create())->get("/events/{$this->event->slug}/post-mortem")->assertForbidden();
    $this->event->update(['status' => DocumentStatus::Draft]);
    $this->actingAs($this->f['president'])->get("/events/{$this->event->slug}/post-mortem")->assertNotFound();
});
