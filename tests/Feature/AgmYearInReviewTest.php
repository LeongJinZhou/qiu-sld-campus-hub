<?php

use App\Enums\DocumentStatus;
use Inertia\Testing\AssertableInertia as Assert;

it('compiles the AGM year: that session\'s events, weekly activities and money only', function () {
    $f = proposalFixture();
    [$this->club, $this->sld, $this->secretary] = [$f['club'], sldOfficer(), $f['club']->roleAssignments()->where('position', 'Secretary')->first()->user];
    $period = openPeriodAndAgm($this);
    $f['event']->update(['status' => DocumentStatus::Approved]);
    $this->actingAs($f['president'])->get("/clubs/{$f['club']->slug}/agm")->assertInertia(fn (Assert $p) => $p
        ->where('year.session_id', $period->session_id)->has('year.events', 1)->where('year.events.0.name', $f['event']->name));
});
