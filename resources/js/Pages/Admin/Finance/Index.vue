<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Table from '@/Components/ui/Table.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { rm } from '@/lib/format';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ clubs: { slug: string; name: string; ready: boolean; balance_sen: number; last_entry: string | null }[] }>();
const columns = [
    { key: 'name' as const, label: 'Club', sortable: true },
    { key: 'ready' as const, label: 'Ledger' },
    { key: 'balance_sen' as const, label: 'Balance', sortable: true },
    { key: 'last_entry' as const, label: 'Last entry', sortable: true },
];
</script>

<template>
    <Head title="Club finance" />
    <PortalLayout title="All clubs · balances">
        <Table :columns="columns" :rows="clubs" row-key="slug">
            <template #cell-name="{ row }"><Link :href="`/clubs/${row.slug}/finance`" class="font-medium hover:underline">{{ row.name }}</Link></template>
            <template #cell-ready="{ row }"><Badge :tone="row.ready ? 'success' : 'warning'">{{ row.ready ? 'Open' : 'Needs opening balance' }}</Badge></template>
            <template #cell-balance_sen="{ row }"><span class="tabular-nums" :class="row.balance_sen < 0 ? 'text-danger' : ''">{{ rm(row.balance_sen) }}</span></template>
        </Table>
    </PortalLayout>
</template>
