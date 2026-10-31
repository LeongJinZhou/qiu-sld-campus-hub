<?php

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Event;
use App\Notifications\DocumentNotification;
use App\Services\Events\ProposalDrafts;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->secretary = $this->club->roleAssignments()->where('position', 'Secretary')->first()->user;
    $this->treasurer = $this->club->roleAssignments()->where('position', 'Treasurer')->first()->user;
});

function newEvent(object $t, $user, string $name = 'Sports Day')
{
    return $t->actingAs($user)->post("/clubs/{$t->club->slug}/events", [
        'name' => $name, 'starts_at' => now()->addMonths(4)->setTime(9, 0)->toDateTimeString(),
        'ends_at' => now()->addMonths(4)->setTime(12, 0)->toDateTimeString(), 'expected_participants' => 30,
    ]);
}

it('tells the committee a proposal started and allows at most 5 open proposals per club', function () {
    Notification::fake();
    newEvent($this, $this->f['president'])->assertRedirect();
    Notification::assertSentTo($this->secretary, DocumentNotification::class, fn ($n) => str_contains($n->title, 'started a proposal: Sports Day'));
    Notification::assertNotSentTo($this->f['president'], DocumentNotification::class);

    // The fixture already has one draft; with this one that's 2. Three more reach the limit of 5.
    foreach (range(1, 3) as $i) {
        newEvent($this, $this->f['president'], "Event {$i}")->assertRedirect();
    }
    expect(app(ProposalDrafts::class)->open($this->club))->toHaveCount(5);
    newEvent($this, $this->f['president'], 'One too many')->assertSessionHas('error', fn ($m) => str_contains($m, '5 proposals in draft'));
    expect(Event::where('name', 'One too many')->exists())->toBeFalse();

    // Discarding an unsubmitted draft frees a place.
    $event = Event::where('name', 'Event 3')->sole();
    $this->actingAs($this->secretary)->delete("/events/{$event->slug}/proposal")->assertRedirect();
    newEvent($this, $this->f['president'], 'Now it fits')->assertRedirect();
});

it('lets another committee member continue the shared draft and submit it as themselves', function () {
    $event = $this->f['event'];
    // The President starts; autosave keeps quiet and records who saved last.
    $this->actingAs($this->f['president'])->put("/events/{$event->slug}/proposal", [...completeProposal($this->f, ['summary' => 'Started by the President']), 'autosave' => 1])
        ->assertSessionMissing('success');
    $this->actingAs($this->treasurer)->get("/events/{$event->slug}/proposal")->assertInertia(fn ($p) => $p
        ->where('data.summary', 'Started by the President')->where('lastEdit.by', $this->f['president']->name));

    // The Treasurer carries on and submits; the first signature is theirs, with their position.
    $this->actingAs($this->treasurer)->post("/events/{$event->slug}/proposal/submit", [...completeProposal($this->f), 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes()]);
    $first = Document::where('event_id', $event->id)->where('type', DocumentType::Proposal)->sole()->approvals()->where('step_no', 1)->with('signature')->first();
    expect($first->approver_user_id)->toBe($this->treasurer->id)->and($first->signature->position_at_time)->toBe('Treasurer');
});

it('asks chosen committee members to submit, with a note', function () {
    Notification::fake();
    $event = $this->f['event'];
    $this->actingAs($this->f['president'])->post("/events/{$event->slug}/proposal/ask", ['user_ids' => [$this->secretary->id], 'note' => 'Please check the budget'])
        ->assertSessionHas('success');
    Notification::assertSentTo($this->secretary, DocumentNotification::class, fn ($n) => str_contains($n->body, 'Please check the budget'));
    Notification::assertNotSentTo($this->treasurer, DocumentNotification::class);
});
