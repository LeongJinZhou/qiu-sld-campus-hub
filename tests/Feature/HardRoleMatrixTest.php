<?php

use App\Enums\ApproverRole;
use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use App\Models\Club;
use App\Models\ClubFee;
use App\Models\Membership;
use App\Models\RoleAssignment;
use App\Models\Staff;
use App\Models\User;
use App\Services\Roles\RoleService;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;

/** Explicit access expectations, independent of the policies being tested. */
function hardRoles(): array
{
    return ['guest', 'student', 'member', 'president', 'vice_president', 'secretary', 'treasurer',
        'event_president', 'event_treasurer', 'helper', 'advisor', 'staff', 'assistant_registrar',
        'registrar', 'bursar', 'coo', 'dean', 'admin', 'superadmin', 'other_committee', 'former_committee'];
}

function hardActor(string $role, array $f, Club $other): ?User
{
    $clubPositions = ['president' => 'President', 'vice_president' => 'Vice President', 'secretary' => 'Secretary', 'treasurer' => 'Treasurer'];
    if (isset($clubPositions[$role])) {
        return $f['club']->roleAssignments()->where('position', $clubPositions[$role])->firstOrFail()->user;
    }
    if (in_array($role, ['event_president', 'event_treasurer'], true)) {
        return $f['event']->committee()->where('position', $role === 'event_president' ? 'President' : 'Treasurer')->firstOrFail()->user;
    }
    if ($role === 'guest') {
        return null;
    }
    if ($role === 'advisor') {
        return $f['advisor'];
    }
    if (ApproverRole::tryFrom($role)) {
        return Staff::where('approver_role', $role)->firstOrFail()->user;
    }
    if ($role === 'admin' || $role === 'superadmin') {
        return $role === 'admin' ? sldOfficer() : superAdmin();
    }
    if ($role === 'staff') {
        return User::where('email', 'advisor2.demo@qiu.edu.my')->firstOrFail();
    }
    $user = otherStudent();
    $user->assignRole('student');
    if ($role === 'member') {
        Membership::create(['club_id' => $f['club']->id, 'user_id' => $user->id, 'status' => 'active', 'starts_on' => today(), 'expires_on' => today()->addYear()]);
    } elseif ($role === 'helper') {
        $f['event']->helpers()->attach($user);
    } elseif ($role === 'other_committee') {
        app(RoleService::class)->assignClubPosition($other, app(SessionResolver::class)->current(), $user, 'President');
    } elseif ($role === 'former_committee') {
        RoleAssignment::create(['user_id' => $user->id, 'role' => ScopedRole::ClubCommittee,
            'scope_type' => ScopeType::Club, 'scope_id' => $f['club']->id, 'session_id' => $f['event']->session_id,
            'position' => 'Former officer', 'starts_on' => today()->subMonths(2), 'ends_on' => today()->subDay()]);
    }

    return $user;
}

