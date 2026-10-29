<?php

use App\Enums\DocumentType;
use App\Models\Agm;
use App\Models\Club;
use App\Models\Document;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Student;
use App\Models\User;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Approvals\ApproverResolver;
use App\Services\Camsys\CamsysSync;
use App\Services\Events\ProposalForm;
use App\Services\Roles\CompulsoryPositionsCheck;
use App\Services\Roles\RoleService;
use App\Services\Time\SessionResolver;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\StaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Uploads and signatures go to throwaway disks, never into the developer's real storage folder.
    ->beforeEach(function () {
        Storage::fake('local');
        Storage::fake('public');
    })
    ->in('Feature');

/** Loads the sample camsys dump into the in-memory camsys connection and seeds lookups. */
function seedCamsys(): void
{
    Artisan::call('camsys:import-dump');
    (new SettingsSeeder)->run();
}

function superAdmin(): User
{
    return tap(User::factory()->create())->assignRole('superadmin');
}

function sldOfficer(): User
{
    return tap(User::factory()->create())->assignRole('admin');
}

function signaturePng(): string
{
    $image = imagecreatetruecolor(20, 10);
    ob_start();
    imagepng($image);

    return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
}

/**
 * A club with a full committee and advisor, plus an event with a full event committee and a draft proposal.
 *
 * @return array{club: Club, event: Event, document: Document, president: User, advisor: User}
 */
function proposalFixture(array $eventAttributes = []): array
{
    seedCamsys();
    app(CamsysSync::class)->syncAll('test');
    (new StaffSeeder)->run();

    $session = app(SessionResolver::class)->current();
    $roles = app(RoleService::class);
    $users = Student::where('status', 'active')->orderBy('student_number')->get()->map(function ($s) {
        $user = User::firstOrCreate(['email' => $s->email], ['name' => $s->name]);
        $s->update(['user_id' => $user->id]);

        return $user->load('student');
    })->values();

    $club = Club::create(['name' => 'Fixture Club']);
    foreach (CompulsoryPositionsCheck::POSITIONS as $i => $position) {
        $roles->assignClubPosition($club, $session, $users[$i], $position);
    }
    $advisor = User::where('email', 'advisor1.demo@qiu.edu.my')->firstOrFail();
    $roles->addAdvisor($club, $session, $advisor);

    $event = Event::create([
        'club_id' => $club->id,
        'session_id' => $session->id,
        'name' => 'Beach Cleanup',
        'starts_at' => now()->addMonths(4)->setTime(8, 0),
        'ends_at' => now()->addMonths(4)->setTime(12, 30),
        'expected_participants' => 40,
        ...$eventAttributes,
    ]);
    foreach (CompulsoryPositionsCheck::POSITIONS as $i => $position) {
        $roles->assignEventPosition($event, $users[$i + 4], $position, "Handles {$position} duties");
    }

    $document = Document::create([
        'type' => DocumentType::Proposal,
        'club_id' => $club->id,
        'event_id' => $event->id,
        'session_id' => $session->id,
        'created_by' => $users[0]->id,
    ]);

    return ['club' => $club, 'event' => $event, 'document' => $document, 'president' => $users[0], 'advisor' => $advisor];
}

/** Signs off every remaining step of the chain with whoever may act on it. */
function approveRemainingSteps(Document $document): void
{
    $engine = app(ApprovalEngine::class);
    $resolver = app(ApproverResolver::class);
    while ($step = $engine->currentStep($document->fresh())) {
        $engine->approve($step, $resolver->candidates($document, $step->role)->firstOrFail(), signaturePng());
    }
}

/** A recorded e-signature as the pad sends it: {w, h, device, strokes: [[[x, y, t, pressure], ...]]}. */
function signatureStrokes(string $device = 'touch'): string
{
    $stroke = array_map(fn (int $i) => [10.0 + $i * 8, 60.0 - ($i % 5) * 6, $i * 16, 0.6], range(0, 24));

    return (string) json_encode(['w' => 400, 'h' => 180, 'device' => $device, 'strokes' => [$stroke]]);
}

