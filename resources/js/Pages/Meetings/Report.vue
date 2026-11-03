<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Input from '@/Components/ui/Input.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { dateTime } from '@/lib/format';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, reactive } from 'vue';

/** Meetings report: one club's (committee and advisor) or every club's (SLD, in the admin portal). */
interface Row {
    id: number;
    date: string;
    club: string;
    club_slug: string;
    kind: string;
    title: string;
    audience: string;
    venue: string | null;
    pax: number | null;
    invited: number;
    present: number;
    checked_out: number;
    rate: number;
    minutes: string;
    upcoming: boolean;
}
const props = defineProps<{
    club: { slug: string; name: string } | null;
    meetings: Paginated<Row>;
    totals: { meetings: number; committee: number; activities: number; average_rate: number; minutes_approved: number };
    filters: { kind: string; month: string; club: string; session: string };
    kinds: Record<string, string>;
    clubs: { slug: string; name: string }[];
    sessions: string[];
    exportBase: string;
}>();

const title = computed(() => (props.club ? `Meetings report · ${props.club.name}` : 'Meetings'));
const layout = computed(() => (props.club ? AppLayout : PortalLayout));
const layoutProps = computed(() => (props.club ? { wide: true } : { title: title.value }));
const f = reactive({ ...props.filters });
const params = computed(() => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null)) as Record<string, string>);
const pageUrl = props.club ? `/clubs/${props.club.slug}/meetings/report` : '/admin/meetings';
watchDebounced(f, () => router.get(pageUrl, params.value, { preserveState: true, preserveScroll: true, replace: true }), { debounce: 300, deep: true });
const exportUrl = (format: 'xlsx' | 'pdf'): string => `${props.exportBase}/export.${format}?${new URLSearchParams(params.value).toString()}`;
const minutesTone = (s: string): 'success' | 'warning' | 'info' | 'neutral' => (s === 'Approved' ? 'success' : s === 'Returned' ? 'warning' : s === 'In Review' ? 'info' : 'neutral');
const cards = computed(() => [
    { label: 'Meetings', value: props.totals.meetings },
    { label: 'Committee meetings', value: props.totals.committee },
    { label: 'Weekly activities', value: props.totals.activities },
    { label: 'Average attendance', value: `${props.totals.average_rate}%` },
    { label: 'Minutes approved', value: props.totals.minutes_approved },
]);
</script>

<template>
    <Head :title="title" />
    <component :is="layout" v-bind="layoutProps">
        <div class="grid gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div v-if="club">
                    <h1 class="text-xl font-bold">{{ title }}</h1>
                    <Link :href="`/clubs/${club.slug}/meetings`" class="text-sm text-brand-blue">Back to meetings</Link>
                </div>
                <p v-else class="max-w-2xl text-sm text-ink-muted">Every club's committee meetings and weekly activities: who was invited, who came (QR or ticked), who checked out, and the minutes.</p>
                <div class="flex gap-2">
                    <a :href="exportUrl('xlsx')" class="rounded-control bg-ink px-4 py-2 text-sm font-semibold text-white hover:bg-ink/85">Export (Excel)</a>
                    <a :href="exportUrl('pdf')" class="rounded-control border border-line bg-surface px-4 py-2 text-sm font-medium hover:bg-surface-muted">PDF</a>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div v-for="c in cards" :key="c.label" class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <p class="text-xs text-ink-muted">{{ c.label }}</p>
                    <p class="text-2xl font-bold tabular-nums">{{ c.value }}</p>
                </div>
            </div>

            <div class="grid gap-3 rounded-card border border-line bg-surface p-4" :class="club ? 'sm:grid-cols-3' : 'sm:grid-cols-4'">
                <Select v-if="!club" v-model="f.club" label="Club" :options="[{ value: '', label: 'All clubs' }, ...clubs.map((c) => ({ value: c.slug, label: c.name }))]" />
                <Select v-model="f.kind" label="Type" :options="[{ value: '', label: 'All types' }, ...Object.entries(kinds).map(([value, label]) => ({ value, label }))]" />
                <Input v-model="f.month" type="month" label="Month" />
                <Select v-model="f.session" label="Session" :options="sessions.map((s) => ({ value: s, label: s }))" />
            </div>

            <div class="overflow-x-auto rounded-card border border-line bg-surface">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-muted">
                        <tr>
                            <th class="px-3 py-3 font-medium">Date</th>
                            <th v-if="!club" class="px-3 py-3 font-medium">Club</th>
                            <th class="px-3 py-3 font-medium">Meeting</th>
                            <th class="px-3 py-3 font-medium">For</th>
                            <th class="px-3 py-3 text-right font-medium">Invited</th>
                            <th class="px-3 py-3 text-right font-medium">Present</th>
                            <th class="px-3 py-3 text-right font-medium">Out</th>
                            <th class="px-3 py-3 text-right font-medium">Rate</th>
                            <th class="px-3 py-3 font-medium">Minutes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in meetings.data" :key="r.id" class="border-t border-line align-top">
                            <td class="whitespace-nowrap px-3 py-2 text-xs">{{ dateTime(r.date) }}</td>
                            <td v-if="!club" class="px-3 py-2">{{ r.club }}</td>
                            <td class="px-3 py-2">
                                <Link :href="`/meetings/${r.id}`" class="font-medium text-brand-blue">{{ r.title }}</Link>
                                <div class="text-xs text-ink-muted">{{ r.kind }}<template v-if="r.venue"> · {{ r.venue }}</template><template v-if="r.pax"> · {{ r.pax }} pax planned</template></div>
                            </td>
                            <td class="px-3 py-2 text-xs">{{ r.audience }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ r.invited }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ r.present }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ r.checked_out }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ r.upcoming ? '—' : `${r.rate}%` }}</td>
                            <td class="px-3 py-2"><Badge :tone="minutesTone(r.minutes)">{{ r.minutes }}</Badge></td>
                        </tr>
                        <tr v-if="!meetings.data.length"><td :colspan="club ? 8 : 9" class="px-3 py-8 text-center text-ink-muted">No meetings match.</td></tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="meetings.links" />
        </div>
    </component>
</template>
