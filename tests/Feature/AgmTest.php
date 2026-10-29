<?php

use App\Enums\ChainRole;
use App\Enums\DocumentStatus;
use App\Jobs\DraftAgmReport;
use App\Models\User;
use App\Services\Agm\AgmReportForm;
use App\Services\Agm\Agms;
use App\Services\Ai\AgmDrafter;
use App\Services\Documents\AgmReportDocx;
use App\Services\Documents\DocxRenderer;
use App\Services\Events\AttendanceQr;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->secretary = $this->club->roleAssignments()->where('position', 'Secretary')->first()->user;
    $this->sld = sldOfficer();
});

it('keeps the AGM module locked until SLD opens a period', function () {
    $this->actingAs($this->secretary)->post("/clubs/{$this->club->slug}/agm", [
        'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(), 'venue' => 'Hall',
    ])->assertSessionHas('error', 'The AGM module opens when SLD starts the AGM period.');

    $agm = openPeriodAndAgm($this);
    expect($agm->report->status)->toBe(DocumentStatus::Draft)
        ->and($this->actingAs($this->secretary)->get("/clubs/{$this->club->slug}/agm")->assertOk());
    $this->actingAs(User::factory()->create())->get("/clubs/{$this->club->slug}/agm")->assertForbidden();
});

it('computes the attendance sentence and orders Advisor, attended members, then absent members; alumni excluded', function () {
    $agm = openPeriodAndAgm($this);
    [$zara, $adam, $absentee] = plainMembers($this, 3);
    [$alumnus] = plainMembers($this, 1, 'alumni');
    $this->travelTo($agm->starts_at->addMinutes(5));
    $agms = app(Agms::class);
    foreach ([$zara, $adam, $this->secretary, $this->f['president']] as $u) {
        $agms->attend($agm, $u, 'manual');
    }

    $c = app(AgmReportForm::class)->computed($agm->fresh());
    // 4 committee + 3 plain members; the alumnus is not counted.
    expect($c['total_members'])->toBe(7)->and($c['attended_members'])->toBe(4)->and($c['absent_members'])->toBe(3);
    $positions = array_column($c['attendance'], 'position');
    $states = array_column($c['attendance'], 'attendance');
    expect($positions[0])->toBe('Advisor')
        ->and(array_slice($positions, 1, 4))->toBe(['President', 'Secretary', 'Member', 'Member'])
        ->and(array_slice($states, 5))->toBe(['absent', 'absent', 'absent'])
        ->and(array_column($c['attendance'], 'name'))->not->toContain($alumnus->name)
        ->and(array_slice(array_column($c['attendance'], 'name'), 3, 2))->toBe(collect([$zara->name, $adam->name])->sort()->values()->all());
    expect($c['committee'][0]['position'])->toBe('Advisor')
        ->and(array_column(array_slice($c['committee'], 1, 4), 'position'))->toBe(['President', 'Vice President', 'Secretary', 'Treasurer']);
});

it('marks attendance by the rotating QR only for members, inside the AGM window', function () {
    $agm = openPeriodAndAgm($this);
    [$member] = plainMembers($this, 1);
    $qr = app(AttendanceQr::class);

    $this->actingAs($member)->get("/agms/{$agm->id}/attend?code=".$qr->code($agm, 'in'))->assertSessionHas('error');
    $this->travelTo($agm->starts_at->subMinutes(30));
    $this->actingAs($member)->get("/agms/{$agm->id}/attend?code=nope")->assertSessionHas('error', 'This QR code has expired. Scan the code on screen again.');
    $this->actingAs($member)->get("/agms/{$agm->id}/attend?code=".$qr->code($agm, 'in'))->assertSessionHas('success');
    $this->actingAs(User::factory()->create())->get("/agms/{$agm->id}/attend?code=".$qr->code($agm, 'in'))->assertSessionHas('error');
    expect($agm->attendees()->pluck('users.id')->all())->toBe([$member->id]);
});

it('refuses an AGM Report with fewer than 2 objectives, then runs Secretary → President → Advisor → SLD', function () {
    $agm = openPeriodAndAgm($this);
    $payload = ['objectives' => ['Review the session', '', ''], 'speech' => ['Welcome'], 'agenda' => [['start' => '4.00PM', 'end' => '4.10PM', 'item' => 'Arrival & attendance']],
        'adjournment' => 'Adjourned at 6.00 PM.', 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes()];

    $this->actingAs($this->secretary)->post("/agms/{$agm->id}/report/submit", $payload)->assertSessionHas('error', 'Write at least 2 objectives before submitting.');
    $this->actingAs($this->f['president'])->post("/agms/{$agm->id}/report/submit", [...$payload, 'objectives' => ['A', 'B']])
        ->assertSessionHas('error');

    $this->actingAs($this->secretary)->post("/agms/{$agm->id}/report/submit", [...$payload, 'objectives' => ['Review the session', 'Elect the committee']])->assertSessionHas('success');
    $document = $agm->report()->first();
    expect($document->status)->toBe(DocumentStatus::InReview)
        ->and($document->approvals()->orderBy('step_no')->get()->map(fn ($a) => $a->role)->all())->toBe([ChainRole::Secretary, ChainRole::President, ChainRole::Advisor, ChainRole::SldOfficer])
        ->and($document->data()['computed']['club'])->toBe($this->club->name);
    approveRemainingSteps($document);
    expect($document->fresh()->status)->toBe(DocumentStatus::Approved);

    $docx = app(AgmReportDocx::class)->render($document->fresh());
    expect(DocxRenderer::remainingPlaceholders($docx))->toBe([])
        ->and(docxText($docx))->toContain('Arrival &amp; attendance')->toContain('Elect the committee')->toContain('Our club has a total of 4 members')
        ->toContain($this->secretary->name)->toContain('Verify ');
});

it('lets the AI draft fill only the empty objectives, speech and agenda', function () {
    $agm = openPeriodAndAgm($this);
    $document = $agm->report;
    app(AgmReportForm::class)->saveDraft($document, ['objectives' => ['Mine', '', ''], 'speech' => [''], 'agenda' => [], 'adjournment' => ''], $this->secretary->id);
    $this->mock(AgmDrafter::class)->shouldReceive('draft')->andReturn([
        'objectives' => ['AI 1', 'AI 2', 'AI 3'], 'speech' => ['Thanks all'], 'agenda' => [['start' => '4.00PM', 'end' => '4.10PM', 'item' => 'Arrival']],
    ]);
    (new DraftAgmReport($document->id, $this->secretary->id))->handle(app(AgmDrafter::class), app(AgmReportForm::class));

    $data = app(AgmReportForm::class)->current($document->fresh());
    expect($data['objectives'])->toBe(['Mine', '', ''])->and($data['speech'])->toBe(['Thanks all'])->and($data['agenda'][0]['item'])->toBe('Arrival');
});
