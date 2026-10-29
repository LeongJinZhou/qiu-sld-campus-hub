<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    runs: { id: number; trigger: string; records_seen: number; upserted: number; error: string | null; started_at: string; seconds: number | null }[];
}>();

const form = useForm({});
const columns = [
    { key: 'started_at' as const, label: 'Started', sortable: true },
    { key: 'trigger' as const, label: 'Trigger', sortable: true },
    { key: 'upserted' as const, label: 'Upserted / seen' },
    { key: 'seconds' as const, label: 'Duration' },
    { key: 'error' as const, label: 'Result' },
];
</script>

<template>
    <Head title="camsys sync" />
    <PortalLayout title="camsys sync">
        <div class="grid gap-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm text-ink-muted">Runs nightly at 02:00 and for each student on login. SELECT only.</p>
                <Button :loading="form.processing" @click="form.post('/admin/sync', { preserveScroll: true })">Sync now</Button>
            </div>
            <Table :columns="columns" :rows="runs" row-key="id">
                <template #cell-upserted="{ row }">{{ row.upserted }} / {{ row.records_seen }}</template>
                <template #cell-seconds="{ row }">{{ row.seconds === null ? '—' : `${row.seconds}s` }}</template>
                <template #cell-error="{ row }">
                    <Badge v-if="row.error" tone="danger" :title="row.error">Failed</Badge>
                    <Badge v-else tone="success">OK</Badge>
                </template>
            </Table>
        </div>
    </PortalLayout>
</template>
