<?php

use App\Enums\ChainRole;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Payee;
use App\Models\User;
use App\Services\Documents\DocumentExporter;
use App\Services\Documents\DocxRenderer;
use App\Services\Documents\FinanceRecordExport;
use App\Services\Finance\FundWithdrawals;
use App\Services\Finance\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->ledger = app(Ledger::class);
    $this->treasurer = $this->club->roleAssignments()->where('position', 'Treasurer')->first()->user;
    $this->secretary = $this->club->roleAssignments()->where('position', 'Secretary')->first()->user;
});

it('locks the ledger until SLD enters the opening balance', function () {
    expect(fn () => $this->ledger->manual($this->club, today(), 'Snacks', 0, 500, UploadedFile::fake()->image('r.jpg'), $this->treasurer))
        ->toThrow(DomainException::class, 'opening balance');

    $this->actingAs($this->treasurer)->post("/clubs/{$this->club->slug}/finance/opening", ['amount_rm' => 100, 'as_of' => '2026-07-01'])->assertForbidden();
    $this->actingAs(sldOfficer())->post("/clubs/{$this->club->slug}/finance/opening", ['amount_rm' => 100, 'as_of' => '2026-07-01'])->assertSessionHas('success');

    $this->assertDatabaseHas('audit_log', ['action' => 'finance.opening_balance']);
});

it('keeps a running balance from the opening line and requires original receipts for expenses', function () {
    Storage::fake('local');
    $this->ledger->setOpeningBalance($this->club, 10000, CarbonImmutable::parse('2026-07-01'), null, sldOfficer());
    $this->ledger->income($this->club, today(), 'Membership fee (online) – A', 3000);

    $this->actingAs($this->treasurer)->post("/clubs/{$this->club->slug}/finance/entries", ['date' => today()->toDateString(), 'description' => 'Banner', 'kind' => 'expense', 'amount_rm' => 25])
        ->assertSessionHasErrors(['receipt', 'original']);
    $this->actingAs($this->treasurer)->post("/clubs/{$this->club->slug}/finance/entries", [
        'date' => today()->toDateString(), 'description' => 'Banner', 'kind' => 'expense', 'amount_rm' => 25, 'receipt' => UploadedFile::fake()->image('r.jpg'), 'original' => '1',
    ])->assertSessionHas('success');

    $rows = $this->ledger->rows($this->club->fresh());
    expect(array_column($rows, 'description'))->toBe(['Opening Acc', 'Membership fee (online) – A', 'Banner'])
        ->and(array_column($rows, 'balance_sen'))->toBe([10000, 13000, 10500]);
});

it('exports the ledger into the official 02 Finance Record with its balance formula', function () {
    $this->ledger->setOpeningBalance($this->club, 10000, CarbonImmutable::parse('2026-07-01'), null, sldOfficer());
    $this->ledger->income($this->club, CarbonImmutable::parse('2026-08-01'), 'April Membership Drive', 37000);

    $path = app(FinanceRecordExport::class)->render($this->club->fresh());
    $sheet = IOFactory::load($path)->getActiveSheet();

    expect($sheet->getCell('A2')->getValue())->toBe('Date')
        ->and($sheet->getCell('A3')->getValue())->toBeNull()
        ->and($sheet->getCell('B4')->getValue())->toBe('Opening Acc')
        ->and($sheet->getCell('B5')->getValue())->toBe('April Membership Drive')
        ->and($sheet->getCell('E5')->getCalculatedValue())->toEqual(470)
        ->and($sheet->getCell('B7')->getValue())->toBeNull()
        ->and($sheet->getCell('B40')->getValue())->toBe($this->treasurer->name);
    unlink($path);
});

it('only allows fund withdrawals for approved events, within the approved budget', function () {
    $funds = app(FundWithdrawals::class);
    $payee = Payee::create(['club_id' => $this->club->id, 'kind' => 'vendor', 'name' => 'Kedai', 'bank_name' => 'Maybank', 'account_holder' => 'Kedai', 'account_number' => '514012345678']);
    $input = ['event_id' => $this->f['event']->id, 'purpose' => 'x', 'items' => [['description' => 'Gloves', 'unit_sen' => 500, 'quantity' => 40, 'method' => 'vendor']], 'payees' => [['payee_id' => $payee->id, 'amount_sen' => 20000]]];

    expect(fn () => $funds->normalise($this->club, $input))->toThrow(DomainException::class, 'approved');

    approvedEventWithBudget($this);
    expect(fn () => $funds->normalise($this->club, [...$input, 'items' => [['description' => 'Gloves', 'unit_sen' => 500, 'quantity' => 41, 'method' => 'vendor']]]))->toThrow(DomainException::class, 'more than')
        ->and(fn () => $funds->normalise($this->club, [...$input, 'items' => [['description' => 'Fireworks', 'unit_sen' => 100, 'quantity' => 1, 'method' => 'vendor']]]))->toThrow(DomainException::class, 'not in the approved budget')
        ->and($funds->normalise($this->club, $input)['total_sen'])->toBe(20000);
});

