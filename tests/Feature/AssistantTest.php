<?php

use App\Enums\DocumentStatus;
use App\Models\AssistantMessage;
use App\Models\Club;
use App\Models\Event;
use App\Services\Ai\AiClient;
use App\Services\Ai\Assistant;
use App\Services\Time\SessionResolver;
use Inertia\Testing\AssertableInertia as Assert;

it('answers with links only to records the person may open, whatever the model returns', function () {
    $f = proposalFixture();
    $other = Club::create(['name' => 'Secret Club']);
    $hidden = Event::create(['club_id' => $other->id, 'session_id' => app(SessionResolver::class)->current()->id, 'name' => 'Hidden Draft', 'status' => DocumentStatus::Draft,
        'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(2)]);
    $student = otherStudent();

    $records = collect(app(Assistant::class)->records($student));
    expect($records->pluck('title'))->not->toContain('Hidden Draft')->not->toContain($f['event']->name)
        ->and(collect(app(Assistant::class)->records($f['president']))->pluck('title'))->toContain($f['event']->name)->toContain($f['club']->name);

    // A model that tries to cite an unknown id gets nothing linked for it.
    $fake = Mockery::mock(AiClient::class);
    $fake->shouldReceive('json')->andReturn(['answer' => 'Your calendar shows it.', 'links' => ['r2', 'r999', 'events/'.$hidden->slug]]);
    app()->instance(AiClient::class, $fake);
    $this->actingAs($student)->post('/assistant', ['question' => 'When is my next event?'])->assertRedirect();
    $reply = AssistantMessage::where('role', 'assistant')->firstOrFail();
    expect($reply->links)->toHaveCount(1)->and($reply->links[0]['title'])->toBe('Campus calendar');
});

it('falls back to matching words when AI is off, and lets the person delete their history', function () {
    config(['services.anthropic.key' => null]);
    $f = proposalFixture();
    $this->actingAs($f['president'])->post('/assistant', ['question' => 'fixture club finance'])->assertRedirect();
    $reply = AssistantMessage::where('role', 'assistant')->firstOrFail();
    expect(collect($reply->links)->pluck('title'))->toContain('Fixture Club: finance');

    $this->actingAs($f['president'])->get('/assistant')->assertInertia(fn (Assert $p) => $p->component('Assistant/Index')->has('messages', 2));
    // Someone else cannot see or delete it.
    $this->actingAs(otherStudent())->get('/assistant')->assertInertia(fn (Assert $p) => $p->has('messages', 0));
    $this->actingAs(otherStudent('STD20249997'))->delete("/assistant/{$reply->id}")->assertNotFound();

    $this->actingAs($f['president'])->delete("/assistant/{$reply->id}");
    expect(AssistantMessage::count())->toBe(1);
    $this->actingAs($f['president'])->delete('/assistant')->assertSessionHas('success');
    expect(AssistantMessage::count())->toBe(0);
});
