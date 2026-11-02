<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Select from '@/Components/ui/Select.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { date, rm } from '@/lib/format';
import { matchesSearch } from '@/lib/search';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Pkg {
    slug: string;
    name: string;
    date: string;
    missing: string[];
    overdue: boolean;
    complete: boolean;
}
interface Row {
    slug: string;
    name: string;
    weekly: { key: string; held: number; submitted: number }[];
    weekly_missing: number;
    events: Pkg[];
    events_incomplete: number;
    agm: { report: string; package: boolean; acknowledged: boolean } | null;
}
const props = defineProps<{
    session: string;
    sessions: string[];
    months: { key: string; label: string }[];
    clubs: Row[];
    transfers: { id: number; club: string; for: string; total_sen: number; stage: string; due_on: string; days_late: number }[];
    totals: { weekly_missing: number; events_incomplete: number; agm_missing: number };
}>();

const search = ref('');
const onlyProblems = ref(false);
const rows = computed(() =>
    props.clubs.filter((c) => matchesSearch(search.value, c.name) && (!onlyProblems.value || c.weekly_missing > 0 || c.events_incomplete > 0 || !c.agm?.package)),
);
const open = ref<string | null>(null);
const changeSession = (s: string | number | null | undefined): void => router.get('/admin/tracking', { session: s }, { preserveScroll: true });
// Cell state for a month: nothing held (blank), all reported (done), some missing (missing count).
const cell = (w: { held: number; submitted: number }): { text: string; cls: string; title: string } =>
    w.held === 0
        ? { text: '·', cls: 'text-ink-muted', title: 'No weekly activity held' }
        : w.submitted >= w.held
          ? { text: '✓', cls: 'bg-success/15 text-success', title: `${w.submitted} of ${w.held} reports submitted` }
          : { text: `${w.held - w.submitted}`, cls: 'bg-danger/15 font-bold text-danger', title: `${w.held - w.submitted} of ${w.held} reports missing` };
</script>

<template>
    <Head title="Submissions tracker" />
    <PortalLayout title="Submissions tracker">
        <div class="grid gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <p class="text-sm text-ink-muted">What every club has submitted for {{ session }}, and what is missing or overdue.</p>
                <Select :model-value="session" label="Session" :options="sessions.map((s) => ({ value: s, label: s }))" @update:model-value="changeSession" />
            </div>

            <section class="grid gap-3 sm:grid-cols-4">
                <div class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <p class="text-xs text-ink-muted">Weekly reports missing</p>
                    <p class="text-2xl font-bold tabular-nums" :class="totals.weekly_missing ? 'text-danger' : 'text-success'">{{ totals.weekly_missing }}</p>
                </div>
                <div class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <p class="text-xs text-ink-muted">Events with an incomplete package</p>
                    <p class="text-2xl font-bold tabular-nums" :class="totals.events_incomplete ? 'text-danger' : 'text-success'">{{ totals.events_incomplete }}</p>
                </div>
                <div class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <p class="text-xs text-ink-muted">Clubs without an AGM package</p>
                    <p class="text-2xl font-bold tabular-nums" :class="totals.agm_missing ? 'text-warning' : 'text-success'">{{ totals.agm_missing }}</p>
                </div>
                <div class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <p class="text-xs text-ink-muted">Withdrawal transfers overdue</p>
                    <p class="text-2xl font-bold tabular-nums" :class="transfers.length ? 'text-danger' : 'text-success'">{{ transfers.length }}</p>
                </div>
            </section>

            <section v-if="transfers.length" class="grid gap-2 rounded-card border border-danger/40 bg-danger/5 p-4">
                <h2 class="font-semibold text-danger">Transfers past the deadline</h2>
                <ul class="grid gap-1 text-sm">
                    <li v-for="t in transfers" :key="t.id" class="flex flex-wrap justify-between gap-2">
                        <Link :href="`/withdrawals/${t.id}`" class="font-medium hover:underline">{{ t.club }} · {{ t.for }}</Link>
                        <span class="tabular-nums"
                            >{{ rm(t.total_sen) }} · due {{ date(t.due_on) }} · <strong class="text-danger">{{ t.days_late }} days late</strong></span
                        >
                    </li>
                </ul>
            </section>

            <div class="flex flex-wrap items-center gap-3">
                <input v-model="search" type="search" placeholder="Find a club" class="h-10 min-w-48 flex-1 rounded-control border border-line bg-surface px-3 text-sm" />
                <label class="flex items-center gap-2 text-sm"><input v-model="onlyProblems" type="checkbox" class="size-4" /> Only clubs with something missing</label>
            </div>

            <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                <table class="w-full text-sm">
                    <thead class="bg-surface-muted text-left text-xs text-ink-muted">
                        <tr>
                            <th class="sticky left-0 bg-surface-muted px-3 py-2">Club</th>
                            <th v-for="m in months" :key="m.key" class="px-2 py-2 text-center">{{ m.label }}</th>
                            <th class="px-3 py-2">Events</th>
                            <th class="px-3 py-2">AGM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="c in rows" :key="c.slug">
                            <tr class="border-t border-line">
                                <td class="sticky left-0 bg-surface px-3 py-2 font-medium">
                                    <button type="button" class="text-left hover:underline" @click="open = open === c.slug ? null : c.slug">{{ c.name }}</button>
                                </td>
                                <td v-for="w in c.weekly" :key="w.key" class="px-1 py-2 text-center">
                                    <span class="inline-grid size-7 place-items-center rounded tabular-nums" :class="cell(w).cls" :title="cell(w).title">{{ cell(w).text }}</span>
                                </td>
                                <td class="px-3 py-2">
                                    <Badge v-if="c.events_incomplete" tone="danger">{{ c.events_incomplete }} incomplete</Badge>
                                    <Badge v-else-if="c.events.length" tone="success">All complete</Badge>
                                    <span v-else class="text-ink-muted">—</span>
                                </td>
                                <td class="px-3 py-2">
                                    <Badge v-if="c.agm?.acknowledged" tone="success">Acknowledged</Badge>
                                    <Badge v-else-if="c.agm?.package" tone="info">Package in</Badge>
                                    <Badge v-else-if="c.agm" tone="warning">Report {{ c.agm.report.replace('_', ' ') }}</Badge>
                                    <Badge v-else tone="neutral">Not held</Badge>
                                </td>
                            </tr>
                            <tr v-if="open === c.slug" class="bg-surface-muted/60">
                                <td :colspan="months.length + 3" class="px-3 py-3">
                                    <p v-if="!c.events.length" class="text-ink-muted">No finished events this session.</p>
                                    <ul class="grid gap-1">
                                        <li v-for="e in c.events" :key="e.slug" class="flex flex-wrap items-center gap-2">
                                            <Link :href="`/events/${e.slug}/documents`" class="font-medium hover:underline">{{ date(e.date) }} · {{ e.name }}</Link>
                                            <Badge :tone="e.complete ? 'success' : e.overdue ? 'danger' : 'warning'">{{
                                                e.complete ? 'Complete' : e.overdue ? 'Overdue' : 'In progress'
                                            }}</Badge>
                                            <span v-if="e.missing.length" class="text-xs text-ink-muted">Missing: {{ e.missing.join(', ') }}</span>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-ink-muted">Months: ✓ every weekly activity reported · number = reports missing · · = no weekly activity held. Click a club for its events.</p>
        </div>
    </PortalLayout>
</template>
