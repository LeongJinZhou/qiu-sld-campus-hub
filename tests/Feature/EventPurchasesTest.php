<?php

use App\Enums\DocumentStatus;
use App\Models\EventPurchase;
use App\Models\LedgerEntry;
use App\Services\Ai\PurchaseReceiptReader;
use App\Services\Ai\ReceiptReader;
use App\Services\Events\EventPurchases;
use App\Services\Finance\Ledger;
use App\Services\Membership\ReceiptReading;
use App\Services\Time\SessionResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->f = proposalFixture();
    approvedEventWithBudget($this);
    $this->event = $this->f['event']->fresh();
    $this->event->update(['status' => DocumentStatus::Approved]);
    $this->president = $this->f['president'];
    $this->treasurer = $this->f['club']->roleAssignments()->where('position', 'Treasurer')->first()->user;
    app(Ledger::class)->setOpeningBalance($this->f['club'], 100000, app(SessionResolver::class)->current()->starts_on, null, sldOfficer());
    $this->helper = otherStudent('STD20249997', 'Aina Helper');
    $this->event->helpers()->attach($this->helper->id, ['added_by' => $this->president->id]);
    $this->mock(PurchaseReceiptReader::class)->shouldReceive('read')->andReturn([
        'items' => [['name' => 'Garden gloves', 'quantity' => 10, 'unit_sen' => 450, 'total_sen' => 4500]],
        'total_sen' => 4500, 'shop' => 'Mr DIY', 'date' => today()->toDateString(), 'confidence' => 0.93,
    ]);
    $this->purchases = app(EventPurchases::class);
});

$items = [['name' => 'Garden gloves', 'quantity' => 10, 'unit_sen' => 450]];

it('reads the receipt, guesses the budget line, and records a club-money purchase straight away', function () use ($items) {
    $p = $this->purchases->snap($this->event, $this->treasurer, UploadedFile::fake()->image('r.jpg'))->fresh();
    expect($p->status)->toBe(EventPurchase::DRAFT)->and($p->items[0]['name'])->toBe('Garden gloves')->and($p->budget_line)->toBe('Gloves');

    $this->purchases->submit($p, $this->treasurer, ['items' => $items, 'paid_with' => 'club', 'budget_line' => 'Gloves']);

    $entry = LedgerEntry::where('event_id', $this->event->id)->where('expense_sen', 4500)->sole();
    expect($p->fresh()->status)->toBe(EventPurchase::RECORDED)->and($entry->receipt_id)->not->toBeNull()
        ->and(collect($this->purchases->budget($this->event))->firstWhere('description', 'Gloves')['spent_sen'])->toBe(4500);
    expect(fn () => $this->purchases->snap($this->event, $this->treasurer, UploadedFile::fake()->createWithContent('r.jpg', file_get_contents(Storage::disk('local')->path($p->receipt_path)))))
        ->toThrow(DomainException::class, 'already been logged');
});

it('lets a helper claim back, verified by the event committee, paid back by transfer to the right name', function () use ($items) {
    $p = $this->purchases->snap($this->event, $this->helper, UploadedFile::fake()->image('r.jpg'))->fresh();
    expect(fn () => $this->purchases->submit($p, $this->helper, ['items' => $items, 'paid_with' => 'club']))->toThrow(DomainException::class, 'club money');

    $verifier = $this->purchases->verifiers($this->event)->first();
    $this->purchases->submit($p, $this->helper, ['items' => $items, 'paid_with' => 'own', 'verifier_id' => $verifier->id, 'budget_line' => 'Gloves']);
    expect($p->fresh()->status)->toBe(EventPurchase::PENDING)->and($verifier->notifications()->get()->reject(fn ($n) => str_starts_with($n->data['title'], 'Event approved'))->count())->toBe(1); // the event committee is now also told the event is approved

    $this->purchases->verify($p->fresh(), $verifier, true, null, 'now');
    expect($p->fresh()->status)->toBe(EventPurchase::OWED);

    // Transfer to someone else: not accepted, still owed.
    $payer = $verifier->is($this->treasurer) ? $this->president : $this->treasurer;
    $this->mock(ReceiptReader::class)->shouldReceive('read')->andReturn(new ReceiptReading(4500, today()->toDateString(), 'TNG111', 'Ahmad Bin Ali', 0.95), new ReceiptReading(4500, today()->toDateString(), 'TNG222', 'AINA HELPER', 0.95));
    $this->purchases->payOnline($p->fresh(), $payer, UploadedFile::fake()->image('t1.jpg'));
    expect($p->fresh()->status)->toBe(EventPurchase::OWED)->and($p->fresh()->review_note)->toContain('Ahmad Bin Ali');

    $this->purchases->payOnline($p->fresh(), $payer, UploadedFile::fake()->image('t2.jpg'));
    expect($p->fresh()->status)->toBe(EventPurchase::RECORDED)
        ->and(LedgerEntry::find($p->fresh()->ledger_entry_id)->only('expense_sen', 'method', 'handled_by'))->toBe(['expense_sen' => 4500, 'method' => 'online', 'handled_by' => $payer->id]);
});

it('pays back in cash only with the buyer\'s own QR in the photo, and records once the buyer confirms', function () use ($items) {
    $p = $this->purchases->snap($this->event, $this->helper, UploadedFile::fake()->image('r.jpg'))->fresh();
    $verifier = $this->purchases->verifiers($this->event)->first();
    $this->purchases->submit($p, $this->helper, ['items' => $items, 'paid_with' => 'own', 'verifier_id' => $verifier->id]);
    $this->purchases->verify($p->fresh(), $verifier, true, null, 'after_event');
    $payer = $verifier->is($this->treasurer) ? $this->president : $this->treasurer;

    expect(fn () => $this->purchases->payCash($p->fresh(), $payer, qrPhoto(route('profile.public', $this->helper->qr_token))))->toThrow(DomainException::class, 'after the event');

    $this->travelTo($this->event->ends_at->copy()->addHour());
    expect(fn () => $this->purchases->payCash($p->fresh(), $payer, qrPhoto(route('profile.public', $this->president->qr_token))))->toThrow(DomainException::class, 'someone else');
    $this->purchases->payCash($p->fresh(), $payer, qrPhoto(route('profile.public', $this->helper->qr_token)));
    expect($p->fresh()->status)->toBe(EventPurchase::PAYING);

    $this->purchases->confirmReceived($p->fresh(), $this->helper);
    expect($p->fresh()->status)->toBe(EventPurchase::RECORDED)->and(LedgerEntry::where('source_id', $p->id)->value('method'))->toBe('cash');
});

it('keeps the purchases page to the committee and helpers', function () {
    $this->actingAs($this->helper)->get("/events/{$this->event->slug}/purchases")->assertOk();
    $this->actingAs(otherStudent())->get("/events/{$this->event->slug}/purchases")->assertForbidden();
});
