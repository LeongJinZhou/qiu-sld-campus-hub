<?php

use App\Models\Staff;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Documents\DocxRenderer;
use App\Services\Documents\PdfConverter;
use App\Services\Documents\ProposalDocx;
use App\Services\Events\ProposalForm;
use App\Services\Uploads\ImageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Golden-file checks: every placeholder replaced, table rows match the data, original never touched. */
beforeEach(fn () => $this->f = proposalFixture());

function proposalPayload(array $f): array
{
    return array_replace(app(ProposalForm::class)->defaults($f['event']), [
        'summary' => 'Clean the beach', 'venue' => 'Pantai Remis', 'introduction' => 'SDG 14', 'eligibility' => 'All',
        'nature' => ['environmental', 'humanity'], 'sdgs' => [14, 13], 'source_of_fund' => ['club'],
        'objectives' => ['Clean & green', 'Learn', 'Bond'],
        'activities' => [['name' => 'Cleanup', 'description' => 'Pick litter'], ['name' => 'Talk', 'description' => 'Marine life']],
        'itinerary' => [['start' => '08:00', 'end' => '08:30', 'agenda' => 'Arrival & <Registration>'], ['start' => '08:30', 'end' => '12:00', 'agenda' => 'Cleanup'], ['start' => '12:00', 'end' => '12:30', 'agenda' => 'Lunch']],
        'budget' => ['revenue' => [['description' => 'Club fund', 'price_sen' => 50000, 'quantity' => 1]], 'expenses' => [['description' => 'Gloves', 'price_sen' => 500, 'quantity' => 40], ['description' => 'Bags', 'price_sen' => 200, 'quantity' => 50]]],
        'conclusion' => 'Done',
    ]);
}

it('fills every placeholder in the proposal template', function () {
    $data = proposalPayload($this->f);
    app(ApprovalEngine::class)->submit($this->f['document'], $this->f['president'], $data, signaturePng());
    approveRemainingSteps($this->f['document']);

    $path = app(ProposalDocx::class)->render($this->f['document']->fresh());
    $text = docxText($path);

    $zip = new ZipArchive;
    $zip->open($path);
    $wellFormed = (new DOMDocument)->loadXML((string) $zip->getFromName('word/document.xml'));
    $zip->close();

    expect($wellFormed)->toBeTrue()
        ->and(DocxRenderer::remainingPlaceholders($path))->toBe([])
        ->and($text)->toContain('BEACH CLEANUP PROPOSAL')
        ->toContain('Mr Nicholas Goh, COO')
        ->toContain('Pantai Remis')
        ->toContain('Marine life')
        ->toContain('Profit / Loss')
        ->toContain('300.00')            // 500 - 200 = RM 300 profit
        ->not->toContain('Verify ')
        ->and(substr_count($text, 'Gloves') + substr_count($text, 'Bags'))->toBe(2)
        ->and(substr_count($text, 'Arrival') + substr_count($text, 'Lunch'))->toBe(2);
    unlink($path);
});

it('prints approver names from the staff table, not from the template', function () {
    Staff::where('approver_role', 'coo')->update(['name' => 'Dr New COO']);
    app(ApprovalEngine::class)->submit($this->f['document'], $this->f['president'], proposalPayload($this->f), signaturePng());

    $path = app(ProposalDocx::class)->render($this->f['document']->fresh());
    expect(docxText($path))->toContain('Dr New COO')->not->toContain('Nicholas Goh');
    unlink($path);
});

it('never modifies the original templates', function () {
    $before = md5_file(resource_path('templates/original/'.ProposalDocx::TEMPLATE));
    app(ApprovalEngine::class)->submit($this->f['document'], $this->f['president'], proposalPayload($this->f), signaturePng());
    unlink(app(ProposalDocx::class)->render($this->f['document']->fresh()));

    expect(md5_file(resource_path('templates/original/'.ProposalDocx::TEMPLATE)))->toBe($before);
});

it('converts the filled proposal to PDF with LibreOffice', function () {
    app(ApprovalEngine::class)->submit($this->f['document'], $this->f['president'], proposalPayload($this->f), signaturePng());
    $docx = app(ProposalDocx::class)->render($this->f['document']->fresh());

    $pdf = app(PdfConverter::class)->convert($docx);

    expect(file_get_contents($pdf, length: 5))->toBe('%PDF-');
    @unlink($docx);
    unlink($pdf);
})->skip(fn () => ! is_executable((string) config('sld.soffice')), 'LibreOffice not installed');

it('sets every PDF export in a sans-serif font at 12 pt', function () {
    $data = completeProposal($this->f);
    app(ApprovalEngine::class)->submit($this->f['document'], $this->f['president'], $data, signaturePng());
    $path = app(ProposalDocx::class)->render($this->f['document']->fresh());

    app(PdfConverter::class)->normaliseFonts($path);

    $zip = new ZipArchive;
    $zip->open($path);
    $xml = $zip->getFromName('word/document.xml').$zip->getFromName('word/styles.xml');
    $zip->close();
    preg_match_all('/w:ascii="([^"]+)"/', $xml, $fonts);
    preg_match_all('/<w:sz w:val="(\d+)"/', $xml, $sizes);
    expect(array_unique($fonts[1]))->toBe(['Arial'])->and(array_values(array_unique($sizes[1])))->toBe(['24']);
    unlink($path);
});