function hardReadCases(): array
{
    return [
        'feed' => ['/feed', 'signed_in'],
        'search' => ['/search?q=Club', 'signed_in'],
        'calendar' => ['/calendar', 'signed_in'],
        'activities' => ['/activities', 'signed_in'],
        'notifications' => ['/notifications', 'signed_in'],
        'own profile' => ['/me', 'signed_in'],
        'club profile' => ['/clubs/{club}', 'signed_in'],
        'join club page' => ['/clubs/{club}/join', 'signed_in'],
        'approval inbox' => ['/approvals', 'signed_in'],
        'start proposal' => ['/clubs/{club}/events/create', 'club'],
        'edit proposal' => ['/events/{event}/proposal', 'organiser'],
        'live proposal' => ['/events/{event}/proposal/live', 'organiser'],
        'private proposal document' => ['/documents/{document}', 'document'],
        'private draft event' => ['/events/{event}', 'document'],
        'attendance desk' => ['/events/{event}/attendance', 'desk'],
        'post-event documents' => ['/events/{event}/documents', 'organiser'],
        'indemnities list' => ['/events/{event}/indemnities', 'organiser'],
        'poster editor' => ['/events/{event}/poster', 'organiser'],
        'club finance' => ['/clubs/{club}/finance', 'club'],
        'club members' => ['/clubs/{club}/members', 'club'],
        'club participants' => ['/clubs/{club}/participants', 'club'],
        'club meetings' => ['/clubs/{club}/meetings', 'club'],
        'club venues' => ['/clubs/{club}/venues', 'club'],
        'club AGM hub' => ['/clubs/{club}/agm', 'club'],
        'payment review' => ['/clubs/{club}/payments/review', 'treasure'],
        'unrelated finance' => ['/clubs/{other}/finance', 'other'],
        'unrelated members' => ['/clubs/{other}/members', 'other'],
        'unrelated meetings' => ['/clubs/{other}/meetings', 'other'],
        'admin dashboard' => ['/admin', 'admin'],
        'club management' => ['/admin/clubs', 'admin'],
        'create club' => ['/admin/clubs/create', 'admin'],
        'staff management' => ['/admin/staff', 'admin'],
        'all members' => ['/admin/members', 'admin'],
        'all finance' => ['/admin/finance', 'admin'],
        'club performance' => ['/admin/performance', 'admin'],
        'SLD requests' => ['/admin/requests', 'admin'],
        'AGM administration' => ['/admin/agm', 'admin'],
        'venue administration' => ['/admin/venues', 'admin'],
        'fee administration' => ['/admin/fees', 'admin'],
        'moderation' => ['/admin/moderation', 'admin'],
        'settings' => ['/admin/settings', 'admin'],
        'global roles' => ['/admin/roles', 'superadmin'],
        'audit log' => ['/admin/audit', 'superadmin'],
        'camsys sync' => ['/admin/sync', 'superadmin'],
    ];
}

dataset('hard role reads', function () {
    foreach (hardRoles() as $role) {
        foreach (hardReadCases() as $name => [$path, $access]) {
            yield "$role / $name" => [$role, $path, $access];
        }
    }
});

it('enforces the role and club boundary on each page', function (string $role, string $path, string $access) {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', config('app.timezone')));
    $f = proposalFixture(['is_faculty_event' => true]);
    $other = Club::create(['name' => 'Unrelated audit club']);
    $actor = hardActor($role, $f, $other);
    if ($actor) {
        $this->actingAs($actor);
    }
    $admin = in_array($role, ['admin', 'superadmin'], true);
    $club = $admin || in_array($role, ['president', 'vice_president', 'secretary', 'treasurer', 'advisor'], true);
    $organiser = $club || in_array($role, ['event_president', 'event_treasurer'], true);
    $allowed = match ($access) {
        'signed_in' => $role !== 'guest',
        'club' => $club,
        'organiser' => $organiser,
        'desk' => $organiser || $role === 'helper',
        'document' => $organiser || in_array($role, ['assistant_registrar', 'registrar', 'bursar', 'coo', 'dean'], true),
        'treasure' => $admin || in_array($role, ['president', 'vice_president', 'secretary', 'treasurer'], true),
        'other' => $admin || $role === 'other_committee',
        'admin' => $admin,
        'superadmin' => $role === 'superadmin',
    };
    $url = strtr($path, ['{club}' => $f['club']->slug, '{event}' => $f['event']->slug,
        '{document}' => (string) $f['document']->id, '{other}' => $other->slug]);
    $this->getJson($url)->assertStatus($role === 'guest' ? 401 : ($allowed ? 200 : 403));
})->with('hard role reads')->group('hard-audit');

dataset('hard role writes', function () {
    foreach (hardRoles() as $role) {
        foreach (['proposal', 'opening_balance', 'event_payment_settings', 'club_FAQ', 'review_payment', 'join_paid_club'] as $action) {
            yield "$role / $action" => [$role, $action];
        }
    }
});

