<?php

use Inertia\Testing\AssertableInertia as Assert;

it('shows SLD a club\'s members and committee fees inside the admin portal', function () {
    $f = proposalFixture();
    $this->actingAs(sldOfficer())->get("/admin/clubs/{$f['club']->slug}/members")
        ->assertInertia(fn (Assert $p) => $p->component('Membership/Members')->where('portal', true)->has('committeeFees', 4));
    $this->actingAs($f['president'])->get("/admin/clubs/{$f['club']->slug}/members")->assertForbidden();
    $this->actingAs($f['advisor'])->get("/clubs/{$f['club']->slug}/members")->assertInertia(fn (Assert $p) => $p->where('portal', false)->has('committeeFees', 4));
});
