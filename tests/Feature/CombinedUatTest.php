<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\CashHandover;
use App\Models\CcaRecord;
use App\Models\Club;
use App\Models\ClubFee;
use App\Models\Document;
use App\Models\Election;
use App\Models\Event;
use App\Models\EventPurchase;
use App\Models\GeneratedFile;
use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Models\Post;
use App\Models\Student;
use App\Models\User;
use App\Services\Agm\AgmReportForm;
use App\Services\Ai\ReceiptReader;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Approvals\ApproverResolver;
use App\Services\Events\AttendanceQr;
use App\Services\Events\PostMortemForm;
use App\Services\Finance\Ledger;
use App\Services\Membership\MembersQuery;
use App\Services\Membership\ReceiptReading;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Explicitly opt in: these tests issue thousands of in-process HTTP requests.
// They never start a server, transfer real money, or use the developer database.
beforeEach(function () {
    // Never the developer database: SQLite in memory, or (to verify MySQL, as on Exabytes) a dedicated throwaway
    // database whose name ends in _test.
    $connection = config('database.default');
    $database = (string) config("database.connections.{$connection}.database");
    expect($connection === 'sqlite' ? $database === ':memory:' : $connection === 'mysql' && str_ends_with($database, '_test'))
        ->toBeTrue("Combined UAT refuses the {$connection} database '{$database}'.");
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', config('app.timezone')));
    Http::preventStrayRequests();
    config(['services.anthropic.key' => null]);
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->secretary = $this->club->roleAssignments()->where('position', 'Secretary')->firstOrFail()->user;
    $this->treasurer = $this->club->roleAssignments()->where('position', 'Treasurer')->firstOrFail()->user;
    $this->sld = sldOfficer();
    $this->uat = ['requests' => 0, 'response_ms' => [], 'steps' => [], 'exports' => []];
});

afterEach(function () {
    if (isset($this->uatName, $this->uat)) {
        uatSave($this);
    }
});

function uatDirectory(): string
{
    $directory = '/private/tmp/sld-uat-20261102';
    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    return $directory;
}

