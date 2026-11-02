<script setup lang="ts">
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import ProposalView from '@/Components/forms/ProposalView.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Modal from '@/Components/ui/Modal.vue';
import SignaturePad from '@/Components/ui/SignaturePad.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PresentedDocument } from '@/types';
import { PROPOSAL_SECTIONS, type ProposalSection } from '@/lib/proposal';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    approvalId: number;
    role: string;
    canAct: boolean;
    document: PresentedDocument;
    downloads: string[];
    exceptions: { kind: string; label: string; reason: string }[];
    decidesExceptions: boolean;
    paper?: { signers: { label: string; signed: boolean }[]; scan_url: string | null; complete: boolean } | null;
    paperOptions?: string[];
}>();

// Higher authorities signing on paper (SLD's step): choose who, export, upload the signed scan, tick who signed.
const chosen = ref<string[]>([]);
const other = ref('');
const startPaper = (): void =>
    router.post(`/approvals/${props.approvalId}/paper`, { signers: [...chosen.value, ...(other.value.trim() ? [other.value.trim()] : [])] }, { preserveScroll: true });
const paperForm = useForm({ scan: null as File | null, signed: (props.paper?.signers ?? []).filter((s) => s.signed).map((s) => s.label) });
const savePaper = (): void => paperForm.post(`/approvals/${props.approvalId}/paper/record`, { preserveScroll: true, forceFormData: true, onSuccess: () => (paperForm.scan = null) });
const cancelPaper = (): void => {
    if (confirm('No paper signatures needed after all? The uploaded scan is removed.')) router.delete(`/approvals/${props.approvalId}/paper`, { preserveScroll: true });
};
const confirming = ref(false);

const form = useForm({
    decision: 'approve' as 'approve' | 'return' | 'reject',
    comment: '',
    no_problems: false,
    accept_exceptions: false,
    signature: null as string | null,
    signature_strokes: null as string | null,
});

// No problems: tick and sign. Problems: mark the wrong parts (proposals) or write a comment, then return or reject.
const verdict = ref<'ok' | 'problems' | null>(null);