/** A camera-style JPEG containing a QR code for $text (for the cash handover photo check). */
function qrPhoto(string $text): UploadedFile
{
    $matrix = Encoder::encode($text, ErrorCorrectionLevel::M())->getMatrix();
    $n = $matrix->getWidth();
    $qr = imagecreatetruecolor($n * 8 + 64, $n * 8 + 64);
    imagefill($qr, 0, 0, imagecolorallocate($qr, 255, 255, 255));
    $black = imagecolorallocate($qr, 0, 0, 0);
    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            if ($matrix->get($x, $y) === 1) {
                imagefilledrectangle($qr, 32 + $x * 8, 32 + $y * 8, 39 + $x * 8, 39 + $y * 8, $black);
            }
        }
    }
    $photo = imagecreatetruecolor(1000, 750);
    imagefill($photo, 0, 0, imagecolorallocate($photo, 60, 130, 70)); // "cash" green background
    imagecopyresampled($photo, $qr, 180, 120, 0, 0, 480, 480, imagesx($qr), imagesy($qr));
    $path = tempnam(sys_get_temp_dir(), 'qr').'.jpg';
    imagejpeg($photo, $path, 85);

    return new UploadedFile($path, 'handover.jpg', 'image/jpeg', null, true);
}

/** Plain text of a DOCX body, one line per paragraph. */
function docxText(string $path): string
{
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    return strip_tags(str_replace('</w:p>', "\n", $xml));
}

/** Opens a period for the current session and schedules the club's AGM for tomorrow. */
function openPeriodAndAgm(object $t): Agm
{
    $t->actingAs($t->sld)->post('/admin/agm/periods', [
        'session' => app(SessionResolver::class)->current()->name,
        'starts_on' => today()->toDateString(), 'agm_deadline' => today()->addWeeks(3)->toDateString(),
        'report_deadline' => today()->addWeeks(5)->toDateString(),
    ])->assertSessionHas('success');
    $t->actingAs($t->secretary)->post("/clubs/{$t->club->slug}/agm", [
        'starts_at' => now()->addDay()->setTime(16, 0)->toDateTimeString(), 'ends_at' => now()->addDay()->setTime(18, 0)->toDateTimeString(),
        'venue' => 'Lecture Hall 2', 'online' => false,
    ])->assertSessionHas('success');

    return Agm::sole();
}

/** @return list<User> students who are not on the committee */
function plainMembers(object $t, int $count, string $status = 'active'): array
{
    $committee = $t->club->roleAssignments()->pluck('user_id');

    return Student::where('status', 'active')->whereNotIn('user_id', $committee)->whereNotNull('user_id')->with('user')->orderBy('student_number')
        ->get()->reject(fn ($s) => Membership::where('user_id', $s->user_id)->exists())->take($count)
        ->map(function (Student $s) use ($t, $status) {
            Membership::create(['club_id' => $t->club->id, 'user_id' => $s->user_id, 'status' => $status, 'starts_on' => today()->subMonths(2), 'expires_on' => today()->addMonths(10)]);

            return $s->user;
        })->values()->all();
}

function completeProposal(array $f, array $overrides = []): array
{
    $data = app(ProposalForm::class)->defaults($f['event']);

    return array_replace_recursive($data, [
        'summary' => 'Clean the beach.', 'venue' => 'Pantai Remis', 'introduction' => 'SDG 14.', 'eligibility' => 'All students',
        'nature' => ['environmental'], 'source_of_fund' => ['club'], 'objectives' => ['Clean', 'Learn', 'Bond'],
        'activities' => [['name' => 'Cleanup', 'description' => 'Pick litter']],
        'itinerary' => [['start' => '08:00', 'end' => '12:30', 'agenda' => 'Cleanup']],
        'budget' => ['revenue' => [['description' => 'Club fund', 'price_sen' => 50000, 'quantity' => 1]], 'expenses' => [['description' => 'Gloves', 'price_sen' => 500, 'quantity' => 40]]],
        'conclusion' => 'Good for all.',
    ], $overrides);
}
