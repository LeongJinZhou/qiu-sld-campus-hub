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
import { computed, reactive } from 'vue';

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
    signature: null as string | null,
    signature_strokes: null as string | null,
});

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
        ...(decision === 'return' && markedList.value.length ? { sections: markedList.value } : {}),
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
                        <SignaturePad v-model="form.signature" v-model:strokes="form.signature_strokes" label="Sign to approve" />
                        <Textarea
                            v-model="form.comment"
                            :label="
                                document.type === 'proposal' ? 'Comment (required to reject; to return, a comment or the parts below)' : 'Comment (required to return or reject)'
                            "
                            :rows="3"
                            :error="form.errors.comment"
                        />
                        <details v-if="document.type === 'proposal'" class="group rounded-control border border-line" :open="markedList.length > 0">
                            <summary class="cursor-pointer px-3 py-2 text-sm font-medium">
                                Parts to fix
                                <span class="text-ink-muted">({{ markedList.length }} marked)</span>
                            </summary>
                            <div class="grid gap-2 border-t border-line p-3">
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
                                            :placeholder="`What to change in ${label.toLowerCase()}`"
                                        />
                                    </Transition>
                                </div>
                                <p v-if="Object.keys(form.errors).some((k) => k.startsWith('sections'))" class="text-xs text-danger">
                                    Write what to change for every part you ticked.
                                </p>
                            </div>
                        </details>
                        <Button :disabled="!form.signature" :loading="form.processing && form.decision === 'approve'" @click="decide('approve')">Approve &amp; sign</Button>
                        <div class="grid grid-cols-2 gap-2">
                            <Button variant="secondary" :loading="form.processing && form.decision === 'return'" @click="decide('return')">Return to club</Button>
                            <Button variant="danger" :loading="form.processing && form.decision === 'reject'" @click="decide('reject')">Reject</Button>
                        </div>
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
