<script setup lang="ts">
import Pagination from '@/Components/ui/Pagination.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Paginated } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    entries: Paginated<{ id: number; at: string | null; actor: string; action: string; subject: string | null; data: Record<string, unknown> | null }>;
}>();
</script>

<template>
    <Head title="Audit log" />
    <PortalLayout title="Audit log">
        <div class="grid gap-4">
            <div class="overflow-x-auto rounded-card border border-line bg-surface">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-muted text-xs uppercase text-ink-muted">
                        <tr><th class="px-4 py-3">When</th><th class="px-4 py-3">Who</th><th class="px-4 py-3">Action</th><th class="px-4 py-3">Subject</th><th class="px-4 py-3">Details</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="entry in entries.data" :key="entry.id" class="border-t border-line align-top">
                            <td class="whitespace-nowrap px-4 py-3 text-ink-muted">{{ entry.at }}</td>
                            <td class="px-4 py-3">{{ entry.actor }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ entry.action }}</td>
                            <td class="px-4 py-3">{{ entry.subject ?? '—' }}</td>
                            <td class="px-4 py-3"><code v-if="entry.data" class="text-xs text-ink-muted">{{ JSON.stringify(entry.data) }}</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="entries.links" />
        </div>
    </PortalLayout>
</template>
