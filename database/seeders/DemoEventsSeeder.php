<?php

namespace Database\Seeders;

use App\Enums\DocumentType;
use App\Models\Club;
use App\Models\Document;
use App\Models\Event;
use App\Models\Post;
use App\Models\Student;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Approvals\ApproverResolver;
use App\Services\Events\ProposalForm;
use App\Services\Roles\CompulsoryPositionsCheck;
use App\Services\Roles\RoleService;
use App\Services\Time\SessionResolver;
use Illuminate\Database\Seeder;

/** Local demo: one fully approved event in the feed, one proposal waiting for its advisor. */
class DemoEventsSeeder extends Seeder
{
    public function run(RoleService $roles, ApprovalEngine $engine, ApproverResolver $approvers, ProposalForm $form, SessionResolver $sessions): void
    {
        $students = Student::where('status', 'active')->orderBy('student_number')->get()->map->ensureUser()->values();
        $signature = $this->signature();

        $events = [
            ['GPS Club', 'Beach Cleanup @ Pantai Remis', 'Pantai Remis, Perak', true, 4, 40, true],
            ['Computer Science Society', 'Hack QIU 2027', 'The Curve, Level 3', false, 5, 80, false],
        ];

        foreach ($events as [$clubName, $name, $venue, $outdoor, $months, $participants, $approve]) {
            $club = Club::where('name', $clubName)->firstOrFail();
            $start = now()->addMonthsNoOverflow($months)->next('Saturday')->setTime(8, 0);
            $event = Event::create([
                'club_id' => $club->id, 'session_id' => $sessions->current()->id, 'name' => $name,
                'starts_at' => $start, 'ends_at' => $start->copy()->setTime(12, 0), 'venue' => $venue,
                'expected_participants' => $participants, 'is_outdoor' => $outdoor,
                'summary' => $outdoor ? 'Join us to clean up the beach and learn about marine conservation.' : 'A 4-hour hackathon for all programmes.',
            ]);
            foreach (CompulsoryPositionsCheck::POSITIONS as $i => $position) {
                $roles->assignEventPosition($event, $students[($i + $months) % $students->count()], $position, "{$position} duties");
            }

            $creator = $club->roleAssignments()->where('position', 'President')->firstOrFail()->user;
            $document = Document::create([
                'type' => DocumentType::Proposal, 'club_id' => $club->id, 'event_id' => $event->id,
                'session_id' => $event->session_id, 'created_by' => $creator->id,
            ]);
            $data = array_replace($form->defaults($event), [
                'nature' => [$outdoor ? 'environmental' : 'competition'], 'sdgs' => $outdoor ? [14] : [4, 9], 'source_of_fund' => ['club'],
                'introduction' => $event->summary, 'objectives' => ['Build teamwork', 'Serve the community', 'Earn CCA hours'],
                'eligibility' => 'All QIU students', 'activities' => [['name' => 'Main activity', 'description' => $event->summary]],
                'itinerary' => [['start' => '08:00', 'end' => '08:30', 'agenda' => 'Arrival & Registration'], ['start' => '08:30', 'end' => '12:00', 'agenda' => 'Main activity']],
                'budget' => ['revenue' => [['description' => 'Club Fund', 'price_sen' => 60000, 'quantity' => 1]], 'expenses' => [['description' => 'Refreshments', 'price_sen' => 1000, 'quantity' => $participants]]],
                'conclusion' => 'We look forward to SLD approval.',
            ]);
            $form->saveDraft($document, $data, $creator->id);
            $engine->submit($document->fresh(), $creator, $data, $signature);

            if ($approve) {
                while ($step = $engine->currentStep($document->fresh())) {
                    $engine->approve($step, $approvers->candidates($document, $step->role)->firstOrFail(), $signature);
                }
            }
        }

        Post::create([
            'club_id' => Club::where('name', 'Music Club')->value('id'), 'type' => Post::TYPE_GENERAL,
            'caption' => 'Open mic every Friday 8pm at the Student Lounge. Bring your instrument!', 'published_at' => now()->subHours(5),
        ]);
    }

    private function signature(): string
    {
        $image = imagecreatetruecolor(240, 80);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagesetthickness($image, 3);
        imageline($image, 20, 60, 220, 20, imagecolorallocate($image, 26, 26, 31));
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }
}
