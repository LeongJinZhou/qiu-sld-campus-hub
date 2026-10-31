<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\LedgerEntry;
use App\Models\Membership;
use App\Models\Payee;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\FinanceReport;
use App\Services\Finance\Ledger;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->treasurer = $this->club->roleAssignments()->where('position', 'Treasurer')->first()->user;
    $this->secretary = $this->club->roleAssignments()->where('position', 'Secretary')->first()->user;
    $this->ledger = app(Ledger::class);
    $this->ledger->setOpeningBalance($this->club, 100000, app(SessionResolver::class)->current()->starts_on, null, sldOfficer());
});

it('files an approved fund withdrawal under its event automatically, once', function () {
    approvedEventWithBudget($this);
    $payee = Payee::create(['club_id' => $this->club->id, 'kind' => 'treasurer', 'name' => 'T', 'bank_name' => 'CIMB', 'account_holder' => 'T', 'account_number' => '7001234567']);
    $this->actingAs($this->secretary)->post("/clubs/{$this->club->slug}/fund-requests", [
        'event_id' => $this->f['event']->id, 'purpose' => 'Supplies',
        'items' => [['description' => 'Gloves', 'unit_sen' => 500, 'quantity' => 40, 'method' => 'vendor']],
        'payees' => [['payee_id' => $payee->id, 'amount_sen' => 20000]],
        'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
    ])->assertRedirect();
    $document = Document::where('type', DocumentType::FundWithdrawal)->sole();
    approveRemainingSteps($document);

    $entry = LedgerEntry::where('category', 'withdrawal')->sole();
    expect($entry->only('expense_sen', 'event_id', 'method'))->toBe(['expense_sen' => 20000, 'event_id' => $this->f['event']->id, 'method' => 'transfer'])
        ->and($this->ledger->withdrawal($document->fresh()))->toBeNull(); // never twice
});

it('builds the year report with breakdowns by type and event, and filters', function () {
    $event = $this->f['event'];
    $event->update(['status' => DocumentStatus::Approved]);
    $today = CarbonImmutable::today();
    $this->ledger->income($this->club, $today, 'Membership fee (cash) – A', 3000, category: 'membership', method: 'cash', handledBy: $this->treasurer);
    $this->ledger->income($this->club, $today, 'Membership fee (online) – B', 3000, category: 'membership', method: 'online');
    $this->ledger->manual($this->club, $today, 'Gloves', 0, 2500, UploadedFile::fake()->image('r.jpg'), $this->treasurer, eventId: $event->id, method: 'cash');
    $this->ledger->manual($this->club, $today, 'Ticket sales', 4000, 0, null, $this->treasurer, eventId: $event->id, method: 'cash');

    $reports = app(FinanceReport::class);
    $session = app(SessionResolver::class)->current();
    $report = $reports->build($this->club, $session);
    $byType = collect($report['by_category'])->keyBy('key');
    expect($report['brought_forward_sen'])->toBe(100000)
        ->and($report['totals'])->toBe(['income_sen' => 10000, 'expense_sen' => 2500])
        ->and($report['closing_sen'])->toBe(107500)
        ->and($byType['membership']['income_sen'])->toBe(6000)
        ->and($byType['event']['expense_sen'])->toBe(2500)
        ->and($report['by_event'][0])->toMatchArray(['event' => $event->name, 'income_sen' => 4000, 'expense_sen' => 2500, 'count' => 2]);

    expect(collect($reports->build($this->club, $session, ['category' => 'membership'])['rows'])->pluck('description')->all())->toBe(['Membership fee (cash) – A', 'Membership fee (online) – B'])
        ->and($reports->build($this->club, $session, ['method' => 'cash', 'category' => 'membership'])['rows'][0]['handled_by'])->toBe($this->treasurer->name)
        ->and(count($reports->build($this->club, $session, ['event_id' => $event->id])['rows']))->toBe(2)
        ->and($reports->build($this->club, $session, ['sort' => 'amount_desc'])['rows'][0]['description'])->toBe('Ticket sales');

    $this->actingAs($this->treasurer)->get("/clubs/{$this->club->slug}/finance?category=membership")->assertOk()
        ->assertInertia(fn ($page) => $page->where('report.filtered', true)->has('report.rows', 2));
    $this->actingAs($this->treasurer)->get("/clubs/{$this->club->slug}/finance/export/xlsx?event_id={$event->id}")->assertOk();
});

it('lets the Treasurer attach a receipt to a transaction afterwards', function () {
    $entry = $this->ledger->income($this->club, today(), 'Sponsorship', 5000);
    $this->actingAs($this->treasurer)->post("/clubs/{$this->club->slug}/finance/entries/{$entry->id}/receipt", ['receipt' => UploadedFile::fake()->image('r.jpg')])->assertSessionHas('success');
    expect($entry->fresh()->receipt_id)->not->toBeNull();
    $this->actingAs(User::factory()->create())->post("/clubs/{$this->club->slug}/finance/entries/{$entry->id}/receipt", ['receipt' => UploadedFile::fake()->image('r.jpg')])->assertForbidden();
});

it('keeps club management, finance and AGM pages to the committee, advisor and SLD', function () {
    // A club member who is not on the committee, and a student with no link to the club.
    $member = Student::where('status', 'active')->whereNotIn('user_id', $this->club->roleAssignments()->pluck('user_id'))->whereNotNull('user_id')->first()->user;
    Membership::create(['club_id' => $this->club->id, 'user_id' => $member->id, 'status' => 'active', 'starts_on' => today(), 'expires_on' => today()->addYear()]);
    $outsider = User::factory()->create();
    $this->secretary = $this->secretary;
    $this->sld = sldOfficer();
    $agm = openPeriodAndAgm($this);
    $slug = $this->club->slug;
    $pages = ["/clubs/{$slug}/finance", "/clubs/{$slug}/finance/export/xlsx", "/clubs/{$slug}/members", "/clubs/{$slug}/members/export/xlsx",
        "/clubs/{$slug}/agm", "/agms/{$agm->id}/report", "/clubs/{$slug}/meetings", "/clubs/{$slug}/venues", '/admin', '/admin/agm', '/admin/finance'];

    foreach ([$member, $outsider] as $student) {
        foreach ($pages as $page) {
            $this->actingAs($student)->get($page)->assertForbidden();
        }
        $this->actingAs($student)->get("/clubs/{$slug}")->assertOk()->assertInertia(fn ($p) => $p->where('can.internal', false)->where('documents', null));
    }
    foreach ([$this->treasurer, $this->f['advisor'], $this->sld] as $staffOrCommittee) {
        $this->actingAs($staffOrCommittee)->get("/clubs/{$slug}/finance")->assertOk();
        $this->actingAs($staffOrCommittee)->get("/clubs/{$slug}/agm")->assertOk();
    }
});

it('shows Activities with management shortcuts for committee and memberships for members', function () {
    $this->actingAs($this->treasurer)->get('/activities')->assertOk()
        ->assertInertia(fn ($p) => $p->component('Activities/Index')->where('clubs.0.manages', true)->where('clubs.0.role', 'Treasurer'));
    // Every committee member can now do club money tasks, not only the Treasurer.
    $this->actingAs($this->secretary)->post("/clubs/{$this->club->slug}/finance/entries", ['date' => today()->toDateString(), 'description' => 'Donation', 'kind' => 'income', 'amount_rm' => 20])
        ->assertSessionHas('success');
});
