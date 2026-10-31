<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon from '@/Components/ui/Icon.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { date, rm } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Tally {
    label: string;
    count: number;
    sen: number;
}
interface Row {
    name: string;
    student_number: string | null;
    email: string;
    status: string;
    date: string;
    method: string | null;
    order_id: string | null;
    amount_sen: number;
    collected_by: string | null;
    drive?: string | null;
}

const props = defineProps<{
    club: { slug: string; name: string };
    breakdown: {
        title: string;
        subtitle: string;
        tiles: { label: string; count?: number; sen?: number; net?: boolean }[];
        check: { payments_sen: number; ledger_sen: number; expected_sen: number | null; ok: boolean };
        groups: { title: string; items: Tally[] }[];
        rows: Row[];
    };
    url: string;
    session: string | null;
    sessions: string[];
}>();

const search = ref('');
const status = ref('');
const statuses = computed(() => [...new Set(props.breakdown.rows.map((r) => r.status))]);
const rows = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.breakdown.rows.filter(
        (r) => (!status.value || r.status === status.value) && (!q || `${r.name} ${r.student_number ?? ''} ${r.email} ${r.order_id ?? ''}`.toLowerCase().includes(q)),
    );
});
const tone = (s: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' =>
    s === 'Paid' ? 'success' : s === 'Rejected' ? 'danger' : s === 'Being checked' ? 'info' : s.startsWith('Registered') ? 'neutral' : 'warning';
const exportUrl = (format: string): string => `${props.url}/export/${format}${props.session ? `?session=${encodeURIComponent(props.session)}` : ''}`;
const changeSession = (s: string | number | null | undefined): void => router.get(props.url, { session: s }, { preserveScroll: true });
</script>

<template>
    <Head :title="`${breakdown.title} · ${club.name}`" />
    <AppLayout>
        <div class="grid gap-6">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <Link :href="`/clubs/${club.slug}/finance`" class="text-sm text-brand-blue">← Finance · {{ club.name }}</Link>
                    <h1 class="text-xl font-bold">{{ breakdown.title }}</h1>
                    <p class="text-sm text-ink-muted">{{ breakdown.subtitle }}</p>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <Select v-if="session" :model-value="session" label="Session" :options="sessions.map((s) => ({ value: s, label: s }))" @update:model-value="changeSession" />
                    <a :href="exportUrl('xlsx')"><Button variant="secondary"><Icon name="file" :size="16" /> Excel</Button></a>
                    <a :href="exportUrl('pdf')"><Button variant="secondary">PDF</Button></a>
                </div>
            </header>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div v-for="t in breakdown.tiles" :key="t.label" class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <p class="text-xs text-ink-muted">{{ t.label }}</p>
                    <p v-if="t.sen !== undefined" class="text-xl font-bold tabular-nums" :class="t.net ? (t.sen < 0 ? 'text-danger' : 'text-success') : ''">{{ rm(t.sen) }}</p>
                    <p v-else class="text-xl font-bold tabular-nums">{{ t.count }}</p>
                </div>
            </div>

            <!-- Payments counted vs. money filed in the ledger: the Treasurer's quick reconciliation. -->
            <div class="flex items-start gap-3 rounded-card border p-4 text-sm" :class="breakdown.check.ok ? 'border-success/30 bg-success/5' : 'border-warning/40 bg-warning/10'">
                <Icon :name="breakdown.check.ok ? 'check' : 'shield'" :size="18" class="mt-0.5 shrink-0" />
                <p v-if="breakdown.check.ok">
                    Everything matches: {{ rm(breakdown.check.payments_sen) }} in confirmed payments, {{ rm(breakdown.check.ledger_sen) }} filed in the ledger<template v-if="breakdown.check.expected_sen !== null">, and paid places × fee = {{ rm(breakdown.check.expected_sen) }}</template>.
                </p>
                <p v-else>
                    Please check: confirmed payments {{ rm(breakdown.check.payments_sen) }} · in the ledger {{ rm(breakdown.check.ledger_sen) }}<template v-if="breakdown.check.expected_sen !== null"> · paid places × fee {{ rm(breakdown.check.expected_sen) }}</template>. A fee changed after some people paid also shows here.
                </p>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <Card v-for="g in breakdown.groups" :key="g.title">
                    <template #title>{{ g.title }}</template>
                    <ul v-if="g.items.length" class="grid gap-1 text-sm">
                        <li v-for="i in g.items" :key="i.label" class="grid grid-cols-[1fr_auto] items-center gap-3 rounded-control px-2 py-2 odd:bg-surface-muted/60">
                            <span class="min-w-0"><span class="block truncate font-medium">{{ i.label }}</span><span class="text-xs text-ink-muted">{{ i.count }} paid</span></span>
                            <span class="font-semibold tabular-nums">{{ rm(i.sen) }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-ink-muted">Nothing yet.</p>
                </Card>
            </div>

            <section class="grid gap-3">
                <div class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card sm:grid-cols-[1fr_14rem]">
                    <Input v-model="search" label="Search" placeholder="Name, student ID, email or order ID" />
                    <Select v-model="status" label="Status" :options="[{ value: '', label: 'All' }, ...statuses.map((s) => ({ value: s, label: s }))]" />
                </div>
                <EmptyState v-if="!breakdown.rows.length" title="No one yet" description="Registrations and payments appear here as they come in." />
                <div v-else class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-muted text-left text-xs uppercase tracking-wide text-ink-muted">
                            <tr>
                                <th class="px-3 py-3 font-medium">Name</th><th class="px-3 py-3 font-medium">Status</th><th class="px-3 py-3 font-medium">Date</th>
                                <th class="px-3 py-3 font-medium">Method · received by</th><th class="px-3 py-3 font-medium">Order ID</th><th class="px-3 py-3 text-right font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, i) in rows" :key="`${r.order_id ?? r.email}-${i}`" class="border-t border-line align-top">
                                <td class="min-w-44 px-3 py-2.5"><span class="font-medium">{{ r.name }}</span><span class="block text-xs text-ink-muted">{{ r.student_number }} · {{ r.email }}</span></td>
                                <td class="px-3 py-2.5"><Badge :tone="tone(r.status)">{{ r.status }}</Badge><span v-if="r.drive" class="mt-1 block text-xs text-ink-muted">{{ r.drive }}</span></td>
                                <td class="whitespace-nowrap px-3 py-2.5 tabular-nums">{{ date(r.date) }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-xs">{{ r.method ? r.method.charAt(0).toUpperCase() + r.method.slice(1) : '—' }}<span v-if="r.collected_by" class="block text-ink-muted">{{ r.collected_by }}</span></td>
                                <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs">{{ r.order_id ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">{{ r.amount_sen ? rm(r.amount_sen) : '' }}</td>
                            </tr>
                            <tr v-if="!rows.length"><td colspan="6" class="px-3 py-8 text-center text-ink-muted">No one matches.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
