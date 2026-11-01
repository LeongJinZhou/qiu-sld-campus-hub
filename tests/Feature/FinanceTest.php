<?php

use App\Enums\ChainRole;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Services\Approvals\ApproverResolver;
use App\Services\Documents\DocumentExporter;
use App\Services\Documents\DocxRenderer;
use App\Services\Documents\FinanceRecordExport;
use App\Services\Finance\FundWithdrawals;
use App\Services\Finance\Ledger;
use App\Services\Notifications\ActionItems;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
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
    $bank = ['bank_name' => 'Maybank', 'account_name' => 'Kedai', 'account_number' => '514012345678'];
    $input = ['scope' => 'event', 'event_id' => $this->f['event']->id, 'items' => [['description' => 'Gloves', 'unit_sen' => 500, 'quantity' => 40, 'method' => 'vendor']], 'bank' => $bank];

    expect(fn () => $funds->normalise($this->club, $input))->toThrow(DomainException::class, 'approved');

    approvedEventWithBudget($this);
    expect(fn () => $funds->normalise($this->club, [...$input, 'items' => [['description' => 'Gloves', 'unit_sen' => 500, 'quantity' => 41, 'method' => 'vendor']]]))->toThrow(DomainException::class, 'more than')
        ->and(fn () => $funds->normalise($this->club, [...$input, 'items' => [['description' => 'Fireworks', 'unit_sen' => 100, 'quantity' => 1, 'method' => 'vendor']]]))->toThrow(DomainException::class, 'not in the approved budget')
        ->and(fn () => $funds->normalise($this->club, [...$input, 'bank' => [...$bank, 'account_number' => '']]))->toThrow(DomainException::class, 'account number')
        ->and($funds->normalise($this->club, $input))->toMatchArray(['total_sen' => 20000, 'scope' => 'event', 'event_id' => $this->f['event']->id]);
});

it('needs club withdrawals to state a purpose and be 2 weeks ahead', function () {
    $funds = app(FundWithdrawals::class);
    $base = ['scope' => 'club', 'purpose' => 'weekly training', 'items' => [['description' => 'Shuttlecocks', 'unit_sen' => 2000, 'quantity' => 4, 'method' => 'vendor']],
        'bank' => ['bank_name' => 'Maybank', 'account_name' => 'ABC Sdn Bhd', 'account_number' => '514012345678']];

    expect(fn () => $funds->normalise($this->club, [...$base, 'needed_by' => today()->addDays(5)->toDateString()]))->toThrow(DomainException::class, '2 weeks')
        ->and(fn () => $funds->normalise($this->club, [...$base, 'purpose' => ' ', 'needed_by' => today()->addDays(20)->toDateString()]))->toThrow(DomainException::class, 'what the club withdrawal is for')
        ->and($funds->normalise($this->club, [...$base, 'needed_by' => today()->addDays(20)->toDateString()])['total_sen'])->toBe(8000);
});

