<?php

namespace App\Services\Clubs;

use App\Models\Club;
use App\Models\ClubApplication;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Approvals\SignatureStore;
use App\Services\AuditLogger;
use App\Services\Roles\CompulsoryPositionsCheck;
use App\Services\Roles\RoleService;
use App\Services\Time\SessionResolver;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * New-club registration: the proposed President fills in the application (name, category, aims, the four
 * compulsory officers, the advisor) and signs; the advisor signs; SLD approves. Until then the club does not exist:
 * no page, no members, no money (the first-opening lock). On approval it opens with its committee and advisor.
 * A returned application can be corrected and signed again.
 */
class ClubApplications
{
    public function __construct(
        private readonly SignatureStore $signatures,
        private readonly RoleService $roles,
        private readonly SessionResolver $sessions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, category_id: ?int, bio: string, objectives: string, officers: array<string, string>, advisor_id: int}  $data
     */
    public function submit(User $applicant, array $data, string $signature, string $strokes, ?ClubApplication $returned = null): ClubApplication
    {
        if (! $applicant->student?->isActive()) {
            throw new DomainException('Only active QIU students can apply to start a club.');
        }
        $name = trim($data['name']);
        $taken = Club::whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()
            || ClubApplication::whereIn('status', ['advisor', 'sld'])->whereKeyNot($returned?->id)->get()->contains(fn (ClubApplication $a) => mb_strtolower($a->data['name']) === mb_strtolower($name));
        if ($taken) {
            throw new DomainException("A club called {$name} already exists or is being applied for.");
        }
        $officers = [];
        foreach (CompulsoryPositionsCheck::POSITIONS as $position) {
            $student = Student::where('student_number', $data['officers'][$position] ?? '')->first();
            if (! $student?->isActive()) {
                throw new DomainException("{$position}: choose an active student.");
            }
            $officers[$position] = $student->student_number;
        }
        if ($officers['President'] !== $applicant->student->student_number) {
            throw new DomainException('The applicant is the proposed President.');
        }
        if (count(array_unique($officers)) < count($officers)) {
            throw new DomainException('Each of the four positions needs a different person.');
        }
        $advisor = User::whereKey($data['advisor_id'])->whereHas('staff', fn ($q) => $q->where('active', true))->first() ?? throw new DomainException('Choose a QIU staff member as the advisor.');

        return DB::transaction(function () use ($applicant, $data, $signature, $strokes, $returned, $officers, $advisor, $name): ClubApplication {
            $signed = $this->signatures->store($applicant, $signature, 'Proposed President', [], $strokes);
            $values = ['applicant_id' => $applicant->id, 'advisor_id' => $advisor->id, 'status' => 'advisor', 'applicant_signature_id' => $signed->id,
                'advisor_signature_id' => null, 'advisor_signed_at' => null, 'note' => null,
                'data' => ['name' => $name, 'category_id' => $data['category_id'] ?? null, 'bio' => trim($data['bio']), 'objectives' => trim($data['objectives']), 'officers' => $officers]];
            $application = $returned ? tap($returned)->update($values) : ClubApplication::create($values);
            $this->audit->log('club_application.submitted', $application, ['name' => $name]);
            $advisor->notify(new DocumentNotification("Sign a new club application: {$name}", "{$applicant->name} proposes you as the advisor. Read it and sign, or decline.", route('club-applications.show', $application)));

            return $application;
        });
    }

    public function advisorSign(ClubApplication $application, User $advisor, string $signature, string $strokes): void
    {
        $this->assertStep($application, 'advisor', $advisor->id === $application->advisor_id);
        $signed = $this->signatures->store($advisor, $signature, 'Club Advisor', [], $strokes);
        $application->update(['status' => 'sld', 'advisor_signature_id' => $signed->id, 'advisor_signed_at' => now()]);
        $this->audit->log('club_application.advisor_signed', $application);
        Notification::send(User::role(['admin', 'superadmin'])->get(), new DocumentNotification("New club application: {$application->data['name']}",
            'The advisor has signed. Review and approve or return it.', route('club-applications.show', $application)));
        $application->applicant->notify(new DocumentNotification('Your advisor signed', 'Your club application is now with SLD.', route('club-applications.show', $application)));
    }

    /** The advisor declines, or SLD returns it for changes: back to the applicant, who corrects it and signs again. */
    public function returnToApplicant(ClubApplication $application, User $by, string $note): void
    {
        $allowed = ($application->status === 'advisor' && $by->id === $application->advisor_id) || ($application->status === 'sld' && $by->isSldAdmin());
        $this->assertStep($application, $application->status, $allowed);
        $application->update(['status' => 'returned', 'note' => $note]);
        $application->applicant->notify(new DocumentNotification("Club application returned: {$application->data['name']}", $note, route('club-applications.show', $application)));
    }

    /** SLD approves (the club opens, committee and advisor appointed) or rejects. */
    public function decide(ClubApplication $application, User $sld, bool $approve, ?string $note): ?Club
    {
        $this->assertStep($application, 'sld', $sld->isSldAdmin());
        if (! $approve) {
            $application->update(['status' => 'rejected', 'note' => $note, 'decided_by' => $sld->id, 'decided_at' => now()]);
            $application->applicant->notify(new DocumentNotification("Club application not approved: {$application->data['name']}", (string) $note, route('club-applications.show', $application)));

            return null;
        }
        $club = DB::transaction(function () use ($application, $sld, $note): Club {
            $d = $application->data;
            $club = Club::create(['name' => $d['name'], 'slug' => Str::slug($d['name']), 'category_id' => $d['category_id'], 'bio' => $d['bio'], 'status' => 'active', 'established_year' => now()->year]);
            $session = $this->sessions->current();
            foreach ($d['officers'] as $position => $number) {
                $this->roles->assignClubPosition($club, $session, Student::where('student_number', $number)->firstOrFail()->ensureUser(), $position);
            }
            $this->roles->addAdvisor($club, $session, $application->advisor);
            $application->update(['status' => 'approved', 'note' => $note, 'decided_by' => $sld->id, 'decided_at' => now(), 'club_id' => $club->id]);
            $this->audit->log('club_application.approved', $application, ['club_id' => $club->id]);

            return $club;
        });
        $people = $club->roleAssignments()->with('user')->get()->pluck('user');
        Notification::send($people, new DocumentNotification("{$club->name} is open", 'SLD approved the club. Its page, membership and finance are now available.', route('clubs.show', $club)));

        return $club;
    }

    private function assertStep(ClubApplication $application, string $status, bool $allowed): void
    {
        if ($application->status !== $status || ! $allowed) {
            throw new DomainException('This application is not waiting for you.');
        }
    }

    /** @return list<array{id: int, name: string}> staff who can be proposed as advisor */
    public function advisors(): array
    {
        return Staff::with('user')->where('active', true)->whereNotNull('user_id')->orderBy('name')->get()
            ->map(fn (Staff $s) => ['id' => $s->user_id, 'name' => $s->name.($s->department ? " · {$s->department}" : '')])->values()->all();
    }
}