// Returning a proposal: tick the parts to fix and say what is wrong with each; they are highlighted for the club.
const marked = reactive<Partial<Record<ProposalSection, string>>>({});
const toggle = (key: ProposalSection, on: boolean): void => {
    if (on) marked[key] = marked[key] ?? '';
    else delete marked[key];
};
const markedList = computed(() =>
    Object.entries(marked).map(([key, note]) => ({
        key,
        note: (note ?? '').trim(),
    })),
);
const decide = (decision: 'approve' | 'return' | 'reject'): void => {
    form.decision = decision;
    form.transform((data) => ({
        ...data,
        ...(decision !== 'approve' && markedList.value.length ? { sections: markedList.value } : {}),
    })).post(`/approvals/${props.approvalId}`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Review · ${document.type_label}`" />
    <AppLayout>
        <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
            <div class="grid content-start gap-4">
                <section v-if="exceptions.length" class="grid gap-2 rounded-card border-2 border-warning/50 bg-warning/5 p-4 text-sm">
                    <h2 class="font-semibold text-warning">Exceptions for SLD to decide</h2>
                    <div v-for="e in exceptions" :key="e.kind">
                        <p class="font-medium">{{ e.label }}</p>
                        <p class="text-ink-muted">Reason given: {{ e.reason || '—' }}</p>
                    </div>
                </section>
                <h1 class="text-xl font-bold">
                    {{ document.type_label }} ·
                    {{ document.event?.name ?? document.club.name }}
                </h1>
                <div v-if="downloads.length" class="flex flex-wrap gap-2">
                    <a v-for="format in downloads" :key="format" :href="`/documents/${document.id}/download/${format}`">
                        <Button variant="secondary" size="sm">Open official {{ format.toUpperCase() }}</Button>
                    </a>
                </div>
                <ProposalView v-if="document.type === 'proposal'" :document="document" />
            </div>
            <aside class="grid content-start gap-4">
                <Card v-if="canAct">
                    <template #title>Your decision · {{ role }}</template>
                    <div class="grid gap-4">
                        <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Your check">
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="verdict === 'ok'"
                                class="grid gap-1 rounded-card border p-3 text-left text-sm transition"
                                :class="verdict === 'ok' ? 'border-success bg-success/10 ring-2 ring-success/30' : 'border-line hover:bg-surface-muted'"
                                @click="verdict = 'ok'"
                            >
                                <strong class="text-success">✓ No problems</strong><span class="text-xs text-ink-muted">Tick, sign and approve</span>
                            </button>
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="verdict === 'problems'"
                                class="grid gap-1 rounded-card border p-3 text-left text-sm transition"
                                :class="verdict === 'problems' ? 'border-danger bg-danger/10 ring-2 ring-danger/30' : 'border-line hover:bg-surface-muted'"
                                @click="verdict = 'problems'"
                            >
                                <strong class="text-danger">✕ Something is wrong</strong><span class="text-xs text-ink-muted">Mark the parts, return or reject</span>
                            </button>
                        </div>

                        <Transition name="fade-up" mode="out-in">
                            <div v-if="verdict === 'ok'" key="ok" class="grid gap-3">
                                <label class="flex items-start gap-2 text-sm">
                                    <input v-model="form.no_problems" type="checkbox" class="mt-0.5 size-4 accent-success" />
                                    I have checked this {{ document.type_label.toLowerCase() }} and found no problems.
                                </label>
                                <label
                                    v-if="exceptions.length && decidesExceptions"
                                    class="flex items-start gap-2 rounded-control border-2 border-warning/50 bg-warning/5 p-3 text-sm"
                                >
                                    <input v-model="form.accept_exceptions" type="checkbox" class="mt-0.5 size-4 accent-warning" />
                                    I approve these exceptions as SLD: {{ exceptions.map((e) => e.label).join('; ') }}.
                                </label>
                                <SignaturePad v-model="form.signature" v-model:strokes="form.signature_strokes" label="Sign to approve" />
                                <p v-if="paper && !paper.complete" class="rounded-control bg-warning/10 px-3 py-2 text-xs text-warning">
                                    Waiting for the paper signatures: upload the signed scan and tick everyone who signed.
                                </p>
                                <Button
                                    :disabled="!form.signature || !form.no_problems || (exceptions.length > 0 && decidesExceptions && !form.accept_exceptions) || (!!paper && !paper.complete)"
                                    :loading="form.processing && form.decision === 'approve'"
                                    @click="confirming = true"
                                >
                                    Approve &amp; sign
                                </Button>
                            </div>
                            <div v-else-if="verdict === 'problems'" key="problems" class="grid gap-3">
                                <div v-if="document.type === 'proposal'" class="grid gap-2 rounded-control border border-line p-3">
                                    <p class="text-sm font-medium">Which parts are wrong?</p>
                                    <div v-for="(label, key) in PROPOSAL_SECTIONS" :key="key" class="grid gap-1">
                                        <label class="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                class="size-4 accent-danger"
                                                :checked="key in marked"
                                                @change="toggle(key, ($event.target as HTMLInputElement).checked)"
                                            />
                                            {{ label }}
                                        </label>
                                        <Transition name="fade-up">
                                            <input
                                                v-if="key in marked"
                                                v-model="marked[key]"
                                                class="ml-6 h-9 rounded-control border border-danger/40 px-2 text-sm outline-none focus:ring-2 focus:ring-danger/20"
                                                :placeholder="`What is wrong in ${label.toLowerCase()}`"
                                            />
                                        </Transition>
                                    </div>
                                    <p v-if="Object.keys(form.errors).some((k) => k.startsWith('sections'))" class="text-xs text-danger">
                                        Write what is wrong for every part you ticked.
                                    </p>
                                </div>
                                <Textarea
                                    v-model="form.comment"
                                    :label="document.type === 'proposal' ? 'Anything else (optional if you marked parts)' : 'What is wrong'"
                                    :rows="3"
                                    :error="form.errors.comment"
                                />
                                <div class="grid grid-cols-2 gap-2">
                                    <Button
                                        variant="secondary"
                                        :disabled="!markedList.length && !form.comment.trim()"
                                        :loading="form.processing && form.decision === 'return'"
                                        @click="decide('return')"
                                    >
                                        Return to fix
                                    </Button>
                                    <Button
                                        variant="danger"
                                        :disabled="!markedList.length && !form.comment.trim()"
                                        :loading="form.processing && form.decision === 'reject'"
                                        @click="decide('reject')"
                                    >
                                        Reject
                                    </Button>
                                </div>
                                <p class="text-xs text-ink-muted">
                                    Return: the committee fixes the marked parts and resubmits. Reject: they can still correct it, but everyone signs again from the start.
                                </p>
                            </div>
                        </Transition>
                    </div>
                </Card>
                <Card v-if="paperOptions?.length || paper">
                    <template #title>Higher-authority signatures (paper)</template>
                    <div v-if="!paper" class="grid gap-3 text-sm">
                        <p class="text-ink-muted">If this needs a higher authority's signature, choose who signs. You then export it, have it signed, and upload the scan.</p>
                        <label v-for="o in paperOptions" :key="o" class="flex items-center gap-2">
                            <input v-model="chosen" type="checkbox" :value="o" class="size-4 accent-brand-blue" /> {{ o }}
                        </label>
                        <input v-model="other" maxlength="120" placeholder="Someone else (name and title)" class="h-9 rounded-control border border-line bg-surface px-2 text-sm" />
                        <Button variant="secondary" :disabled="!chosen.length && !other.trim()" @click="startPaper">Send for signature on paper</Button>
                    </div>
                    <div v-else class="grid gap-3 text-sm">
                        <ol class="grid gap-3">
                            <li class="grid gap-1">
                                <p class="font-medium">1. Export and have it signed</p>
                                <div class="flex flex-wrap gap-2">
                                    <a v-for="format in downloads" :key="format" :href="`/documents/${document.id}/download/${format}`" class="text-brand-blue underline">{{ format.toUpperCase() }}</a>
                                </div>
                            </li>
                            <li v-if="canAct" class="grid gap-1">
                                <p class="font-medium">2. Upload the signed scan</p>
                                <input type="file" accept="application/pdf,image/*" class="text-xs" @change="paperForm.scan = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                                <p v-if="paperForm.errors.scan" class="text-xs text-danger">{{ paperForm.errors.scan }}</p>
                                <a v-if="paper.scan_url" :href="paper.scan_url" target="_blank" rel="noopener" class="text-xs text-brand-blue underline">Signed scan on file</a>
                            </li>
                            <li class="grid gap-1">
                                <p class="font-medium">{{ canAct ? '3. Tick who has signed' : 'Signatures' }}</p>
                                <label v-for="sgn in paper.signers" :key="sgn.label" class="flex items-center gap-2">
                                    <input v-if="canAct" v-model="paperForm.signed" type="checkbox" :value="sgn.label" class="size-4 accent-success" />
                                    <span v-else>{{ sgn.signed ? '✓' : '…' }}</span>
                                    {{ sgn.label }}
                                </label>
                            </li>
                        </ol>
                        <div v-if="canAct" class="flex flex-wrap gap-2">
                            <Button size="sm" :loading="paperForm.processing" @click="savePaper">Save</Button>
                            <Button size="sm" variant="ghost" @click="cancelPaper">Not needed</Button>
                        </div>
                        <p class="text-xs" :class="paper.complete ? 'text-success' : 'text-ink-muted'">
                            {{ paper.complete ? '✓ All signed and the scan is on file. You can now give the final approval.' : 'The final approval opens once every signatory is ticked and the scan is uploaded.' }}
                        </p>
                    </div>
                </Card>
                <Card>
                    <template #title>Approval progress</template>
                    <ApprovalTracker :progress="document.progress" :steps="document.steps" />
                </Card>
            </aside>
        </div>
        <Modal v-model:open="confirming" title="Confirm the approval">
            <div class="grid gap-3 text-sm">
                <p>
                    You are approving this {{ document.type_label.toLowerCase() }}<template v-if="decidesExceptions"> as the final step</template>.
                    <template v-if="paper"> It records that {{ paper.signers.map((s) => s.label).join(', ') }} signed on paper.</template>
                </p>
                <div class="flex justify-end gap-2">
                    <Button variant="secondary" @click="confirming = false">Go back</Button>
                    <Button
                        :loading="form.processing"
                        @click="
                            confirming = false;
                            decide('approve');
                        "
                        >Yes, approve</Button
                    >
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
