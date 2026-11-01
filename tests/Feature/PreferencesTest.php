<?php

use Inertia\Testing\AssertableInertia as Assert;

it('saves the theme and tours per account, and tells the page which club a committee member runs', function () {
    $f = proposalFixture();
    $president = $f['president'];

    $this->actingAs($president)->get('/feed')->assertInertia(fn (Assert $p) => $p
        ->where('auth.user.theme', 'system')->where('auth.user.tours_seen', [])->where('auth.user.committee_club.slug', $f['club']->slug));

    $this->actingAs($president)->put('/me/theme', ['theme' => 'dark'])->assertRedirect();
    $this->actingAs($president)->put('/me/theme', ['theme' => 'neon'])->assertSessionHasErrors('theme');
    $this->actingAs($president)->post('/me/tours', ['tour' => 'student', 'seen' => true]);
    $this->actingAs($president)->post('/me/tours', ['tour' => 'committee', 'seen' => true]);
    $this->actingAs($president)->post('/me/tours', ['tour' => 'student', 'seen' => false]); // "Show me around again"

    expect($president->fresh()->only('theme', 'tours_seen'))->toBe(['theme' => 'dark', 'tours_seen' => ['committee']]);
    $this->actingAs($president)->get('/feed')->assertSee('data-theme="dark"', false);
    $this->actingAs(otherStudent())->get('/feed')->assertInertia(fn (Assert $p) => $p->where('auth.user.committee_club', null));
});