it('needs weekly-activity withdrawals 2 weeks ahead and payees matching the transfers', function () {
    $funds = app(FundWithdrawals::class);
    $payee = Payee::create(['club_id' => $this->club->id, 'kind' => 'vendor', 'name' => 'Kedai ABC', 'bank_name' => 'Maybank', 'account_holder' => 'ABC Sdn Bhd', 'account_number' => '514012345678']);
    $base = ['purpose' => 'weekly training', 'items' => [['description' => 'Shuttlecocks', 'unit_sen' => 2000, 'quantity' => 4, 'method' => 'vendor']], 'payees' => [['payee_id' => $payee->id, 'amount_sen' => 8000]]];

    expect(fn () => $funds->normalise($this->club, [...$base, 'needed_by' => today()->addDays(5)->toDateString()]))->toThrow(DomainException::class, '2 weeks')
        ->and(fn () => $funds->normalise($this->club, [...$base, 'needed_by' => today()->addDays(20)->toDateString(), 'payees' => [['payee_id' => $payee->id, 'amount_sen' => 1]]]))->toThrow(DomainException::class, 'add up')
        ->and($funds->normalise($this->club, [...$base, 'needed_by' => today()->addDays(20)->toDateString()])['total_sen'])->toBe(8000);
});

it('encrypts payee bank details at rest', function () {
    $payee = Payee::create(['club_id' => $this->club->id, 'kind' => 'vendor', 'name' => 'Kedai ABC', 'bank_name' => 'Maybank', 'account_holder' => 'ABC Sdn Bhd', 'account_number' => '514012345678']);

    expect(DB::table('payees')->value('account_number'))->not->toContain('514012345678')
        ->and($payee->maskedAccount())->toBe('•••• 5678')
        ->and($payee->toArray())->not->toHaveKey('account_number');
});

it('runs a fund withdrawal through Preparer → President → Advisor → AR → Bursar → COO and prints the 07 form', function () {
    approvedEventWithBudget($this);
    $payee = Payee::create(['club_id' => $this->club->id, 'kind' => 'treasurer', 'name' => $this->treasurer->name, 'bank_name' => 'CIMB', 'account_holder' => $this->treasurer->name, 'account_number' => '7001234567']);

    $this->actingAs($this->secretary)->post("/clubs/{$this->club->slug}/fund-requests", [
        'event_id' => $this->f['event']->id, 'purpose' => 'x',
        'items' => [['description' => 'Gloves', 'unit_sen' => 500, 'quantity' => 40, 'method' => 'vendor'], ['description' => 'Refreshments & water', 'unit_sen' => 1000, 'quantity' => 30, 'method' => 'self']],
        'payees' => [['payee_id' => $payee->id, 'amount_sen' => 50000]],
        'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
    ])->assertRedirect();

    $document = Document::where('type', DocumentType::FundWithdrawal)->firstOrFail();
    expect($document->approvals()->pluck('role')->all())->toBe([ChainRole::Preparer, ChainRole::President, ChainRole::Advisor, ChainRole::AssistantRegistrar, ChainRole::Bursar, ChainRole::Coo]);
    approveRemainingSteps($document);
    expect($document->fresh()->status)->toBe(DocumentStatus::Approved);

    $path = app(DocumentExporter::class)->export($document->fresh(), 'docx');
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    expect(DocxRenderer::remainingPlaceholders($path))->toBe([])
        ->and($xml)->toContain('withdrawal of RM500.00')
        ->toContain('Refreshments &amp; water')
        ->toContain('Student self purchase')
        ->not->toContain('100 Plus')
        ->toContain('7001234567')
        ->toContain('Mr Nicholas Goh')
        ->not->toContain('GPS Club')
        ->and((new DOMDocument)->loadXML($xml))->toBeTrue();
    unlink($path);
});

it('lets any committee member prepare a fund withdrawal, signed with their own position, but not outsiders', function () {
    approvedEventWithBudget($this);
    $payee = Payee::create(['club_id' => $this->club->id, 'kind' => 'vendor', 'name' => 'K', 'bank_name' => 'B', 'account_holder' => 'K', 'account_number' => '1234567']);
    $request = fn () => [
        'event_id' => $this->f['event']->id, 'purpose' => 'x', 'items' => [['description' => 'Gloves', 'unit_sen' => 500, 'quantity' => 1, 'method' => 'vendor']],
        'payees' => [['payee_id' => $payee->id, 'amount_sen' => 500]], 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
    ];

    $this->actingAs(User::factory()->create())->post("/clubs/{$this->club->slug}/fund-requests", $request())->assertForbidden();
    $this->actingAs($this->treasurer)->post("/clubs/{$this->club->slug}/fund-requests", $request())->assertRedirect();

    $first = Document::where('type', DocumentType::FundWithdrawal)->sole()->approvals()->where('step_no', 1)->with('signature')->first();
    expect($first->approver_user_id)->toBe($this->treasurer->id)->and($first->signature->position_at_time)->toBe('Treasurer');
});
