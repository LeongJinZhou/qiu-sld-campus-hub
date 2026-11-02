<script setup lang="ts">
import MoneyChart from '@/Components/charts/MoneyChart.vue';
import Badge from '@/Components/ui/Badge.vue';
import { date, rm } from '@/lib/format';
import { Link } from '@inertiajs/vue3';

/** The club's whole session compiled for the AGM: events, weekly activities, money, and every download. */
const props = defineProps<{
    club: { slug: string; name: string };
    year: {
        session: string;
        session_id: number;
        events: { slug: string; name: string; date: string; registered: number; attended: number; package: boolean | null }[];
        weekly: { held: number; reported: number };
        finance: {
            income_sen: number;
            expense_sen: number;
            monthly: { key: string; label: string; income_sen: number; expense_sen: number }[];
            categories: { category: string; income_sen: number; expense_sen: number }[];
        };
        members: number;
    };
}>();
const q = `session=${encodeURIComponent(props.year.session)}`;
const downloads = [
    { label: 'Financial Report (02)', href: `/clubs/${props.club.slug}/finance/export/pdf?${q}` },
    { label: 'Finance workbook (Excel)', href: `/clubs/${props.club.slug}/finance/workbook/xlsx?period=session&${q}` },
    { label: 'Full Member List (10)', href: `/clubs/${props.club.slug}/members/export/xlsx` },
    { label: 'Annual Activities Report (12)', href: `/clubs/${props.club.slug}/annual-report/pdf?session=${props.year.session_id}` },
];
const label = (c: string): string => c.replace(/_/g, ' ').replace(/^\w/, (m) => m.toUpperCase());
</script>

<template>
    <section class="grid gap-4 rounded-card border border-line bg-surface p-5 shadow-card">
        <header class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-lg font-bold">{{ year.session }} in review</h2>
                <p class="text-sm text-ink-muted">Compiled automatically for the AGM from this session's records only.</p>
            </div>
        </header>

        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-card bg-surface-muted p-3">
                <dt class="text-xs text-ink-muted">Approved events</dt>
                <dd class="text-2xl font-bold tabular-nums">{{ year.events.length }}</dd>
            </div>
            <div class="rounded-card bg-surface-muted p-3">
                <dt class="text-xs text-ink-muted">Weekly activities reported</dt>
                <dd class="text-2xl font-bold tabular-nums">{{ year.weekly.reported }} / {{ year.weekly.held }}</dd>
            </div>
            <div class="rounded-card bg-surface-muted p-3">
                <dt class="text-xs text-ink-muted">Net for the year</dt>
                <dd class="text-2xl font-bold tabular-nums" :class="year.finance.income_sen - year.finance.expense_sen < 0 ? 'text-danger' : ''">
                    {{ rm(year.finance.income_sen - year.finance.expense_sen) }}
                </dd>
            </div>
            <div class="rounded-card bg-surface-muted p-3">
                <dt class="text-xs text-ink-muted">Active members</dt>
                <dd class="text-2xl font-bold tabular-nums">{{ year.members }}</dd>
            </div>
        </dl>

        <div class="grid gap-2">
            <h3 class="text-sm font-semibold">Events</h3>
            <p v-if="!year.events.length" class="text-sm text-ink-muted">No approved events this session.</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-ink-muted">
                        <tr>
                            <th class="py-1 pr-3">Date</th>
                            <th class="py-1 pr-3">Event</th>
                            <th class="py-1 pr-3 text-right">Registered</th>
                            <th class="py-1 pr-3 text-right">Attended</th>
                            <th class="py-1">Completion package</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in year.events" :key="e.slug" class="border-t border-line">
                            <td class="whitespace-nowrap py-2 pr-3">{{ date(e.date) }}</td>
                            <td class="py-2 pr-3">
                                <Link :href="`/events/${e.slug}/documents`" class="font-medium hover:underline">{{ e.name }}</Link>
                            </td>
                            <td class="py-2 pr-3 text-right tabular-nums">{{ e.registered }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums">{{ e.attended }}</td>
                            <td class="py-2">
                                <Badge v-if="e.package === null" tone="neutral">Upcoming</Badge>
                                <Badge v-else :tone="e.package ? 'success' : 'warning'">{{ e.package ? 'Complete' : 'Missing items' }}</Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_18rem]">
            <MoneyChart title="Money in and out, month by month" :rows="year.finance.monthly" />
            <div class="grid content-start gap-2">
                <h3 class="text-sm font-semibold">By category</h3>
                <p v-if="!year.finance.categories.length" class="text-sm text-ink-muted">No ledger entries this session.</p>
                <ul class="grid gap-1 text-sm">
                    <li v-for="c in year.finance.categories" :key="c.category" class="flex justify-between gap-2 border-b border-line py-1">
                        <span>{{ label(c.category) }}</span>
                        <span class="tabular-nums"
                            ><span v-if="c.income_sen">+{{ rm(c.income_sen) }}</span> <span v-if="c.expense_sen" class="text-danger">−{{ rm(c.expense_sen) }}</span></span
                        >
                    </li>
                    <li class="flex justify-between gap-2 pt-1 font-semibold">
                        <span>Total</span>
                        <span class="tabular-nums"
                            >+{{ rm(year.finance.income_sen) }} <span class="text-danger">−{{ rm(year.finance.expense_sen) }}</span></span
                        >
                    </li>
                </ul>
            </div>
        </div>

        <div class="grid gap-2">
            <h3 class="text-sm font-semibold">AGM documents for {{ year.session }}</h3>
            <div class="flex flex-wrap gap-2">
                <a v-for="d in downloads" :key="d.label" :href="d.href" class="rounded-control border border-line px-3 py-1.5 text-sm hover:bg-surface-muted">{{ d.label }}</a>
            </div>
        </div>
    </section>
</template>
