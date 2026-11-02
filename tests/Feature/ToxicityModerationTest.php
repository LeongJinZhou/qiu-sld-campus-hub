<?php

use App\Models\Student;
use App\Services\Moderation\ToxicityClassifier;
use App\Support\ContentFilter;
use Illuminate\Support\Facades\Http;

it('catches disguised profanity and Malaysian slang, and leaves ordinary words alone', function () {
    foreach (['f_u_c_k', 'f-u-c-k', 'fvck you', 'fuckingshit', 'sh1t', 'sohai', 'pundek', 'kepala bapak kau', 'kan ni na', 'b_o_d_o_h', 'puki mak'] as $bad) {
        expect(ContentFilter::offences($bad))->not->toBeEmpty("missed: {$bad}");
    }
    foreach (['Scunthorpe class', 'beach trash pickup', 'mak kau sihat?', 'assess the bass', 'Shitake mushrooms', 'cocktail night'] as $fine) {
        expect(ContentFilter::offences($fine))->toBe([], "false positive: {$fine}");
    }
});

it('refuses comments the toxic-comment model flags, and keeps working when the model is unreachable', function () {
    config(['services.huggingface.token' => 'hf_test']);
    Http::fake(['*toxic-comment-model*' => Http::sequence()
        ->push([[['label' => 'toxic', 'score' => 0.97], ['label' => 'non-toxic', 'score' => 0.03]]])
        ->push([[['label' => 'non-toxic', 'score' => 0.99], ['label' => 'toxic', 'score' => 0.01]]])
        ->push('', 503)]);
    $f = proposalFixture();
    $student = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    $post = $f['club']->posts()->create(['type' => 'general', 'caption' => 'Hello', 'published_at' => now(), 'created_at' => now()]);

    $this->actingAs($student)->post("/posts/{$post->id}/comments", ['body' => 'you people are worthless'])->assertSessionHasErrors('body');
    $this->actingAs($student)->post("/posts/{$post->id}/comments", ['body' => 'see you there'])->assertSessionHasNoErrors();
    expect(app(ToxicityClassifier::class)->score('model is down'))->toBeNull(); // 503: fail open, the word filter still applies
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer hf_test') && $r['inputs'] === 'you people are worthless');
});
