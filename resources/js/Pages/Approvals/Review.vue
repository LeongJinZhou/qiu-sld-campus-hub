<script setup lang="ts">
import ApprovalTimeline from '@/Components/forms/ApprovalTimeline.vue';
import ProposalView from '@/Components/forms/ProposalView.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import SignaturePad from '@/Components/ui/SignaturePad.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PresentedDocument } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ approvalId: number; role: string; canAct: boolean; document: PresentedDocument }>();

const form = useForm({ decision: 'approve' as 'approve' | 'return' | 'reject', comment: '', signature: null as string | null });
const decide = (decision: 'approve' | 'return' | 'reject'): void => {
    form.decision = decision;
    form.post(`/approvals/${props.approvalId}`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Review · ${document.type_label}`" />
    <AppLayout>
        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="grid content-start gap-4">
                <h1 class="text-xl font-bold">{{ document.type_label }} · {{ document.event?.name ?? document.club.name }}</h1>
                <ProposalView v-if="document.type === 'proposal'" :document="document" />
            </div>
            <aside class="grid content-start gap-4">
                <Card v-if="canAct">
                    <template #title>Your decision · {{ role }}</template>
                    <div class="grid gap-4">
                        <SignaturePad v-model="form.signature" label="Sign to approve" />
                        <Textarea v-model="form.comment" label="Comment (required to return or reject)" :rows="3" :error="form.errors.comment" />
                        <Button :disabled="!form.signature" :loading="form.processing && form.decision === 'approve'" @click="decide('approve')">Approve &amp; sign</Button>
                        <div class="grid grid-cols-2 gap-2">
                            <Button variant="secondary" :loading="form.processing && form.decision === 'return'" @click="decide('return')">Return to club</Button>
                            <Button variant="danger" :loading="form.processing && form.decision === 'reject'" @click="decide('reject')">Reject</Button>
                        </div>
                    </div>
                </Card>
                <Card>
                    <template #title>Approvals</template>
                    <ApprovalTimeline :steps="document.steps" />
                </Card>
            </aside>
        </div>
    </AppLayout>
</template>
