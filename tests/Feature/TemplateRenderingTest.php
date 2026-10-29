<?php

use App\Models\Staff;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Documents\DocxRenderer;
use App\Services\Documents\ProposalDocx;
use App\Services\Events\ProposalForm;

/** Golden-file checks: every placeholder replaced, table rows match the data, original never touched. */
beforeEach(fn () => $this->f = proposalFixture());

function proposalPayload(array $f): array
{
    return array_replace(app(ProposalForm::class)->defaults($f['event']), [
        'summary' => 'Clean the beach', 'venue' => 'Pantai Remis', 'introduction' => 'SDG 14', 'eligibility' => 'All',
        'nature' => ['environmental', 'humanity'], 'sdgs' => [14, 13], 'source_of_fund' => ['club'],
        'objectives' => ['Clean', 'Learn', 'Bond'],
        'activities' => [['name' => 'Cleanup', 'description' => 'Pick litter'], ['name' => 'Talk', 'description' => 'Marine life']],
        'itinerary' => [['start' => '08:00', 'end' => '08:30', 'agenda' => 'Arrival'], ['start' => '08:30', 'end' => '12:00', 'agenda' => 'Cleanup'], ['start' => '12:00', 'end' => '12:30', 'agenda' => 'Lunch']],
        'budget' => ['revenue' => [['description' => 'Club fund', 'price_sen' => 50000, 'quantity' => 1]], 'expenses' => [['description' => 'Gloves', 'price_sen' => 500, 'quantity' => 40], ['description' => 'Bags', 'price_sen' => 200, 'quantity' => 50]]],
        'conclusion' => 'Done',
    ]);
}

function docxText(string $path): string
{
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    return strip_tags(str_replace('</w:p>', "\n", $xml));
}

it('fills every placeholder in the proposal template', function () {
    $data = proposalPayload($this->f);
    app(ApprovalEngine::class)->submit($this->f['document'], $this->f['president'], $data, signaturePng());
    approveRemainingSteps($this->f['document']);

    $path = app(ProposalDocx::class)->render($this->f['document']->fresh());
    $text = docxText($path);

    expect(DocxRenderer::remainingPlaceholders($path))->toBe([])
        ->and($text)->toContain('BEACH CLEANUP PROPOSAL')
        ->toContain('Mr Nicholas Goh, COO')
        ->toContain('Pantai Remis')
        ->toContain('Marine life')
        ->toContain('Profit / Loss')
        ->toContain('300.00')            // 500 - 200 = RM 300 profit
        ->toContain('Verify ')
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
