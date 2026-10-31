<?php

namespace Database\Seeders;

use App\Enums\GlobalRole;
use App\Models\Club;
use App\Models\ClubCategory;
use App\Models\ClubFee;
use App\Models\Student;
use App\Models\User;
use App\Services\Camsys\CamsysSync;
use App\Services\Roles\CompulsoryPositionsCheck;
use App\Services\Roles\RoleService;
use App\Services\Time\SessionResolver;
use Illuminate\Database\Seeder;

/** Six demo clubs with committees drawn from the 10 dummy camsys students. */
class DemoClubsSeeder extends Seeder
{
    /** Membership fee per club for the demo session, in sen (0 = free). */
    private const FEES = [1000, 1500, 0, 2000, 0, 0];

    /** Name, bio, Instagram handle, category, established year, logo glyph. */
    public const CLUBS = [
        ['GPS Club', 'Student Consumer Movement', 'gps_qiu', 'Community Service', 2019, '🛒'],
        ['Computer Science Society', 'Code, build and ship together.', 'qiu_css', 'Academic', 2016, '💻'],
        ['Music Club', 'Jams, recitals and open mics.', 'qiu_music', 'Arts & Culture', 2015, '🎸'],
        ['Badminton Club', 'Weekly training and inter-varsity games.', 'qiu_badminton', 'Sports', 2014, '🏸'],
        ['Debate Society', 'Think fast, speak clearly.', 'qiu_debate', 'Leadership', 2018, '🎤'],
        ['Volunteer Club', 'Serving Ipoh, one project at a time.', 'qiu_volunteers', 'Community Service', 2017, '🤝'],
    ];

    public function run(CamsysSync $camsys, RoleService $roles, SessionResolver $sessions): void
    {
        $camsys->syncAll('seed');

        foreach (Student::all() as $student) {
            $user = User::firstOrCreate(['email' => $student->email], ['name' => $student->name]);
            $user->assignRole(GlobalRole::Student->value);
            $student->update(['user_id' => $user->id]);
        }

        $category = fn (string $name) => ClubCategory::where('name', $name)->value('id');

        $session = $sessions->current();
        $active = User::whereHas('student', fn ($q) => $q->where('status', 'active'))->orderBy('id')->get()->values();
        $advisors = User::where('email', 'like', 'advisor%.demo@qiu.edu.my')->orderBy('email')->get()->values();
        $sld = User::where('email', 'sld.officer.demo@qiu.edu.my')->first();

        foreach (self::CLUBS as $i => [$name, $bio, $handle, $categoryName, $established, $glyph]) {
            $club = Club::firstOrCreate(['name' => $name], [
                'logo_path' => DemoMedia::logo($name, $i, $glyph),
                'bio' => $bio,
                'instagram_handle' => $handle,
                'category_id' => $category($categoryName),
                'established_year' => $established,
            ]);

            foreach (CompulsoryPositionsCheck::POSITIONS as $p => $position) {
                $roles->assignClubPosition($club, $session, $active[($i + $p) % $active->count()], $position);
            }
            $roles->assignClubPosition($club, $session, $active[($i + 4) % $active->count()], 'Publicity Director');

            $advisor = $advisors[$i % $advisors->count()];
            if (! $roles->belongsToClub($advisor, $club, $session)) {
                $roles->addAdvisor($club, $session, $advisor);
            }

            // An SLD-approved fee for the session so students can join straight away (some clubs free, some paid).
            $treasurer = $club->roleAssignments()->where('session_id', $session->id)->where('position', 'Treasurer')->first()?->user;
            ClubFee::firstOrCreate(['club_id' => $club->id, 'session_id' => $session->id], [
                'amount_sen' => self::FEES[$i] ?? 0,
                'account_name' => (self::FEES[$i] ?? 0) > 0 ? "{$name} (QIU)" : null,
                'set_by' => $treasurer?->id,
                'approved_by' => $sld?->id,
                'approved_at' => now(),
            ]);
        }
    }
}
