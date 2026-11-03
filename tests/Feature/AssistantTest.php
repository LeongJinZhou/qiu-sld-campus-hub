<?php

use App\Enums\DocumentStatus;
use App\Models\AssistantMessage;
use App\Models\Club;
use App\Models\Event;
use App\Services\Ai\AiClient;
use App\Services\Ai\AssistantKnowledge;
use App\Services\Finance\Ledger;
use App\Services\Time\SessionResolver;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['services.anthropic.key' => null]);
    $this->f = proposalFixture();
    $this->other = Club::create(['name' => 'Secret Club']);
    $this->hidden = Event::create(['club_id' => $this->other->id, 'session_id' => app(SessionResolver::class)->current()->id, 'name' => 'Hidden Draft',
        'status' => DocumentStatus::Draft, 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(2)]);
    $this->open = Event::create(['club_id' => $this->other->id, 'session_id' => app(SessionResolver::class)->current()->id, 'name' => 'Open Day',
        'status' => DocumentStatus::Approved, 'fee_sen' => 500, 'starts_at' => now()->addWeeks(2), 'ends_at' => now()->addWeeks(2)->addHours(2)]);
    $ledger = app(Ledger::class);
    foreach ([$this->f['club'], $this->other] as $club) {
        $ledger->setOpeningBalance($club, 10000, now()->subMonth(), null, $this->f['president']);
    }
    $this->titles = fn ($user) => collect(app(AssistantKnowledge::class)->for($user))->pluck('title');
});

it('gives a student fees and approved events, but no club\'s money, members or draft events', function () {
    $titles = ($this->titles)(otherStudent());
    expect($titles)->toContain('Secret Club')->toContain('Open Day')->not->toContain('Hidden Draft')
        ->and($titles->filter(fn ($t) => str_contains($t, ': money') || str_contains($t, 'members and payments')))->toBeEmpty();
    $open = collect(app(AssistantKnowledge::class)->for(otherStudent('STD20249913')))->firstWhere('title', 'Open Day');
    expect($open['text'])->toContain('fee RM 5.00');
});

it('gives a committee its own club\'s internal facts and never another club\'s', function () {
    $titles = ($this->titles)($this->f['president']);
    expect($titles)->toContain('Fixture Club: money')->toContain('Fixture Club: members and payments')
        ->not->toContain('Secret Club: money')->not->toContain('Hidden Draft');
});

it('gives SLD every club\'s internal facts and the total', function () {
    $titles = ($this->titles)(sldOfficer());
    expect($titles)->toContain('Fixture Club: money')->toContain('Secret Club: money')->toContain('All clubs: total money')->toContain('Hidden Draft');
});

it('answers from retrieved facts, links only what the person may open, and offers follow-ups', function () {
    $student = otherStudent();
    $fake = Mockery::mock(AiClient::class);
    $fake->shouldReceive('json')->withArgs(function ($prompt) {
        // The prompt carries only this person's facts: the other club's draft and money never reach the model.
        return str_contains($prompt, 'Open Day') && ! str_contains($prompt, 'Hidden Draft') && ! str_contains($prompt, 'Balance RM');
    })->andReturn(['answer' => 'Open Day costs RM 5.', 'links' => ['r999', 'events/'.$this->hidden->slug, ...collect(app(AssistantKnowledge::class)->for($student))->where('title', 'Open Day')->pluck('id')], 'followups' => ['Is registration open?']]);
    app()->instance(AiClient::class, $fake);

    $this->actingAs($student)->post('/assistant', ['question' => 'How much is the Open Day fee?'])->assertSessionHasNoErrors();
    $reply = AssistantMessage::where('role', 'assistant')->sole();
    expect($reply->links['items'])->toHaveCount(1)->and($reply->links['items'][0]['title'])->toBe('Open Day')
        ->and($reply->links['followups'])->toBe(['Is registration open?']);
    $this->actingAs($student)->get('/assistant')->assertInertia(fn (Assert $p) => $p->where('messages.1.followups.0', 'Is registration open?'));
});

it('refuses abusive questions, however they are spelt, and stores nothing', function () {
    $student = otherStudent();
    foreach (['f_u_c_k this club', 'sohai la you', 'kepala bapak kau'] as $q) {
        $this->actingAs($student)->post('/assistant', ['question' => $q])->assertSessionHasErrors('question');
    }
    expect(AssistantMessage::count())->toBe(0);
});

it('falls back to the matching facts when AI is off, and lets the person delete their history', function () {
    $this->actingAs($this->f['president'])->post('/assistant', ['question' => 'fixture club money balance']);
    $reply = AssistantMessage::where('role', 'assistant')->sole();
    expect($reply->content)->toContain('Balance RM')->and(collect($reply->links['items'])->pluck('title'))->toContain('Fixture Club: money');

    $this->actingAs(otherStudent())->get('/assistant')->assertInertia(fn (Assert $p) => $p->has('messages', 0));
    $this->actingAs(otherStudent('STD20249997'))->delete("/assistant/{$reply->id}")->assertNotFound();
    $this->actingAs($this->f['president'])->delete('/assistant')->assertSessionHas('success');
    expect(AssistantMessage::count())->toBe(0);
});
