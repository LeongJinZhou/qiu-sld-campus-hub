<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import type { ApprovalProgress } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    stats: { waiting: number; overdue: number; decided_this_month: number; avg_days: number | null };
    recent: { document_id: number; type: string; club: string; decision: string; decided_at: string | null }[];
    items: { id: number; type: string; club: string; event: string | null; role: string; step_no: number; due_at: string | null; overdue: boolean; progress: ApprovalProgress }[] }>();
</script>

<template>
    <Head title="Approvals" />
    <AppLayout>
        <div class="grid gap-4">
            <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div v-for="tile in [
                    { label: 'Waiting for you', value: stats.waiting, alert: false },
                    { label: 'Overdue', value: stats.overdue, alert: stats.overdue > 0 },
                    { label: 'Decided this month', value: stats.decided_this_month, alert: false },
                    { label: 'Average days to decide', value: stats.avg_days ?? '—', alert: false },
                ]" :key="tile.label" class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <p class="text-xs text-ink-muted">{{ tile.label }}</p>
                    <p class="text-2xl font-bold tabular-nums" :class="tile.alert ? 'text-danger' : ''">{{ tile.value }}</p>
                </div>
            </section>
            <h1 class="text-xl font-bold">Waiting for you</h1>
            <EmptyState v-if="items.length === 0" title="Nothing to approve" description="You'll get an email and a notification when a document reaches your step." />
            <ul class="grid gap-2">
                <li v-for="item in items" :key="item.id">
                    <Link :href="`/approvals/${item.id}`" class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card transition hover:shadow-pop">
                        <span class="flex flex-wrap items-center justify-between gap-3">
                            <span class="grid">
                                <strong>{{ item.type }} · {{ item.event ?? item.club }}</strong>
                                <span class="text-sm text-ink-muted">{{ item.club }} · step {{ item.step_no }} as {{ item.role }}</span>
                            </span>
                            <Badge v-if="item.due_at" :tone="item.overdue ? 'danger' : 'neutral'">{{ item.overdue ? 'Overdue' : 'Due' }} {{ item.due_at }}</Badge>
                        </span>
                        <ApprovalTracker :progress="item.progress" compact />
                    </Link>
                </li>
            </ul>
            <section v-if="recent.length" class="grid gap-2">
                <h2 class="font-semibold">Recently decided</h2>
                <ul class="divide-y divide-line rounded-card border border-line bg-surface text-sm">
                    <li v-for="r in recent" :key="r.document_id + r.decision" class="flex justify-between gap-3 p-3">
                        <Link :href="`/documents/${r.document_id}`">{{ r.type }} · {{ r.club }}</Link>
                        <span class="text-ink-muted">{{ r.decision }} {{ r.decided_at }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
