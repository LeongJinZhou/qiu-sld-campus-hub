<?php

use App\Enums\ChainRole;
use App\Enums\DocumentStatus;
use App\Models\Approval;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Approvals\ApproverResolver;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

/** Submits the fixture proposal and signs every step before SLD's; returns SLD's step and an SLD officer. */
function atSldStep(array $f): array
{
    $engine = app(ApprovalEngine::class);
    $engine->submit($f['document'], $f['president'], completeProposal($f), signaturePng());
    while (($step = $engine->currentStep($f['document']->fresh())) && $step->role !== ChainRole::SldOfficer) {
        $engine->approve($step, app(ApproverResolver::class)->candidates($f['document'], $step->role)->firstOrFail(), signaturePng());
    }

    return [$step, app(ApproverResolver::class)->candidates($f['document'], ChainRole::SldOfficer)->firstOrFail()];
}

it('lets SLD send a proposal for paper signatures, then approve only once all are ticked and the scan is uploaded', function () {
    $f = proposalFixture();
    [$step, $sld] = atSldStep($f);

    $this->actingAs($sld)->get("/approvals/{$step->id}")->assertInertia(fn (Assert $p) => $p->where('paper', null)->where('paperOptions', fn ($o) => collect($o)->contains(fn ($x) => str_starts_with($x, 'Dean'))));
    // Nobody but SLD at its step arranges paper signatures.
    $this->actingAs($f['advisor'])->post("/approvals/{$step->id}/paper", ['signers' => ['Dean']])->assertSessionHas('error');

    $this->actingAs($sld)->post("/approvals/{$step->id}/paper", ['signers' => ['Dean', 'COO']])->assertSessionHas('success');
    $approve = fn () => $this->actingAs($sld)->post("/approvals/{$step->id}", ['decision' => 'approve', 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(), 'no_problems' => true]);
    $approve()->assertSessionHas('error', 'Still to sign on paper: Dean, COO.');

    $this->actingAs($sld)->post("/approvals/{$step->id}/paper/record", ['signed' => ['Dean', 'COO']])->assertSessionHas('success');
    $approve()->assertSessionHas('error', 'Upload the signed scan first.');

    $this->actingAs($sld)->post("/approvals/{$step->id}/paper/record", ['signed' => ['Dean', 'COO'], 'scan' => UploadedFile::fake()->create('signed.pdf', 200, 'application/pdf')]);
    $this->actingAs($sld)->get("/approvals/{$step->id}/paper/scan")->assertOk();
    $approve()->assertSessionHas('celebrate', 'Event Proposal approved');

    expect($f['document']->fresh()->status)->toBe(DocumentStatus::Approved)
        ->and(Approval::find($step->id)->comment)->toContain('Signed on paper by: Dean, COO');
});

it('approves straight away when no paper signatures are asked for', function () {
    $f = proposalFixture();
    [$step, $sld] = atSldStep($f);
    $this->actingAs($sld)->post("/approvals/{$step->id}/paper", ['signers' => ['Registrar']]);
    $this->actingAs($sld)->delete("/approvals/{$step->id}/paper")->assertSessionHas('success');
    $this->actingAs($sld)->post("/approvals/{$step->id}", ['decision' => 'approve', 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(), 'no_problems' => true])
        ->assertSessionHas('celebrate');
});
