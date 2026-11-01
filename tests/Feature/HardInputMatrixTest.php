<?php

use App\Models\Signature;
use App\Models\User;
use App\Services\Approvals\SignatureStore;
use Carbon\CarbonImmutable;

dataset('hard invalid proposal inputs', [
    'blank name' => ['name', '', 'name'],
    'oversized name' => ['name', str_repeat('x', 151), 'name'],
    'blank summary' => ['summary', '', 'summary'],
    'oversized summary' => ['summary', str_repeat('x', 1001), 'summary'],
    'invalid start' => ['starts_at', 'not-a-date', 'starts_at'],
    'end before start' => ['ends_at', '2020-01-01', 'ends_at'],
    'missing venue' => ['venue', '', 'venue'],
    'unsupported mode' => ['mode', 'teleport', 'mode'],
    'no participants' => ['expected_participants', 0, 'expected_participants'],
    'negative participants' => ['expected_participants', -1, 'expected_participants'],
    'fractional participants' => ['expected_participants', 1.5, 'expected_participants'],
    'too many participants' => ['expected_participants', 5001, 'expected_participants'],
    'invalid nature' => ['nature', ['invalid'], 'nature.0'],
    'invalid SDG' => ['sdgs', [99], 'sdgs.0'],
    'invalid source' => ['source_of_fund', ['invalid'], 'source_of_fund.0'],
    'one objective' => ['objectives', ['Only one'], 'objectives'],
    'no objectives' => ['objectives', [], 'objectives'],
    'negative participant CCA' => ['cca_participant', -1, 'cca_participant'],
    'negative committee CCA' => ['cca_committee', -1, 'cca_committee'],
    'excessive participant CCA' => ['cca_participant', 100000, 'cca_participant'],
    'missing introduction' => ['introduction', '', 'introduction'],
    'missing eligibility' => ['eligibility', '', 'eligibility'],
    'missing conclusion' => ['conclusion', '', 'conclusion'],
    'negative price' => ['budget.expenses.0.price_sen', -1, 'budget.expenses.0.price_sen'],
    'zero quantity' => ['budget.expenses.0.quantity', 0, 'budget.expenses.0.quantity'],
    'fractional quantity' => ['budget.expenses.0.quantity', 1.5, 'budget.expenses.0.quantity'],
    'oversized quantity' => ['budget.expenses.0.quantity', 100001, 'budget.expenses.0.quantity'],
    'missing expense description' => ['budget.expenses.0.description', '', 'budget.expenses.0.description'],
    'no signature' => ['signature', '', 'signature'],
    'no stroke evidence' => ['signature_strokes', '', 'signature_strokes'],
]);

it('rejects invalid proposal submissions without creating a signed version', function (string $field, mixed $value, string $error) {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', config('app.timezone')));
    $f = proposalFixture();
    $data = [...completeProposal($f), 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes()];
    data_set($data, $field, $value);

    $this->actingAs($f['president'])->postJson('/events/'.$f['event']->slug.'/proposal/submit', $data)
        ->assertUnprocessable()->assertJsonValidationErrors($error);

    expect($f['document']->versions()->where('number', '>', 0)->count())->toBe(0)
        ->and(Signature::count())->toBe(0)->and($f['document']->fresh()->status->value)->toBe('draft');
})->with('hard invalid proposal inputs')->group('hard-audit');

dataset('hard invalid signature evidence', [
    'malformed JSON' => ['{'],
    'no strokes' => ['{"device":"touch","strokes":[]}'],
    'unsupported device' => ['{"device":"keyboard","strokes":[]}'],
    'string coordinate' => ['{"device":"touch","strokes":[[["x",1,2,3]]]}'],
    'missing coordinate' => ['{"device":"touch","strokes":[[[1,2,3]]]}'],
    'object instead of stroke' => ['{"device":"touch","strokes":["bad"]}'],
    'oversized JSON' => [str_repeat(' ', 307201)],
]);

it('rejects malformed signature evidence and writes no signature', function (string $strokes) {
    seedCamsys();
    $user = User::factory()->create();

    expect(fn () => app(SignatureStore::class)->store($user, signaturePng(), 'Student', [], $strokes))
        ->toThrow(DomainException::class);
    expect(Signature::count())->toBe(0);
})->with('hard invalid signature evidence')->group('hard-audit');