it('enforces role permissions and persisted results for sensitive actions', function (string $role, string $action) {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', config('app.timezone')));
    $f = proposalFixture();
    $other = Club::create(['name' => 'Unrelated audit club']);
    $actor = hardActor($role, $f, $other);
    if ($actor) {
        $this->actingAs($actor);
    }
    $admin = in_array($role, ['admin', 'superadmin'], true);
    $committee = in_array($role, ['president', 'vice_president', 'secretary', 'treasurer'], true);
    $club = $admin || $committee || $role === 'advisor';
    $organiser = $club || in_array($role, ['event_president', 'event_treasurer'], true);
    $base = '/clubs/'.$f['club']->slug;

    if ($action === 'proposal') {
        $response = $this->post('/events/'.$f['event']->slug.'/proposal/submit', [
            ...completeProposal($f), 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
        ]);
        if ($role === 'guest') {
            $response->assertRedirect(route('login'));
        } elseif (! $organiser) {
            $response->assertForbidden();
        } elseif ($committee) {
            $response->assertSessionHas('success');
        } else {
            $response->assertSessionHas('error');
        }
        expect($f['document']->fresh()->status->value)->toBe($committee ? 'in_review' : 'draft');
    } elseif ($action === 'opening_balance') {
        $response = $this->post($base.'/finance/opening', ['amount_rm' => 123.45, 'as_of' => today()->toDateString()]);
        if ($role === 'guest') {
            $response->assertRedirect(route('login'));
        } elseif (! $admin) {
            $response->assertForbidden();
        } else {
            $response->assertSessionHas('success');
        }
        expect($f['club']->openingBalance()->value('amount_sen'))->toBe($admin ? 12345 : null);
    } elseif ($action === 'event_payment_settings') {
        $response = $this->put('/events/'.$f['event']->slug.'/payment-settings', [
            'fee_rm' => 15, 'payment_url' => 'https://pay.example/audit', 'payment_account_name' => 'Audit Club',
        ]);
        if ($role === 'guest') {
            $response->assertRedirect(route('login'));
        } elseif (! $organiser) {
            $response->assertForbidden();
        } else {
            $response->assertSessionHas('success');
        }
        expect($f['event']->fresh()->fee_sen)->toBe($organiser ? 1500 : 0);
    } elseif ($action === 'club_FAQ') {
        $response = $this->post($base.'/faqs', ['question' => 'Audit question', 'answer' => 'Audit answer']);
        if ($role === 'guest') {
            $response->assertRedirect(route('login'));
        } elseif (! $club) {
            $response->assertForbidden();
        } else {
            $response->assertRedirect()->assertSessionHasNoErrors();
        }
        expect($f['club']->faqs()->count())->toBe($club ? 1 : 0);
    } elseif ($action === 'review_payment') {
        $payer = User::factory()->create();
        $m = Membership::create(['club_id' => $f['club']->id, 'user_id' => $payer->id, 'status' => 'pending']);
        $payment = $m->payments()->create(['club_id' => $f['club']->id, 'user_id' => $payer->id,
            'method' => 'online', 'amount_sen' => 3000, 'status' => 'pending_review']);
        $response = $this->post('/payments/'.$payment->id.'/decide', ['approve' => true, 'reference' => 'AUDIT-REF']);
        if ($role === 'guest') {
            $response->assertRedirect(route('login'));
        } elseif (! ($admin || $committee)) {
            $response->assertForbidden();
        } else {
            $response->assertSessionHas('success');
        }
        expect($payment->fresh()->status->value)->toBe($admin || $committee ? 'confirmed' : 'pending_review');
    } else {
        ClubFee::create(['club_id' => $f['club']->id, 'session_id' => $f['event']->session_id,
            'amount_sen' => 3000, 'set_by' => $f['president']->id, 'approved_at' => now()]);
        $response = $this->post($base.'/join', ['acknowledged' => true]);
        if ($role === 'guest') {
            $response->assertRedirect(route('login'));
        } elseif ($actor->student?->isActive() && $role !== 'member') {
            $response->assertSessionHas('success');
            expect(Membership::where('club_id', $f['club']->id)->where('user_id', $actor->id)->sole()->status->value)->toBe('pending');
        } else {
            $response->assertSessionHas('error');
        }
    }
})->with('hard role writes')->group('hard-audit');
