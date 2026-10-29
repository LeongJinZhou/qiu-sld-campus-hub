<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps<{
    event: { slug: string; name: string };
    items: { id: number; label: string; due_on: string; overdue: boolean; submitted_at: string | null; file_name: string | null; file_url: string | null }[];
}>();

function upload(id: number, event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) router.post(`/events/${props.event.slug}/documents/${id}`, { file }, { preserveScroll: true, forceFormData: true });
}
</script>

<template>
    <Head :title="`Post-event documents · ${event.name}`" />
    <AppLayout>
        <div class="grid gap-4">
            <div>
                <h1 class="text-xl font-bold">Post-event documents</h1>
                <p class="text-sm text-ink-muted">{{ event.name }} · Finance record and receipts within 1 week; everything else within 2 weeks. Reminders are automatic.</p>
            </div>
            <ul class="grid gap-2">
                <li v-for="item in items" :key="item.id" class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-surface p-4">
                    <div class="grid">
                        <strong class="text-sm">{{ item.label }}</strong>
                        <span class="text-xs text-ink-muted">Due {{ item.due_on }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <a v-if="item.file_url" :href="item.file_url" class="text-xs text-brand-blue">{{ item.file_name }}</a>
                        <Badge :tone="item.submitted_at ? 'success' : item.overdue ? 'danger' : 'neutral'">{{ item.submitted_at ? `Submitted ${item.submitted_at}` : item.overdue ? 'Overdue' : 'Pending' }}</Badge>
                        <label class="cursor-pointer rounded-control border border-line px-3 py-1.5 text-xs font-medium hover:bg-surface-muted">
                            {{ item.submitted_at ? 'Replace' : 'Upload' }}
                            <input type="file" class="sr-only" @change="upload(item.id, $event)" />
                        </label>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
