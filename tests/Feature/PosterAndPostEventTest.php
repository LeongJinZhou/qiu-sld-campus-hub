<?php

use App\Enums\DocumentStatus;
use App\Models\EventDocumentItem;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Events\PosterRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(fn () => $this->f = proposalFixture());

it('refuses a poster until every checklist item is confirmed', function () {
    Storage::fake('public');
    $ticked = array_fill_keys(array_keys(PosterRules::CHECKLIST), true);
    $partial = [...$ticked, 'real_photos' => false];
    $url = "/events/{$this->f['event']->slug}/poster";

    $this->actingAs($this->f['president'])->post($url, ['poster' => UploadedFile::fake()->image('p.png'), 'checklist' => $partial])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'AI-generated'));
    $this->actingAs($this->f['president'])->post($url, ['poster' => UploadedFile::fake()->image('p.png'), 'checklist' => $ticked])
        ->assertSessionHas('success');

    expect($this->f['event']->fresh()->poster_path)->not->toBeNull();
});

it('reminds the event committee about documents due within 2 days, once a day', function () {
    Notification::fake();
    $this->f['event']->update(['status' => DocumentStatus::Approved]);
    $this->f['event']->documentItems()->create(['kind' => 'receipts', 'due_on' => now()->addDay()]);
    $this->f['event']->documentItems()->create(['kind' => 'reel', 'due_on' => now()->addDays(10)]);

    $this->artisan('events:send-reminders')->assertSuccessful();
    $this->artisan('events:send-reminders')->assertSuccessful();

    Notification::assertSentTimes(DocumentNotification::class, 4); // 4 event committee members, once
    expect(EventDocumentItem::where('kind', 'reel')->value('reminded_at'))->toBeNull();
});

it('keeps uploaded event documents private to organisers', function () {
    Storage::fake('local');
    $item = $this->f['event']->documentItems()->create(['kind' => 'receipts', 'due_on' => now()->addWeek()]);

    $this->actingAs($this->f['president'])->post("/events/{$this->f['event']->slug}/documents/{$item->id}", ['file' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf')])
        ->assertSessionHas('success');
    $signed = URL::temporarySignedRoute('events.documents.download', now()->addMinutes(30), [$this->f['event'], $item]);
    // A signed link alone is not enough (the policy still applies), and an unsigned or expired link never works.
    $this->actingAs(User::factory()->create())->get($signed)->assertForbidden();
    $this->actingAs($this->f['president'])->get("/events/{$this->f['event']->slug}/documents/{$item->id}")->assertForbidden();
    $this->actingAs($this->f['president'])->get($signed)->assertOk();
    $this->travel(31)->minutes();
    $this->actingAs($this->f['president'])->get($signed)->assertForbidden();
});
