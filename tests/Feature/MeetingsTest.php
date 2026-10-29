<?php

use App\Enums\ChainRole;
use App\Enums\DocumentStatus;
use App\Jobs\DraftMinutes;
use App\Models\CcaRecord;
use App\Models\Meeting;
use App\Models\Student;
use App\Models\User;
use App\Services\Ai\MinutesDrafter;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Documents\DocumentExporter;
use App\Services\Documents\DocxRenderer;
use App\Services\Meetings\Meetings;
use App\Services\Meetings\MinutesForm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->secretary = $this->club->roleAssignments()->where('position', 'Secretary')->first()->user;
});

function meeting(object $t, string $kind = 'committee', bool $online = false): Meeting
{
    return app(Meetings::class)->create($t->club, $t->secretary, [
        'kind' => $kind, 'title' => 'Weekly meeting', 'starts_at' => now()->addDay()->setTime(20, 0)->toDateTimeString(),
        'ends_at' => now()->addDay()->setTime(21, 30)->toDateTimeString(), 'venue' => 'Student Lounge', 'online' => $online,
    ]);
}

function minutesPayload(): array
{
    return ['purpose' => 'Weekly committee meeting', 'summary' => ['Attendance was taken.', 'Budget & tasks discussed.'], 'actions' => [['task' => 'Design poster', 'pic' => 'Wei Zhang', 'deadline' => '10 Nov 2026']], 'next_date' => '2026-11-12', 'next_time' => '20:00', 'next_venue' => 'Student Lounge', 'notes' => ''];
}

it('creates a Google Meet on the organiser’s own calendar, refreshing the token, and invites the committee', function () {
    $this->secretary->forceFill(['google_refresh_token' => 'refresh-1'])->save();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'expires_in' => 3600]),
        'www.googleapis.com/calendar/*' => Http::response(['id' => 'evt-1', 'hangoutLink' => 'https://meet.google.com/abc-defg-hij']),
    ]);

    $meeting = meeting($this, online: true);

    expect($meeting->meet_link)->toBe('https://meet.google.com/abc-defg-hij')
        ->and($this->secretary->fresh()->google_token)->toBe('access-1');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'conferenceDataVersion=1')
        && $request->hasHeader('Authorization', 'Bearer access-1')
        && count($request['attendees']) >= 5
        && $request['conferenceData']['createRequest']['conferenceSolutionKey']['type'] === 'hangoutsMeet');
});

it('asks for a calendar connection before creating an online meeting', function () {
    meeting($this, online: true);
})->throws(DomainException::class, 'Connect your QIU Google calendar');

it('stores Google tokens encrypted and hidden', function () {
    $this->secretary->forceFill(['google_token' => 'secret-token'])->save();

    expect(DB::table('users')->where('id', $this->secretary->id)->value('google_token'))->not->toContain('secret-token')
        ->and($this->secretary->toArray())->not->toHaveKey('google_token');
});

it('runs minutes through Preparer → President → Advisor → SLD and prints the 13 template', function () {
    $meeting = meeting($this);
    $document = $meeting->minutes;
    app(MinutesForm::class)->saveDraft($document, minutesPayload(), $this->secretary->id);
    app(ApprovalEngine::class)->submit($document->fresh(), $this->secretary, minutesPayload(), signaturePng(), signatureStrokes());

    expect($document->approvals()->pluck('role')->all())->toBe([ChainRole::Preparer, ChainRole::President, ChainRole::Advisor, ChainRole::SldOfficer]);
    approveRemainingSteps($document);

    $path = app(DocumentExporter::class)->export($document->fresh(), 'docx');
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    expect(DocxRenderer::remainingPlaceholders($path))->toBe([])
        ->and((new DOMDocument)->loadXML($xml))->toBeTrue()
        ->and($xml)->toContain('Fixture Club')->toContain('Budget &amp; tasks discussed.')->toContain('Design poster')->toContain('Dr Lim Siew Ling')
        ->not->toContain('Weekly club training')->not->toContain('Angie');
    unlink($path);
});

it('needs attendance before weekly activity minutes, then awards CCA on approval', function () {
    $meeting = meeting($this, kind: 'activity');
    $this->actingAs($this->secretary)->post("/meetings/{$meeting->id}/minutes/submit", [...minutesPayload(), 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes()])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'attendance'));

    $student = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    $this->club->memberships()->create(['user_id' => $student->id, 'status' => 'active']);
    $this->actingAs($this->secretary)->post("/meetings/{$meeting->id}/attendance", ['present' => [$student->id, $this->secretary->id]]);
    $this->actingAs($this->secretary)->post("/meetings/{$meeting->id}/minutes/submit", [...minutesPayload(), 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes()])
        ->assertSessionHas('success');
    approveRemainingSteps($meeting->minutes);

    expect($meeting->minutes->fresh()->status)->toBe(DocumentStatus::Approved)
        ->and(CcaRecord::where('meeting_id', $meeting->id)->pluck('hours', 'user_id')->all())->toEqual([$student->id => 1.5, $this->secretary->id => 1.5]);
});

it('drafts minutes from notes with AI in the queue', function () {
    $meeting = meeting($this);
    $this->mock(MinutesDrafter::class)->shouldReceive('draft')->once()->andReturn([
        'purpose' => 'Plan the beach cleanup', 'summary' => ['Permits discussed'], 'actions' => [['task' => 'Book bus', 'pic' => 'Amirul', 'deadline' => 'N/A']],
    ]);

    $this->actingAs($this->secretary)->post("/meetings/{$meeting->id}/minutes/draft", [...minutesPayload(), 'notes' => '- permits; Amirul books bus'])->assertSessionHas('success');

    $data = app(MinutesForm::class)->current($meeting->minutes->fresh());
    expect($data['purpose'])->toBe('Plan the beach cleanup')->and($data['actions'][0]['pic'])->toBe('Amirul')
        ->and(Cache::get(DraftMinutes::statusKey($meeting->minutes->id)))->toBe('done');
});

it('keeps meetings to the club side', function () {
    $meeting = meeting($this);
    $this->actingAs(User::factory()->create())->get("/meetings/{$meeting->id}")->assertForbidden();
    $this->actingAs($this->secretary)->get("/meetings/{$meeting->id}")->assertOk();
});
