<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Input from '@/Components/ui/Input.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Select from '@/Components/ui/Select.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, reactive } from 'vue';

interface Row {
    id: number;
    event: string;
    event_slug: string;
    club: string;
    starts_at: string;
    status: string;
    submitted_at: string | null;
    revenue: number;
    expenses: number;
    profit: number;
    downloadable: boolean;
    approved: boolean;
}
const props = defineProps<{
    proposals: Paginated<Row>;
    filters: { q: string; club: string; status: string | null; session: string };
    clubs: { slug: string; name: string }[];
    sessions: string[];
}>();

const f = reactive({ q: props.filters.q, club: props.filters.club, status: props.filters.status ?? '', session: props.filters.session });
const params = computed(() => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null)));
watchDebounced(f, () => router.get('/admin/proposals', params.value, { preserveState: true, preserveScroll: true, replace: true }), { debounce: 350, deep: true });
const exportUrl = (format: 'xlsx' | 'pdf'): string => `/admin/proposals/export.${format}?${new URLSearchParams(params.value as Record<string, string>).toString()}`;
const money = (n: number): string => n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const tone = (s: string): 'success' | 'warning' | 'danger' | 'info' => (s === 'approved' ? 'success' : s === 'returned' ? 'warning' : s === 'rejected' ? 'danger' : 'info');
</script>

<template>
    <Head title="Proposals & exports" />
    <PortalLayout title="Proposals & exports">
        <div class="grid gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <p class="max-w-2xl text-sm text-ink-muted">
                    Every submitted event proposal. Download one as PDF or Word, or everything for an event in one ZIP: the proposal, fund withdrawals, attendance, post mortem and
                    the event's finance lines.
                </p>
                <div class="flex gap-2">
                    <a :href="exportUrl('xlsx')" class="rounded-control bg-ink px-4 py-2 text-sm font-semibold text-white hover:bg-ink/85">Export list (Excel)</a>
                    <a :href="exportUrl('pdf')" class="rounded-control border border-line bg-surface px-4 py-2 text-sm font-medium hover:bg-surface-muted">PDF</a>
                </div>
            </div>

            <div class="grid gap-3 rounded-card border border-line bg-surface p-4 sm:grid-cols-4">
                <Input v-model="f.q" label="Search" placeholder="Event or club" />
                <Select v-model="f.club" label="Club" :options="[{ value: '', label: 'All clubs' }, ...clubs.map((c) => ({ value: c.slug, label: c.name }))]" />
                <Select
                    v-model="f.status"
                    label="Status"
                    :options="[
                        { value: '', label: 'Any status' },
                        { value: 'in_review', label: 'In review' },
                        { value: 'returned', label: 'Returned' },
                        { value: 'rejected', label: 'Rejected' },
                        { value: 'approved', label: 'Approved' },
                    ]"
                />
                <Select v-model="f.session" label="Session" :options="sessions.map((s) => ({ value: s, label: s }))" />
            </div>

            <div class="overflow-x-auto rounded-card border border-line bg-surface">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead class="bg-surface-muted text-left text-xs uppercase text-ink-muted">
                        <tr>
                            <th class="px-4 py-2">Event</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right">Revenue</th>
                            <th class="px-4 py-2 text-right">Expenses</th>
                            <th class="px-4 py-2 text-right">Profit / Loss</th>
                            <th class="px-4 py-2">Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in proposals.data" :key="p.id" class="border-t border-line align-top">
                            <td class="px-4 py-3">
                                <Link :href="`/documents/${p.id}`" class="font-medium hover:text-brand-blue">{{ p.event }}</Link>
                                <p class="text-xs text-ink-muted">{{ p.club }} · {{ p.starts_at }}<template v-if="p.submitted_at"> · submitted {{ p.submitted_at }}</template></p>
                            </td>
                            <td class="px-4 py-3"><Badge :tone="tone(p.status)">{{ p.status.replace('_', ' ') }}</Badge></td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ money(p.revenue) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ money(p.expenses) }}</td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums" :class="p.profit < 0 ? 'text-danger' : ''">{{ money(p.profit) }}</td>
                            <td class="px-4 py-3">
                                <div v-if="p.downloadable" class="flex flex-wrap gap-x-3 gap-y-1 text-sm font-medium text-brand-blue">
                                    <a :href="`/documents/${p.id}/download/pdf`">PDF</a>
                                    <a :href="`/documents/${p.id}/download/docx`">Word</a>
                                    <a :href="`/admin/proposals/${p.id}/package.zip`" title="Proposal, withdrawals, attendance, post mortem and finance">All + finance (ZIP)</a>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!proposals.data.length">
                            <td colspan="6" class="px-4 py-10 text-center text-ink-muted">No proposals match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="proposals.links" />
        </div>
    </PortalLayout>
</template>