it('prints speakers with photos, every trip, the committee chart and the club\'s own parts in the proposal', function () {
    Staff::where('email', 'advisor1.demo@qiu.edu.my')->update(['staff_number' => 'E1234']);
    Storage::fake('public');
    $picture = fn (int $w, int $h) => app(ImageUpload::class)->store(UploadedFile::fake()->image('p.jpg', $w, $h), "proposal-images/{$this->f['event']->id}");
    $photo = $picture(400, 500);
    $wide = $picture(2000, 600);
    $day = $this->f['event']->starts_at->format('Y-m-d');
    $data = array_replace(proposalPayload($this->f), [
        'nature' => ['seminar'],
        'collaboration' => ['Robotics Club', 'Penang Green Society'],
        'ict_required' => false, 'ict_requirements' => 'Projector (unticked, so not printed)',
        'speakers' => [['name' => 'Dr Aida Rahman', 'phone' => '012-3456789', 'designation' => 'Data Scientist, Petronas', 'linkedin' => '', 'photo' => $photo]],
        'transport_required' => true,
        'trips' => [
            ['arrangement' => 'University bus', 'departure_at' => "{$day}T08:00", 'departure_pickup' => 'Main gate',
                'stops' => [['name' => 'Penang Hill', 'at' => "{$day}T08:40", 'point' => 'Lower station'], ['name' => 'Pantai Remis', 'at' => "{$day}T09:30", 'point' => 'Car park']],
                'return_at' => "{$day}T12:30", 'return_pickup' => 'Pantai Remis car park'],
            ['arrangement' => 'Van', 'departure_at' => "{$day}T09:00", 'departure_pickup' => 'Hostel', 'stops' => [['name' => 'Jetty', 'at' => "{$day}T09:30", 'point' => 'Pier 2']], 'return_at' => "{$day}T12:30", 'return_pickup' => 'Jetty'],
        ],
        'extra_sections' => [
            ['kind' => 'heading', 'text' => 'Risk Assessment', 'rows' => [], 'image' => ''],
            ['kind' => 'subheading', 'text' => 'Weather', 'rows' => [], 'image' => ''],
            ['kind' => 'paragraph', 'text' => 'We check the forecast the day before.', 'rows' => [], 'image' => ''],
            ['kind' => 'bullets', 'text' => "Sunscreen\nWater", 'rows' => [], 'image' => ''],
            ['kind' => 'image', 'text' => 'Site map of the beach', 'rows' => [], 'image' => $wide],
            ['kind' => 'table', 'text' => '', 'rows' => [['Risk', 'Action'], ['Rain', 'Move indoors']], 'image' => ''],
        ],
    ]);
    app(ApprovalEngine::class)->submit($this->f['document'], $this->f['president'], $data, signaturePng());

    $path = app(ProposalDocx::class)->render($this->f['document']->fresh());
    $text = docxText($path);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $media = collect(range(0, $zip->numFiles - 1))->map(fn (int $i) => $zip->getNameIndex($i))->filter(fn (string $n) => str_starts_with($n, 'word/media/'))->count();
    $zip->close();

    expect(DocxRenderer::remainingPlaceholders($path))->toBe([])
        ->and($text)->toContain('Speaker / External Profile')->toContain('Dr Aida Rahman')->toContain('Data Scientist, Petronas')
        ->toContain('Robotics Club, Penang Green Society')->not->toContain('Projector')
        ->toContain('Main gate')->toContain('Penang Hill')->toContain('Lower station')->toContain('Drop-off: Pantai Remis')->toContain('Pantai Remis car park')->toContain('Jetty')
        ->toContain('Event Committee Chart')->toContain('Club Advisor')->toContain('Employee ID: E1234')->toContain('Student ID: ')
        ->toContain('Risk Assessment')->toContain('Weather')->toContain('forecast')->toContain('Sunscreen')->toContain('Site map of the beach')->toContain('Move indoors')
        // Both pictures are embedded (plus the preparer's signature); the wide one fits 12 cm (340.2 pt) across.
        ->and($media)->toBeGreaterThanOrEqual(3)
        ->and($xml)->toContain('width:340.2pt')->toContain('Not required');
    if (getenv('PROPOSAL_SAMPLE_DIR')) {
        copy($path, getenv('PROPOSAL_SAMPLE_DIR').'/proposal-sample.docx');
    }
    unlink($path);
});

it('leaves out the speakers part when the event is not a workshop', function () {
    app(ApprovalEngine::class)->submit($this->f['document'], $this->f['president'], proposalPayload($this->f), signaturePng());
    $path = app(ProposalDocx::class)->render($this->f['document']->fresh());

    expect(DocxRenderer::remainingPlaceholders($path))->toBe([])->and(docxText($path))->not->toContain('Speaker / External Profile');
    unlink($path);
});
