<script setup lang="ts">
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import ProposalView from '@/Components/forms/ProposalView.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import SignaturePad from '@/Components/ui/SignaturePad.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PresentedDocument } from '@/types';
import { PROPOSAL_SECTIONS, type ProposalSection } from '@/lib/proposal';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    approvalId: number;
    role: string;
    canAct: boolean;
    document: PresentedDocument;
    downloads: string[];
}>();

const form = useForm({
    decision: 'approve' as 'approve' | 'return' | 'reject',
    comment: '',
    no_problems: false,
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
                                <SignaturePad v-model="form.signature" v-model:strokes="form.signature_strokes" label="Sign to approve" />
                                <Button :disabled="!form.signature || !form.no_problems" :loading="form.processing && form.decision === 'approve'" @click="decide('approve')">
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
                <Card>
                    <template #title>Approval progress</template>
                    <ApprovalTracker :progress="document.progress" :steps="document.steps" />
                </Card>
            </aside>
        </div>
    </AppLayout>
</template>
