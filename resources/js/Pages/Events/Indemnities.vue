<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ event: { slug: string; name: string }; people: { name: string; student_number: string | null; signed_at: string | null }[] }>();
const signed = computed(() => props.people.filter((p) => p.signed_at).length);
const columns = [
    { key: 'name' as const, label: 'Name', sortable: true },
    { key: 'student_number' as const, label: 'Student ID' },
    { key: 'signed_at' as const, label: 'Status', sortable: true },
];
</script>

<template>
    <Head :title="`Indemnities · ${event.name}`" />
    <AppLayout>
        <div class="grid gap-4">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold">Indemnity forms</h1>
                    <p class="text-sm text-ink-muted">{{ event.name }} · {{ signed }} of {{ people.length }} signed</p>
                </div>
                <a :href="`/events/${event.slug}/indemnities/pdf`"><Button :disabled="signed === 0">Download all signed (PDF)</Button></a>
            </header>
            <Table :columns="columns" :rows="people" row-key="name">
                <template #cell-signed_at="{ row }">
                    <Badge :tone="row.signed_at ? 'success' : 'warning'">{{ row.signed_at ? `Signed ${row.signed_at}` : 'Not signed' }}</Badge>
                </template>
            </Table>
        </div>
    </AppLayout>
</template>