it('runs an event withdrawal: event Treasurer signs, Advisor, SLD, Finance, bank-in proof, SLD confirms, then the ledger', function () {
    Storage::fake('local');
    approvedEventWithBudget($this);
    $event = $this->f['event'];
    $eventTreasurer = app(ApproverResolver::class)->candidates(new Document(['event_id' => $event->id, 'club_id' => $this->club->id, 'session_id' => $event->session_id, 'type' => DocumentType::FundWithdrawal]), ChainRole::Treasurer)->first();
    $form = fn (array $extra = []) => [
        'scope' => 'event', 'event_id' => $event->id,
        'items' => [['description' => 'Gloves', 'unit_sen' => 500, 'quantity' => 40, 'method' => 'vendor'], ['description' => 'Refreshments & water', 'unit_sen' => 1000, 'quantity' => 30, 'method' => 'self']],
        'bank' => ['bank_name' => 'CIMB', 'account_name' => 'Fixture Club QIU', 'account_number' => '7001-234567'], 'bank_confirmed' => '1', ...$extra,
    ];

    // Bank details must be confirmed; a secretary can fill it in, but only the event Treasurer signs.
    $this->actingAs($this->secretary)->post("/clubs/{$this->club->slug}/fund-requests", [...$form(), 'bank_confirmed' => '0'])->assertSessionHasErrors('bank_confirmed');
    $this->actingAs($this->secretary)->post("/clubs/{$this->club->slug}/fund-requests", $form())->assertRedirect();
    $document = Document::where('type', DocumentType::FundWithdrawal)->sole();
    expect($document->status)->toBe(DocumentStatus::Draft)->and($eventTreasurer->notifications()->count())->toBe(1);

    $this->actingAs($eventTreasurer)->post("/withdrawals/{$document->id}/sign", ['signature' => signaturePng(), 'signature_strokes' => signatureStrokes(), 'bank_confirmed' => '1'])->assertSessionHasNoErrors();
    expect($document->approvals()->orderBy('step_no')->pluck('role')->all())->toBe([ChainRole::Treasurer, ChainRole::Advisor, ChainRole::SldOfficer]);
    approveRemainingSteps($document);
    expect($document->fresh()->finance_stage)->toBe('awaiting_finance')->and(LedgerEntry::where('category', 'withdrawal')->count())->toBe(0);

    $sld = sldOfficer();
    $this->actingAs($sld)->post("/withdrawals/{$document->id}/finance-signed", ['file' => UploadedFile::fake()->create('signed.pdf', 50, 'application/pdf')])->assertSessionHasNoErrors();
    $this->actingAs($this->secretary)->post("/withdrawals/{$document->id}/bank-in", ['file' => UploadedFile::fake()->image('bank.jpg')]);
    expect($document->fresh()->finance_stage)->toBe('awaiting_bankin');
    $this->actingAs($eventTreasurer)->post("/withdrawals/{$document->id}/bank-in", ['file' => UploadedFile::fake()->image('bank.jpg'), 'reference' => 'IBG 778'])->assertSessionHasNoErrors();
    $this->actingAs($sld)->post("/withdrawals/{$document->id}/complete")->assertSessionHasNoErrors();

    $entry = LedgerEntry::where('category', 'withdrawal')->sole();
    expect($document->fresh()->finance_stage)->toBe('completed')
        ->and($entry->only('expense_sen', 'event_id', 'method'))->toBe(['expense_sen' => 50000, 'event_id' => $event->id, 'method' => 'transfer'])
        ->and($entry->receipt_id)->not->toBeNull()->and($entry->order_id)->not->toBeNull();

    $path = app(DocumentExporter::class)->export($document->fresh(), 'docx');
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    expect(DocxRenderer::remainingPlaceholders($path))->toBe([])->and($xml)->toContain('withdrawal of RM500.00')->toContain('7001234567')->toContain('Fixture Club QIU');
    unlink($path);
});

it('needs a purpose and 2 weeks for a club withdrawal, and keeps outsiders out', function () {
    $form = ['scope' => 'club', 'purpose' => 'Weekly training balls', 'needed_by' => today()->addDays(20)->toDateString(),
        'items' => [['description' => 'Balls', 'unit_sen' => 3000, 'quantity' => 2, 'method' => 'self']],
        'bank' => ['bank_name' => 'Maybank', 'account_name' => $this->treasurer->name, 'account_number' => '112233445566'], 'bank_confirmed' => '1',
        'signature' => signaturePng(), 'signature_strokes' => signatureStrokes()];

    $this->actingAs(User::factory()->create())->post("/clubs/{$this->club->slug}/fund-requests", $form)->assertForbidden();
    $this->actingAs($this->treasurer)->post("/clubs/{$this->club->slug}/fund-requests", [...$form, 'needed_by' => today()->addDays(3)->toDateString()])->assertSessionHas('error');
    $this->actingAs($this->treasurer)->post("/clubs/{$this->club->slug}/fund-requests", $form)->assertRedirect();

    $first = Document::where('type', DocumentType::FundWithdrawal)->sole()->approvals()->where('step_no', 1)->with('signature')->first();
    expect($first->approver_user_id)->toBe($this->treasurer->id)->and($first->signature->position_at_time)->toBe('Treasurer');
});

it('lists a saved withdrawal under the Treasurer\'s Action needed until they sign', function () {
    $form = ['scope' => 'club', 'purpose' => 'Weekly training balls', 'needed_by' => today()->addDays(20)->toDateString(),
        'items' => [['description' => 'Balls', 'unit_sen' => 3000, 'quantity' => 2, 'method' => 'self']],
        'bank' => ['bank_name' => 'Maybank', 'account_name' => 'Club', 'account_number' => '112233445566'], 'bank_confirmed' => '1'];
    $this->actingAs($this->secretary)->post("/clubs/{$this->club->slug}/fund-requests", $form)->assertRedirect();

    $items = collect(app(ActionItems::class)->for($this->treasurer));
    expect($items->firstWhere('kind', 'payment')['title'] ?? null)->toStartWith('Sign the fund withdrawal')
        ->and($items->firstWhere('kind', 'payment')['body'])->toContain('60.00');
});
