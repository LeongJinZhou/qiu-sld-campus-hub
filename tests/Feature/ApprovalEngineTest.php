<?php

use App\Enums\ApprovalDecision;
use App\Enums\ChainRole;
use App\Enums\DocumentStatus;
use App\Models\EventDocumentItem;
use App\Models\Post;
use App\Models\RoleAssignment;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Approvals\ApproverResolver;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->engine = app(ApprovalEngine::class);
    $this->data = ['name' => 'Beach Cleanup', 'budget' => ['revenue' => [], 'expenses' => []], 'starts_at' => '2027-03-01T08:00', 'ends_at' => '2027-03-01T12:30'];
});

it('builds the proposal chain in the official order', function () {
    $this->engine->submit($this->f['document'], $this->f['president'], $this->data, signaturePng());

    expect($this->f['document']->approvals()->pluck('role')->all())->toBe([
        ChainRole::Preparer, ChainRole::Advisor, ChainRole::SldOfficer, ChainRole::AssistantRegistrar,
        ChainRole::Registrar, ChainRole::Bursar, ChainRole::Coo,
    ]);
});

it('adds the Dean step for faculty events', function () {
    $this->f['event']->update(['is_faculty_event' => true]);
    $this->engine->submit($this->f['document']->fresh(), $this->f['president'], $this->data, signaturePng());

    expect($this->f['document']->approvals()->pluck('role')->all())->toContain(ChainRole::Dean);
});

it('signs the preparer step on submit with server time and a verification code', function () {
    $this->engine->submit($this->f['document'], $this->f['president'], $this->data, signaturePng());

    $first = $this->f['document']->approvals()->first();
    expect($first->decision)->toBe(ApprovalDecision::Approved)
        ->and($first->signature->position_at_time)->toBe('President')
        ->and($first->signature->verification_code)->toHaveLength(12)
        ->and($this->f['document']->fresh()->status)->toBe(DocumentStatus::InReview);
});

it('refuses submission until the event committee has its four compulsory positions', function () {
    RoleAssignment::where('scope_type', 'event')->where('position', 'Treasurer')->delete();

    $this->engine->submit($this->f['document'], $this->f['president'], $this->data, signaturePng());
})->throws(DomainException::class, 'Treasurer');

it('only lets the current step act, and only the right person', function () {
    $this->engine->submit($this->f['document'], $this->f['president'], $this->data, signaturePng());
    $advisorStep = $this->engine->currentStep($this->f['document']->fresh());
    $sldStep = $this->f['document']->approvals()->where('role', ChainRole::SldOfficer)->first();

    expect($advisorStep->role)->toBe(ChainRole::Advisor)
        ->and($this->engine->canAct($advisorStep, $this->f['president']))->toBeFalse()
        ->and($this->engine->canAct($advisorStep, $this->f['advisor']))->toBeTrue()
        ->and($this->engine->canAct($sldStep, sldOfficer()))->toBeFalse();
});

it('runs through all seven signers, then posts to the feed, unlocks attendance and creates the checklist', function () {
    $this->engine->submit($this->f['document'], $this->f['president'], $this->data, signaturePng());
    approveRemainingSteps($this->f['document']);

    $event = $this->f['event']->fresh();
    expect($this->f['document']->fresh()->status)->toBe(DocumentStatus::Approved)
        ->and($this->f['document']->signatures()->count())->toBe(7)
        ->and($event->status)->toBe(DocumentStatus::Approved)
        ->and($event->attendance_open)->toBeTrue()
        ->and($event->registration_open)->toBeTrue()
        ->and(Post::where('event_id', $event->id)->where('type', 'event')->exists())->toBeTrue()
        ->and($event->documentItems()->count())->toBe(count(EventDocumentItem::KINDS))
        ->and($event->documentItems()->where('kind', 'receipts')->first()->due_on->toDateString())->toBe($event->ends_at->copy()->addWeek()->toDateString());
});

it('returns to the club with a comment and keeps earlier approvals on an unchanged resubmit', function () {
    $this->engine->submit($this->f['document'], $this->f['president'], $this->data, signaturePng());
    $resolver = app(ApproverResolver::class);
    $this->engine->approve($this->engine->currentStep($this->f['document']->fresh()), $this->f['advisor'], signaturePng());
    $sld = $this->engine->currentStep($this->f['document']->fresh());
    $this->engine->returnToClub($sld, $resolver->candidates($this->f['document'], ChainRole::SldOfficer)->first(), 'Add the venue booking.');

    expect($this->f['document']->fresh()->status)->toBe(DocumentStatus::Returned);

    $this->engine->submit($this->f['document']->fresh(), $this->f['president'], [...$this->data, 'conclusion' => 'Venue booked.'], signaturePng());

    expect($this->engine->currentStep($this->f['document']->fresh())->role)->toBe(ChainRole::SldOfficer)
        ->and($this->f['document']->approvals()->where('role', ChainRole::Advisor)->value('decision'))->toBe(ApprovalDecision::Approved);
});

it('restarts the whole chain when the budget or date changes on resubmit', function () {
    $this->engine->submit($this->f['document'], $this->f['president'], $this->data, signaturePng());
    $this->engine->approve($this->engine->currentStep($this->f['document']->fresh()), $this->f['advisor'], signaturePng());
    $sld = $this->engine->currentStep($this->f['document']->fresh());
    $this->engine->returnToClub($sld, app(ApproverResolver::class)->candidates($this->f['document'], ChainRole::SldOfficer)->first(), 'Fix date');

    $this->engine->submit($this->f['document']->fresh(), $this->f['president'], [...$this->data, 'starts_at' => '2027-03-08T08:00'], signaturePng());

    expect($this->engine->currentStep($this->f['document']->fresh())->role)->toBe(ChainRole::Advisor);
});

it('requires a comment to return or reject, and rejection is final', function () {
    $this->engine->submit($this->f['document'], $this->f['president'], $this->data, signaturePng());
    $step = $this->engine->currentStep($this->f['document']->fresh());

    expect(fn () => $this->engine->reject($step, $this->f['advisor'], ' '))->toThrow(DomainException::class, 'comment');

    $this->engine->reject($step, $this->f['advisor'], 'Not suitable.');
    expect($this->f['document']->fresh()->status)->toBe(DocumentStatus::Rejected)
        ->and(fn () => $this->engine->submit($this->f['document']->fresh(), $this->f['president'], $this->data, signaturePng()))->toThrow(DomainException::class);
});

it('rejects a forged signature payload', function () {
    $this->engine->submit($this->f['document'], $this->f['president'], $this->data, 'data:image/png;base64,'.base64_encode('<svg onload=alert(1)>'));
})->throws(DomainException::class, 'not valid');

it('audits every action', function () {
    $this->engine->submit($this->f['document'], $this->f['president'], $this->data, signaturePng());
    $this->engine->approve($this->engine->currentStep($this->f['document']->fresh()), $this->f['advisor'], signaturePng());

    $this->assertDatabaseHas('audit_log', ['action' => 'document.submitted'])
        ->assertDatabaseHas('audit_log', ['action' => 'approval.approved']);
});
