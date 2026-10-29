<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    session: string;
    clubs: { id: number; slug: string; name: string; category: string | null; managed_by: string; missing: string[]; status: string; last_activity: string | null }[];
}>();

const columns = [
    { key: 'name' as const, label: 'Club', sortable: true },
    { key: 'category' as const, label: 'Category', sortable: true },
    { key: 'managed_by' as const, label: 'Managed by', sortable: true },
    { key: 'status' as const, label: 'Status', sortable: true },
    { key: 'missing' as const, label: `Compulsory positions` },
];
</script>

<template>
    <Head title="Clubs" />
    <PortalLayout title="Clubs">
        <div class="grid gap-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm text-ink-muted">Session {{ session }}</p>
                <Link href="/admin/clubs/create"><Button>New club</Button></Link>
            </div>
            <Table :columns="columns" :rows="clubs" row-key="id">
                <template #cell-name="{ row }">
                    <Link :href="`/admin/clubs/${row.slug}/edit`" class="font-medium hover:underline">{{ row.name }}</Link>
                </template>
                <template #cell-managed_by="{ row }">
                    <Badge :tone="row.managed_by === 'committee' ? 'success' : 'neutral'">{{ row.managed_by === 'committee' ? 'Committee' : 'SLD' }}</Badge>
                </template>
                <template #cell-status="{ row }">
                    <Badge :tone="row.status === 'active' ? 'success' : row.status === 'inactive' ? 'danger' : 'warning'">{{ row.status === 'at_risk' ? 'At risk' : row.status === 'inactive' ? 'Inactive' : 'Active' }}</Badge>
                    <span class="ml-2 text-xs text-ink-muted">{{ row.last_activity ? `Last activity ${row.last_activity}` : 'No activity yet' }}</span>
                </template>
                <template #cell-missing="{ row }">
                    <Badge v-if="row.missing.length === 0" tone="success">Complete</Badge>
                    <Badge v-else tone="warning">Missing {{ row.missing.join(', ') }}</Badge>
                </template>
            </Table>
        </div>
    </PortalLayout>
</template>