function uatSave(object $t): void
{
    $data = $t->uat;
    $times = $data['response_ms'];
    sort($times);
    unset($data['response_ms']);
    $data['timing'] = ['in_process_only' => true, 'total_ms' => round(array_sum($times), 2),
        'p50_ms' => $times === [] ? null : $times[(int) floor((count($times) - 1) * 0.5)],
        'p95_ms' => $times === [] ? null : $times[(int) floor((count($times) - 1) * 0.95)],
        'max_ms' => $times === [] ? null : max($times), 'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1)];
    file_put_contents(uatDirectory().'/'.$t->uatName.'.json', json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}

function uatCall(object $t, User $actor, string $method, string $url, array $data = [], int $status = 302): TestResponse
{
    $start = hrtime(true);
    $response = $t->actingAs($actor)->{$method}($url, $data);
    $t->uat['requests']++;
    $t->uat['response_ms'][] = round((hrtime(true) - $start) / 1000000, 2);
    $response->assertStatus($status);
    if ($status < 400) {
        $response->assertSessionHasNoErrors();
        expect(session('error'), "$method $url")->toBeNull();
    }

    return $response;
}

function uatApprove(object $t, Document $document): void
{
    $engine = app(ApprovalEngine::class);
    $resolver = app(ApproverResolver::class);
    $roles = [];
    while ($step = $engine->currentStep($document->fresh())) {
        $user = $resolver->candidates($document, $step->role)->firstOrFail();
        uatCall($t, $user, 'get', '/approvals/'.$step->id, [], 200);
        uatCall($t, $user, 'post', '/approvals/'.$step->id, [
            'decision' => 'approve', 'no_problems' => true,
            'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
        ]);
        $roles[] = $step->role->value;
    }
    expect($document->fresh()->status)->toBe(DocumentStatus::Approved);
    $t->uat['steps'][] = ['approved' => $document->type->value, 'roles' => $roles];
}

function uatExport(object $t, User $actor, string $url, string $name): string
{
    $response = uatCall($t, $actor, 'get', $url, [], 200);
    $path = uatDirectory().'/'.$name;
    copy($response->getFile()->getPathname(), $path);
    $t->uat['exports'][] = $path;

    return $path;
}

function uatFee(object $t, Club $club, int $amount): void
{
    uatCall($t, $t->treasurer, 'post', '/clubs/'.$club->slug.'/fee', [
        'amount_rm' => $amount, 'payment_url' => 'https://pay.example/uat', 'account_name' => 'UAT Club QIU',
    ]);
    $fee = ClubFee::where('club_id', $club->id)->firstOrFail();
    uatCall($t, $t->sld, 'post', '/admin/fees/'.$fee->id.'/approve');
    uatCall($t, $t->sld, 'post', '/clubs/'.$club->slug.'/finance/opening', [
        'amount_rm' => 1000, 'as_of' => today()->toDateString(),
    ]);
}

function uatCash(object $t, User $student, CashHandover $handover): void
{
    $url = '/handovers/'.$handover->id;
    uatCall($t, $t->treasurer, 'post', $url, ['action' => 'accept']);
    uatCall($t, $student, 'post', $url, ['action' => 'ready']);
    uatCall($t, $t->treasurer, 'post', $url, ['action' => 'ready']);
    uatCall($t, $t->treasurer, 'post', $url, ['action' => 'photo', 'photo' => qrPhoto(route('profile.public', $student->qr_token))]);
    uatCall($t, $t->treasurer, 'post', $url, ['action' => 'confirm_cash']);
    uatCall($t, $student, 'post', $url, ['action' => 'done']);
    uatCall($t, $t->treasurer, 'post', $url, ['action' => 'done']);
    expect($handover->fresh()->status->value)->toBe('completed');
}

it('runs 3000 club joins plus online and cash payment requests and reconciles every club', function () {
    $this->uatName = 'capacity';
    $clubs = [$this->club];
    foreach (['UAT Online Club', 'UAT Cash Club'] as $i => $name) {
        uatCall($this, $this->sld, 'post', '/admin/clubs', ['name' => $name, 'slug' => 'uat-club-'.$i]);
        $club = Club::where('name', $name)->firstOrFail();
        foreach ($this->club->roleAssignments()->whereNotNull('position')->with('user.student')->get() as $assignment) {
            uatCall($this, $this->sld, 'post', '/admin/clubs/'.$club->slug.'/committee', [
                'student_number' => $assignment->user->student->student_number,
                'session' => app(SessionResolver::class)->current()->name, 'position' => $assignment->position,
            ]);
        }
        uatCall($this, $this->sld, 'post', '/admin/clubs/'.$club->slug.'/advisors', [
            'user_id' => $this->f['advisor']->id, 'session' => app(SessionResolver::class)->current()->name,
        ]);
        uatCall($this, $this->sld, 'post', '/admin/clubs/'.$club->slug.'/transfer');
        $clubs[] = $club;
    }
    foreach ($clubs as $index => $club) {
        uatFee($this, $club, [0, 30, 20][$index]);
    }
    $template = Student::where('student_number', 'STD20240010')->firstOrFail();
    $students = [];
    for ($i = 1; $i <= 1000; $i++) {
        $number = 'UAT'.str_pad((string) $i, 5, '0', STR_PAD_LEFT);
        $students[] = Student::create([...$template->only($template->getFillable()), 'user_id' => null,
            'student_number' => $number, 'email' => strtolower($number).'@qiu.edu.my', 'name' => 'UAT Student '.$i])->ensureUser();
    }
    $reference = 0;
    $this->mock(ReceiptReader::class)->shouldReceive('read')->andReturnUsing(function () use (&$reference) {
        return new ReceiptReading(3000, today()->toDateString(), 'UATBANK'.(++$reference), 'UAT Club QIU', 0.99, auth()->user()->name);
    });
    $png = base64_decode(substr(signaturePng(), strlen('data:image/png;base64,')), true);
    foreach ($students as $index => $student) {
        foreach ($clubs as $club) {
            uatCall($this, $student, 'post', '/clubs/'.$club->slug.'/join', ['acknowledged' => true]);
        }
        uatCall($this, $student, 'post', '/clubs/'.$clubs[1]->slug.'/join/receipt', [
            'receipt' => UploadedFile::fake()->createWithContent('receipt.png', $png.'UAT-'.$index),
        ]);
        uatCall($this, $student, 'post', '/clubs/'.$clubs[2]->slug.'/join/cash', ['committee_user_id' => $this->treasurer->id]);
        if ($index < 10) {
            $handover = CashHandover::where('student_id', $student->id)->latest('id')->firstOrFail();
            uatCash($this, $student, $handover);
            // A single collector's 60/minute handover limit remains enabled.
            $this->travel(1)->minutes();
        }
        if (($index + 1) % 100 === 0) {
            $this->uat['students_processed'] = $index + 1;
            uatSave($this);
        }
    }
    $expected = [100000, 3100000, 120000];
    $reconciliation = [];
    foreach ($clubs as $index => $club) {
        $actual = app(Ledger::class)->balance($club->fresh());
        $reconciliation[] = ['club' => $club->name, 'memberships' => $club->memberships()->count(),
            'active' => $club->memberships()->where('status', 'active')->count(),
            'expected_balance_sen' => $expected[$index], 'actual_balance_sen' => $actual];
        expect($club->memberships()->count())->toBe(1000)->and($actual)->toBe($expected[$index]);
        // Spec change: every member type is listed, so the club's committee members and advisor who hold no
        // membership row appear alongside the 1,000 members.
        $officers = app(MembersQuery::class)->rows([$club->id])->whereIn('status', ['committee', 'advisor'])->count();
        $listed = 1000 + $officers;
        $lastPage = (int) ceil($listed / 100);
        uatCall($this, $this->treasurer, 'get', '/clubs/'.$club->slug.'/members', [], 200)
            ->assertInertia(fn ($page) => $page->where('pagination.total', $listed)->where('pagination.last_page', $lastPage)->has('rows', 100));
        uatCall($this, $this->treasurer, 'get', '/clubs/'.$club->slug.'/members?page='.$lastPage, [], 200)
            ->assertInertia(fn ($page) => $page->where('pagination.page', $lastPage)->has('rows', $listed - ($lastPage - 1) * 100));
        uatCall($this, $this->treasurer, 'get', '/clubs/'.$club->slug.'/payments/review', [], 200);
        $path = uatExport($this, $this->treasurer, '/clubs/'.$club->slug.'/members/export/xlsx', 'capacity-members-'.$index.'.xlsx');
        $book = IOFactory::load($path);
        expect($book->getActiveSheet()->getHighestDataRow())->toBeGreaterThan(1000);
        $book->disconnectWorksheets();
    }
    $this->uat['reconciliation'] = $reconciliation;
    $this->uat['memberships'] = Membership::count();
    $this->uat['cash_completed'] = CashHandover::where('status', 'completed')->count();
    $this->uat['cash_pending'] = CashHandover::where('status', '!=', 'completed')->count();
    $this->uat['member_screen_limit'] = 500;
    expect(Membership::count())->toBe(3000)->and($reference)->toBe(1000);
    $sample = MembershipPayment::where('club_id', $clubs[1]->id)->where('method', 'online')->firstOrFail();
    uatCall($this, $this->sld, 'get', '/admin/finance?q='.urlencode($sample->order_id), [], 200)
        ->assertInertia(fn ($page) => $page->where('transactions.0.order_id', $sample->order_id));
    uatCall($this, $this->treasurer, 'get', '/clubs/'.$clubs[1]->slug.'/finance?search='.urlencode($sample->order_id), [], 200)
        ->assertInertia(fn ($page) => $page->has('report.rows', 1));
    uatCall($this, $this->treasurer, 'get', '/clubs/'.$clubs[2]->slug.'/members?search=UAT00001', [], 200);
    $path = uatExport($this, $this->treasurer, '/clubs/'.$clubs[1]->slug.'/finance/workbook/xlsx?period=all', 'capacity-online-finance.xlsx');
    $book = IOFactory::load($path);
    $overview = $book->getSheetByName('Overview');
    $closing = null;
    foreach ($overview->getRowIterator() as $row) {
        $n = $row->getRowIndex();
        if ($overview->getCell('A'.$n)->getValue() === 'Closing balance') {
            $closing = (int) round((float) $overview->getCell('B'.$n)->getCalculatedValue() * 100);
        }
    }
    expect($closing)->toBe(3100000);
    $book->disconnectWorksheets();
    $this->uat['steps'][] = '3000 joins, 1000 online receipts, 1000 cash requests, 10 complete cash handovers, student/transaction lookup and XLSX reconciliation';
})->group('combined-uat')->skip(fn () => getenv('SLD_UAT') !== '1', 'Set SLD_UAT=1 for the isolated combined UAT');

it('runs an event from committee creation through approvals student payment attendance and post-event reports', function () {
    $this->uatName = 'event-journey';
    uatFee($this, $this->club, 30);
    $student = otherStudent();
    $start = now()->addMonths(4)->setTime(9, 0);
    uatCall($this, $this->secretary, 'post', '/clubs/'.$this->club->slug.'/events', [
        'name' => 'UAT Community Activity', 'starts_at' => $start->toDateTimeString(),
        'ends_at' => $start->addHours(2)->toDateTimeString(), 'expected_participants' => 40,
    ]);
    $event = Event::where('name', 'UAT Community Activity')->firstOrFail();
    foreach ($this->f['event']->committee()->with('user.student')->get() as $a) {
        uatCall($this, $this->secretary, 'post', '/events/'.$event->slug.'/committee', [
            'student_number' => $a->user->student->student_number, 'position' => $a->position, 'responsibility' => 'UAT '.$a->position,
        ]);
    }
    $f = [...$this->f, 'event' => $event];
    $data = completeProposal($f, ['is_outdoor' => true]);
    uatCall($this, $this->secretary, 'put', '/events/'.$event->slug.'/proposal', $data);
    uatCall($this, $this->secretary, 'post', '/events/'.$event->slug.'/proposal/submit', [
        ...$data, 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
    ]);
    uatApprove($this, $event->proposal()->firstOrFail());
    $event->refresh();
    uatCall($this, $student, 'post', '/clubs/'.$this->club->slug.'/follow');
    uatCall($this, $this->secretary, 'post', '/clubs/'.$this->club->slug.'/posts', [
        'type' => 'general', 'caption' => 'UAT community activity is open for registration.', 'photos' => [UploadedFile::fake()->image('activity.jpg')],
    ]);
    $post = Post::where('type', 'general')->latest('id')->firstOrFail();
    uatCall($this, $student, 'post', '/posts/'.$post->id.'/like');
    uatCall($this, $student, 'post', '/posts/'.$post->id.'/comments', ['body' => 'Looking forward to joining this activity.']);
    uatCall($this, $student, 'post', '/posts/'.$post->id.'/save');
    expect($post->likers()->count())->toBe(1)->and($post->comments()->count())->toBe(1);
    uatCall($this, $this->treasurer, 'put', '/events/'.$event->slug.'/payment-settings', [
        'fee_rm' => 15, 'payment_url' => 'https://pay.example/event', 'payment_account_name' => 'UAT Club QIU',
    ]);
    uatCall($this, $student, 'post', '/events/'.$event->slug.'/register');
    $this->mock(ReceiptReader::class)->shouldReceive('read')->andReturn(new ReceiptReading(1500, today()->toDateString(), 'UATEVENT1', 'UAT Club QIU', 0.99, $student->name));
    uatCall($this, $student, 'post', '/events/'.$event->slug.'/pay/receipt', ['receipt' => UploadedFile::fake()->image('event-receipt.jpg')]);
    uatCall($this, $student, 'post', '/events/'.$event->slug.'/indemnity', [
        'emergency_name' => 'UAT Parent', 'emergency_mobile_phone' => '0123456789', 'emergency_relationship' => 'Parent',
        'address' => 'Dummy UAT address', 'consent' => true, 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
    ]);
    $registration = $event->registrations()->where('user_id', $student->id)->firstOrFail();
    uatCall($this, $student, 'get', '/tickets/'.$registration->ticket_code, [], 200);
    $this->travelTo($event->starts_at->addMinute());
    uatCall($this, $student, 'get', route('events.attend', [$event, 'action' => 'in', 'code' => app(AttendanceQr::class)->code($event, 'in')]));
    $this->travelTo($event->ends_at->subMinute());
    uatCall($this, $student, 'get', route('events.attend', [$event, 'action' => 'out', 'code' => app(AttendanceQr::class)->code($event, 'out')]));
    expect(CcaRecord::where('user_id', $student->id)->where('event_id', $event->id)->exists())->toBeTrue();
    uatExport($this, $this->secretary, '/events/'.$event->slug.'/attendance/export', 'event-attendance.xlsx');
    uatCall($this, $this->secretary, 'post', '/events/'.$event->slug.'/attendance/submit', ['signature' => signaturePng(), 'signature_strokes' => signatureStrokes()]);
    uatApprove($this, Document::where('event_id', $event->id)->where('type', DocumentType::AttendanceList)->firstOrFail());
    $this->travelTo($event->ends_at->addDay());
    uatCall($this, $this->treasurer, 'post', '/clubs/'.$this->club->slug.'/finance/entries', [
        'date' => today()->toDateString(), 'description' => 'UAT gloves', 'kind' => 'expense', 'amount_rm' => 5,
        'event_id' => $event->id, 'method' => 'cash', 'receipt' => UploadedFile::fake()->image('gloves.jpg'), 'original' => true,
    ]);
    $rows = array_fill_keys(array_keys(PostMortemForm::AREAS), [
        'well' => 'Completed as planned', 'challenges' => 'Minor delays', 'improvement' => 'Start setup earlier',
    ]);
    uatCall($this, $this->secretary, 'post', '/events/'.$event->slug.'/post-mortem/submit', [
        'rows' => $rows, 'recommendations' => ['Repeat the activity'], 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
    ]);
    uatApprove($this, Document::where('event_id', $event->id)->where('type', DocumentType::PostMortem)->firstOrFail());
    foreach ($event->documentItems()->get() as $item) {
        uatCall($this, $this->secretary, 'post', '/events/'.$event->slug.'/documents/'.$item->id, ['file' => UploadedFile::fake()->image('evidence.jpg')]);
    }
    uatCall($this, $this->secretary, 'post', '/events/'.$event->slug.'/helpers', ['student_number' => $student->student->student_number]);
    uatCall($this, $student, 'post', '/events/'.$event->slug.'/purchases', ['receipt' => UploadedFile::fake()->image('shop.jpg', 120, 100)]);
    $purchase = EventPurchase::where('bought_by', $student->id)->firstOrFail();
    $verifier = $event->committee()->where('position', 'President')->firstOrFail()->user;
    uatCall($this, $student, 'put', '/purchases/'.$purchase->id, [
        'items' => [['name' => 'Gloves', 'quantity' => 2, 'unit_sen' => 500]], 'paid_with' => 'own', 'verifier_id' => $verifier->id, 'budget_line' => 'Gloves',
    ]);
    uatCall($this, $verifier, 'post', '/purchases/'.$purchase->id.'/verify', ['approve' => true, 'settle_when' => 'now']);
    uatCall($this, $this->treasurer, 'post', '/purchases/'.$purchase->id.'/pay-cash', ['photo' => qrPhoto(route('profile.public', $student->qr_token))]);
    uatCall($this, $student, 'post', '/purchases/'.$purchase->id.'/received');
    expect($purchase->fresh()->status)->toBe('recorded');
    $this->uat['reconciliation'] = ['opening_sen' => 100000, 'event_income_sen' => 1500, 'expenses_sen' => 500,
        'reimbursed_purchase_sen' => 1000, 'expected_closing_sen' => 100000, 'actual_closing_sen' => app(Ledger::class)->balance($this->club->fresh())];
    expect($this->uat['reconciliation']['actual_closing_sen'])->toBe(100000);
    uatExport($this, $this->treasurer, '/clubs/'.$this->club->slug.'/finance/workbook/xlsx?period=all', 'event-finance.xlsx');
    uatExport($this, $this->secretary, '/clubs/'.$this->club->slug.'/participants/export/xlsx', 'event-participants.xlsx');
    uatCall($this, $this->sld, 'get', '/admin', [], 200);
    $this->uat['steps'][] = 'Proposal created and signed through all approvers; social interactions; online fee; indemnity; ticket; QR attendance; CCA; expense; approved attendance and post-mortem; evidence checklist';
})->group('combined-uat')->skip(fn () => getenv('SLD_UAT') !== '1', 'Set SLD_UAT=1 for the isolated combined UAT');

it('does not count an event cash advance and its funded purchases as two separate expenses', function () {
    $this->uatName = 'advance-reconciliation';
    uatFee($this, $this->club, 0);
    approvedEventWithBudget($this);
    $event = $this->f['event'];
    uatCall($this, $this->secretary, 'post', '/clubs/'.$this->club->slug.'/fund-requests', [
        'scope' => 'event', 'event_id' => $event->id,
        'items' => [['description' => 'Gloves', 'unit_sen' => 500, 'quantity' => 20, 'method' => 'self']],
        'bank' => ['bank_name' => 'UAT Bank', 'account_name' => 'UAT Treasurer', 'account_number' => '1234567890'], 'bank_confirmed' => true,
    ]);
    $withdrawal = Document::where('type', DocumentType::FundWithdrawal)->firstOrFail();
    $eventTreasurer = $event->committee()->where('position', 'Treasurer')->firstOrFail()->user;
    uatCall($this, $eventTreasurer, 'post', '/withdrawals/'.$withdrawal->id.'/sign', [
        'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(), 'bank_confirmed' => true,
    ]);
    uatApprove($this, $withdrawal);
    expect(app(Ledger::class)->balance($this->club->fresh()))->toBe(100000);
    uatCall($this, $this->sld, 'post', '/withdrawals/'.$withdrawal->id.'/finance-signed', ['file' => UploadedFile::fake()->image('finance-signed.png')]);
    uatCall($this, $eventTreasurer, 'post', '/withdrawals/'.$withdrawal->id.'/bank-in', [
        'file' => UploadedFile::fake()->image('bank-in.png'), 'reference' => 'UATADVANCE100',
    ]);
    uatCall($this, $this->sld, 'post', '/withdrawals/'.$withdrawal->id.'/complete');
    uatCall($this, $eventTreasurer, 'post', '/events/'.$event->slug.'/purchases', ['receipt' => UploadedFile::fake()->image('shop-gloves.png')]);
    $purchase = EventPurchase::firstOrFail();
    uatCall($this, $eventTreasurer, 'put', '/purchases/'.$purchase->id, [
        'items' => [['name' => 'Gloves', 'quantity' => 10, 'unit_sen' => 500]], 'paid_with' => 'club', 'budget_line' => 'Gloves',
    ]);
    $actual = app(Ledger::class)->balance($this->club->fresh());
    $this->uat['reconciliation'] = ['opening_sen' => 100000, 'advance_sen' => 10000, 'actual_expenses_sen' => 5000,
        'bank_funds_sen' => 90000, 'unspent_advance_sen' => 5000, 'expected_total_club_assets_sen' => 95000,
        'actual_displayed_balance_sen' => $actual, 'note' => 'An advance is a movement of club cash; its purchases are the actual expenses.'];
    uatExport($this, $this->treasurer, '/clubs/'.$this->club->slug.'/finance/workbook/xlsx?period=all', 'advance-and-purchase-finance.xlsx');
    expect($actual)->toBe(95000);
})->group('combined-uat')->skip(fn () => getenv('SLD_UAT') !== '1', 'Set SLD_UAT=1 for the isolated combined UAT');

it('runs weekly activity minutes AGM elections report and handover in one session', function () {
    $this->uatName = 'agm-journey';
    uatFee($this, $this->club, 0);
    $members = [];
    for ($i = 0; $i < 5; $i++) {
        $member = otherStudent('UATAGM'.$i, 'UAT Candidate '.$i);
        $member->student->update(['expected_end_date' => now()->addYears(4)]);
        uatCall($this, $member, 'post', '/clubs/'.$this->club->slug.'/join', ['acknowledged' => true]);
        $members[] = $member;
    }
    uatCall($this, $this->secretary, 'post', '/clubs/'.$this->club->slug.'/meetings', [
        'kind' => 'activity', 'title' => 'UAT Weekly Training', 'starts_at' => now()->addDay()->toDateTimeString(),
        'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(), 'venue' => 'UAT Hall', 'online' => false,
    ]);
    $meeting = $this->club->meetings()->firstOrFail();
    $this->travelTo($meeting->ends_at->addMinute());
    uatCall($this, $this->secretary, 'post', '/meetings/'.$meeting->id.'/attendance', ['present' => array_column(array_map(fn ($u) => ['id' => $u->id], $members), 'id')]);
    withPhotos($meeting); // Spec change: a weekly activity report needs SLD's minimum evidence photos (default 3).
    uatCall($this, $this->secretary, 'post', '/meetings/'.$meeting->id.'/minutes/submit', [
        'purpose' => 'UAT weekly training', 'summary' => ['Training completed'], 'actions' => [], 'notes' => '',
        'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
    ]);
    uatApprove($this, $meeting->minutes()->firstOrFail());
    expect(CcaRecord::where('meeting_id', $meeting->id)->count())->toBe(5);
    uatExport($this, $this->secretary, '/documents/'.$meeting->minutes->id.'/download/docx', 'weekly-minutes.docx');
    $agm = openPeriodAndAgm($this);
    $this->travelTo($agm->starts_at->addMinutes(5));
    foreach ($members as $member) {
        uatCall($this, $member, 'get', '/agms/'.$agm->id.'/attend?code='.app(AttendanceQr::class)->code($agm, 'in'));
    }
    uatCall($this, $this->secretary, 'post', '/agms/'.$agm->id.'/election', [
        'nominations_open_at' => now()->subHour()->toDateTimeString(), 'nominations_close_at' => now()->addHour()->toDateTimeString(),
        'voting_open_at' => now()->addHours(2)->toDateTimeString(), 'voting_close_at' => now()->addHours(3)->toDateTimeString(),
        'allow_alumni_vote' => false, 'extra_positions' => [],
    ]);
    $election = Election::firstOrFail();
    foreach ($election->positions()->get() as $i => $position) {
        uatCall($this, $members[$i], 'post', '/election-positions/'.$position->id.'/nominate', ['statement' => 'Serve the UAT club']);
    }
    $this->travelTo($election->voting_open_at->addMinute());
    foreach ($election->positions()->with('candidates')->get() as $position) {
        uatCall($this, $members[4], 'post', '/election-positions/'.$position->id.'/vote', ['candidate_id' => $position->candidates->first()->id]);
    }
    $this->travelTo($election->voting_close_at->addMinute());
    uatCall($this, $this->secretary, 'post', '/elections/'.$election->id.'/publish');
    withPhotos($agm); // Spec change: the AGM report needs SLD's minimum evidence photos (default 3).
    uatCall($this, $this->secretary, 'post', '/agms/'.$agm->id.'/report/submit', [
        'objectives' => ['Review achievements', 'Elect incoming committee'], 'speech' => ['Welcome to the UAT AGM'],
        'agenda' => [['start' => '16:00', 'end' => '18:00', 'item' => 'Review and election']], 'adjournment' => '18:00',
        'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
    ]);
    uatApprove($this, $agm->report()->firstOrFail());
    $computed = app(AgmReportForm::class)->computed($agm->fresh());
    expect($computed['total_members'])->toBe(9)->and($computed['attended_members'])->toBe(5);
    uatExport($this, $this->secretary, '/documents/'.$agm->report->id.'/download/docx', 'agm-report.docx');
    uatExport($this, $this->secretary, '/clubs/'.$this->club->slug.'/members/export/xlsx', 'agm-members.xlsx');
    uatExport($this, $this->secretary, '/clubs/'.$this->club->slug.'/finance/export/xlsx', 'agm-finance.xlsx');
    $this->uat['agm'] = ['members' => $computed['total_members'], 'present' => $computed['attended_members'],
        'weekly_cca_records' => CcaRecord::where('meeting_id', $meeting->id)->count(), 'report_status' => $agm->report->fresh()->status->value];
    // Exercise annual generation honestly. A PDF converter failure is recorded, never replaced with a fake PDF.
    $this->f['event']->update(['status' => DocumentStatus::Approved, 'venue' => 'UAT Beach']);
    uatCall($this, $this->secretary, 'put', '/clubs/'.$this->club->slug.'/annual-report', [
        'details' => [$this->f['event']->id => 'UAT students completed a community activity.'], 'theme_id' => null,
    ]);
    $response = $this->actingAs($this->secretary)->post('/clubs/'.$this->club->slug.'/annual-report/generate');
    $this->uat['requests']++;
    $this->uat['annual_generation_error'] = session('error');
    $file = GeneratedFile::where('club_id', $this->club->id)->first();
    foreach (glob(storage_path('app/private/generated/annual-'.$this->club->id.'-'.$agm->session_id.'-'.now()->format('YmdHis').'.pptx')) ?: [] as $source) {
        $target = uatDirectory().'/annual-activities.pptx';
        copy($source, $target);
        $this->uat['exports'][] = $target;
    }
    $this->flushSession();
    // Spec change: the handover is started by SLD once it has acknowledged the AGM (package submitted first), with
    // the SLD-configured overlap; it is no longer automatic seven days after publication.
    uatCall($this, $this->secretary, 'post', '/agms/'.$agm->id.'/package');
    $sld = sldOfficer();
    uatCall($this, $sld, 'post', '/admin/agms/'.$agm->id.'/acknowledge');
    uatCall($this, $sld, 'post', '/admin/elections/'.$election->id.'/handover');
    $this->travelTo($election->fresh()->handoverOn()->addHour());
    $this->artisan('schedule:test', ['--name' => 'election-handover'])->assertSuccessful();
    expect($this->secretary->can('viewInternal', $this->club))->toBeFalse()
        ->and($members[0]->can('viewInternal', $this->club))->toBeTrue()
        ->and($members[3]->can('treasure', $this->club))->toBeTrue();
    $this->uat['steps'][] = 'Weekly activity approved with CCA; AGM scheduled; attendance; nomination; voting; publication; signed AGM report; native exports; seven-day access handover';
    uatSave($this);
    expect($this->uat['annual_generation_error'])->toBeNull();
})->group('combined-uat')->skip(fn () => getenv('SLD_UAT') !== '1', 'Set SLD_UAT=1 for the isolated combined UAT');

it('exports an empty club finance workbook without circular formulas', function () {
    $this->uatName = 'empty-finance';
    uatFee($this, $this->club, 0);
    $path = uatExport($this, $this->treasurer, '/clubs/'.$this->club->slug.'/finance/workbook/xlsx?period=all', 'empty-finance.xlsx');
    $book = IOFactory::load($path);
    $sheet = $book->getSheetByName('Overview');
    $value = $sheet->getCell('B8')->getCalculatedValue();
    $this->uat['total_transactions_formula'] = $sheet->getCell('B8')->getValue();
    $this->uat['total_transactions'] = $value;
    if (preg_match('/B(\d+):B(\d+)/', (string) $this->uat['total_transactions_formula'], $range)) {
        $selfReference = min((int) $range[1], (int) $range[2]) <= 8 && max((int) $range[1], (int) $range[2]) >= 8;
        $this->uat['self_referencing_total'] = $selfReference;
        expect($selfReference)->toBeFalse('A transaction total must not include its own cell');
    }
    expect($value)->toEqual(0);
})->group('combined-uat')->skip(fn () => getenv('SLD_UAT') !== '1', 'Set SLD_UAT=1 for the isolated combined UAT');

it('exports user entered descriptions as text rather than executable spreadsheet formulas', function () {
    $this->uatName = 'export-input-safety';
    uatFee($this, $this->club, 0);
    uatCall($this, $this->treasurer, 'post', '/clubs/'.$this->club->slug.'/finance/entries', [
        'date' => today()->toDateString(), 'description' => '=1+1', 'kind' => 'income', 'amount_rm' => 20,
    ]);
    $path = uatExport($this, $this->treasurer, '/clubs/'.$this->club->slug.'/finance/workbook/xlsx?period=all', 'formula-input-finance.xlsx');
    $book = IOFactory::load($path);
    $cell = $book->getSheetByName('Other transactions')->getCell('D6');
    $this->uat['description_type'] = $cell->getDataType();
    $this->uat['description_value'] = $cell->getValue();
    expect($cell->getDataType())->toBe(DataType::TYPE_STRING)->and($cell->getValue())->toBe('=1+1');
})->group('combined-uat')->skip(fn () => getenv('SLD_UAT') !== '1', 'Set SLD_UAT=1 for the isolated combined UAT');

it('refuses another membership payment after the same membership is already paid', function (string $channel) {
    $this->uatName = 'paid-again-'.$channel;
    uatFee($this, $this->club, 30);
    $student = otherStudent();
    uatCall($this, $student, 'post', '/clubs/'.$this->club->slug.'/join', ['acknowledged' => true]);
    $reference = 0;
    $this->mock(ReceiptReader::class)->shouldReceive('read')->andReturnUsing(function () use ($student, &$reference) {
        return new ReceiptReading(3000, today()->toDateString(), 'UATREPAY'.(++$reference), 'UAT Club QIU', 0.99, $student->name);
    });
    uatCall($this, $student, 'post', '/clubs/'.$this->club->slug.'/join/receipt', ['receipt' => UploadedFile::fake()->image('first.png', 100, 100)]);
    expect($student->memberships()->firstOrFail()->status->value)->toBe('active');
    $start = hrtime(true);
    $response = $channel === 'online'
        ? $this->actingAs($student)->post('/clubs/'.$this->club->slug.'/join/receipt', ['receipt' => UploadedFile::fake()->image('second.png', 101, 100)])
        : $this->actingAs($student)->post('/clubs/'.$this->club->slug.'/join/cash', ['committee_user_id' => $this->treasurer->id]);
    $this->uat['requests']++;
    $this->uat['response_ms'][] = round((hrtime(true) - $start) / 1000000, 2);
    $this->uat['reconciliation'] = ['channel' => $channel, 'expected_payment_count' => 1,
        'actual_payment_count' => MembershipPayment::count(), 'expected_balance_sen' => 103000,
        'actual_balance_sen' => app(Ledger::class)->balance($this->club->fresh()), 'cash_requests' => CashHandover::count()];
    $response->assertSessionHas('error');
    expect(MembershipPayment::count())->toBe(1)->and(app(Ledger::class)->balance($this->club->fresh()))->toBe(103000);
})->with(['online', 'cash'])->group('combined-uat')->skip(fn () => getenv('SLD_UAT') !== '1', 'Set SLD_UAT=1 for the isolated combined UAT');
