<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { rm } from '@/lib/format';
import { Head, router } from '@inertiajs/vue3';

defineProps<{
    club: { slug: string; name: string };
    fee_sen: number | null;
    items: {
        id: number;
        student: string;
        student_number: string | null;
        status: string;
        amount_sen: number;
        ai: { amount_sen: number | null; date: string | null; reference: string | null; payee: string | null; confidence: number | null };
        flags: string[];
        receipt_url: string;
        at: string | null;
    }[];
}>();

function decide(id: number, approve: boolean): void {
    const note = approve ? null : prompt('Reason for rejecting (sent to the student)');
    if (!approve && !note) return;
    router.post(`/payments/${id}/decide`, { approve, note }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Payment review" />
    <AppLayout wide>
        <div class="grid gap-4">
            <div>
                <h1 class="text-xl font-bold">Membership payments · {{ club.name }}</h1>
                <p class="text-sm text-ink-muted">Fee {{ fee_sen !== null ? rm(fee_sen) : 'not set' }}. Unclear receipts wait here; auto-confirmed ones are listed for spot checks.</p>
            </div>
            <EmptyState v-if="!items.length" title="Nothing to review" />
            <article v-for="p in items" :key="p.id" class="grid gap-4 rounded-card border border-line bg-surface p-4 shadow-card md:grid-cols-[18rem_1fr]">
                <a :href="p.receipt_url" target="_blank" rel="noopener" class="block overflow-hidden rounded-control border border-line bg-surface-muted">
                    <img :src="p.receipt_url" alt="Receipt" class="max-h-80 w-full object-contain" />
                </a>
                <div class="grid content-start gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p><strong>{{ p.student }}</strong> <span class="text-sm text-ink-muted">{{ p.student_number }} · {{ p.at }}</span></p>
                        <Badge :tone="p.status === 'auto_confirmed' ? 'success' : 'warning'">{{ p.status.replace('_', ' ') }}</Badge>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-ink-muted"><tr><th class="py-1">Check</th><th>Expected</th><th>AI read</th></tr></thead>
                        <tbody class="[&_td]:py-1">
                            <tr><td>Amount</td><td>{{ rm(p.amount_sen) }}</td><td :class="p.flags.includes('amount_mismatch') ? 'font-semibold text-danger' : ''">{{ p.ai.amount_sen !== null ? rm(p.ai.amount_sen) : '—' }}</td></tr>
                            <tr><td>Date</td><td>within 7 days</td><td :class="p.flags.some((f) => ['old_receipt', 'future_date', 'no_date'].includes(f)) ? 'font-semibold text-danger' : ''">{{ p.ai.date ?? '—' }}</td></tr>
                            <tr><td>Reference</td><td>unique</td><td :class="p.flags.includes('no_reference') ? 'font-semibold text-danger' : ''">{{ p.ai.reference ?? '—' }}</td></tr>
                            <tr><td>Payee</td><td>club account</td><td :class="p.flags.includes('payee_mismatch') ? 'font-semibold text-danger' : ''">{{ p.ai.payee ?? '—' }}</td></tr>
                            <tr><td>Confidence</td><td>≥ 80%</td><td>{{ p.ai.confidence !== null ? `${Math.round(p.ai.confidence * 100)}%` : '—' }}</td></tr>
                        </tbody>
                    </table>
                    <div v-if="p.status === 'pending_review'" class="flex gap-2">
                        <Button @click="decide(p.id, true)">Confirm payment</Button>
                        <Button variant="secondary" @click="decide(p.id, false)">Reject</Button>
                    </div>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
