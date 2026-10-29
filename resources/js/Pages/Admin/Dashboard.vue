<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Card from '@/Components/ui/Card.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { rm } from '@/lib/format';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    session: string;
    clubs_by_status: { active: number; at_risk: number; inactive: number };
    approvals: { role: string; waiting: number; overdue: number }[];
    overdue: { approvals: number; post_event: number };
    events_this_month: { slug: string; name: string; club: string; date: string; status: string }[];
    agm: { session: string; clubs: number; scheduled: number; reports: number; packages: number } | null;
    members: Record<'pending' | 'active' | 'grace' | 'terminated' | 'alumni', number>;
    payments: { paid_sen: number; pending_review: number };
    balances: { slug: string; name: string; ready: boolean; balance_sen: number }[];
}>();

const totalClubs = computed(() => props.clubs_by_status.active + props.clubs_by_status.at_risk + props.clubs_by_status.inactive);
const maxWaiting = computed(() => Math.max(1, ...props.approvals.map((a) => a.waiting)));
// Status meaning is never colour alone: each segment carries its label and count beside it.
const statuses = [
    { key: 'active', label: 'Active', bar: 'bg-success', tone: 'success' },
    { key: 'at_risk', label: 'At risk', bar: 'bg-warning', tone: 'warning' },
    { key: 'inactive', label: 'Inactive', bar: 'bg-danger', tone: 'danger' },
] as const;
const pct = (n: number, of: number): string => `${of ? Math.round((n / of) * 100) : 0}%`;
</script>

<template>
    <Head title="SLD dashboard" />
    <PortalLayout :title="`Dashboard · ${session}`">
        <div class="grid gap-6">
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <p class="text-xs text-ink-muted">Active members</p>
                    <p class="text-3xl font-bold tabular-nums">{{ members.active + members.grace }}</p>
                    <p class="text-xs text-ink-muted">{{ members.grace }} in grace · {{ members.alumni }} alumni</p>
                </div>
                <div class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <p class="text-xs text-ink-muted">Fees collected this session</p>
                    <p class="text-3xl font-bold tabular-nums">{{ rm(payments.paid_sen) }}</p>
                    <p class="text-xs text-ink-muted">{{ payments.pending_review }} receipts waiting for Treasurers</p>
                </div>
                <div class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <p class="text-xs text-ink-muted">Overdue approvals</p>
                    <p class="text-3xl font-bold tabular-nums" :class="overdue.approvals ? 'text-danger' : ''">{{ overdue.approvals }}</p>
                    <p class="text-xs text-ink-muted">past their 3-working-day step</p>
                </div>
                <div class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <p class="text-xs text-ink-muted">Overdue post-event documents</p>
                    <p class="text-3xl font-bold tabular-nums" :class="overdue.post_event ? 'text-danger' : ''">{{ overdue.post_event }}</p>
                    <p class="text-xs text-ink-muted">finance, receipts, post mortem, reel…</p>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <template #title>Clubs by status</template>
                    <div class="flex h-3 gap-0.5 overflow-hidden rounded-full bg-surface-muted" role="img" :aria-label="statuses.map((s) => `${s.label} ${clubs_by_status[s.key]}`).join(', ')">
                        <div v-for="s in statuses" :key="s.key" :class="s.bar" :style="{ width: pct(clubs_by_status[s.key], totalClubs) }" :title="`${s.label}: ${clubs_by_status[s.key]}`" />
                    </div>
                    <ul class="mt-3 flex flex-wrap gap-4 text-sm">
                        <li v-for="s in statuses" :key="s.key" class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full" :class="s.bar" />{{ s.label }} <strong class="tabular-nums">{{ clubs_by_status[s.key] }}</strong>
                        </li>
                    </ul>
                    <Link href="/admin/clubs" class="mt-3 inline-block text-sm text-brand-blue">See all clubs →</Link>
                </Card>

                <Card>
                    <template #title>Approvals waiting, by approver</template>
                    <ul v-if="approvals.length" class="grid gap-2 text-sm">
                        <li v-for="a in approvals" :key="a.role" class="grid grid-cols-[9rem_1fr_auto] items-center gap-3" :title="`${a.role}: ${a.waiting} waiting, ${a.overdue} overdue`">
                            <span class="truncate">{{ a.role }}</span>
                            <span class="h-2.5 rounded-r bg-surface-muted"><span class="block h-full rounded-r bg-brand-blue" :style="{ width: pct(a.waiting, maxWaiting) }" /></span>
                            <span class="tabular-nums">{{ a.waiting }}<Badge v-if="a.overdue" tone="danger" class="ml-2">{{ a.overdue }} overdue</Badge></span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-ink-muted">Nothing waiting.</p>
                </Card>

                <Card>
                    <template #title>AGM {{ agm?.session ?? '' }}</template>
                    <template v-if="agm">
                        <ul class="grid gap-3 text-sm">
                            <li v-for="row in [{ label: 'AGM scheduled', n: agm.scheduled }, { label: 'AGM Report submitted', n: agm.reports }, { label: 'Package submitted', n: agm.packages }]" :key="row.label" class="grid gap-1">
                                <span class="flex justify-between"><span>{{ row.label }}</span><span class="tabular-nums">{{ row.n }} / {{ agm.clubs }}</span></span>
                                <span class="h-2 rounded-full bg-surface-muted"><span class="block h-full rounded-full bg-brand-cyan" :style="{ width: pct(row.n, agm.clubs) }" /></span>
                            </li>
                        </ul>
                        <Link href="/admin/agm" class="mt-3 inline-block text-sm text-brand-blue">AGM details →</Link>
                    </template>
                    <p v-else class="text-sm text-ink-muted">No AGM period open. <Link href="/admin/agm" class="text-brand-blue">Open one</Link></p>
                </Card>

                <Card>
                    <template #title>Events this month</template>
                    <ul v-if="events_this_month.length" class="divide-y divide-line text-sm">
                        <li v-for="e in events_this_month" :key="e.slug" class="flex items-center justify-between gap-3 py-2">
                            <Link :href="`/events/${e.slug}`" class="min-w-0"><span class="font-medium">{{ e.name }}</span> <span class="text-ink-muted">· {{ e.club }} · {{ e.date }}</span></Link>
                            <Badge>{{ e.status.replace('_', ' ') }}</Badge>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-ink-muted">No events this month.</p>
                </Card>
            </div>

            <Card>
                <template #title>Club balances</template>
                <ul class="grid gap-1 text-sm sm:grid-cols-2">
                    <li v-for="b in balances" :key="b.slug" class="flex justify-between gap-3 rounded-control px-2 py-1.5 hover:bg-surface-muted">
                        <Link :href="`/clubs/${b.slug}/finance`">{{ b.name }}</Link>
                        <span v-if="b.ready" class="tabular-nums" :class="b.balance_sen < 0 ? 'text-danger' : ''">{{ rm(b.balance_sen) }}</span>
                        <Badge v-else tone="warning">No opening balance</Badge>
                    </li>
                </ul>
            </Card>
        </div>
    </PortalLayout>
</template>
