<script setup lang="ts">
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon from '@/Components/ui/Icon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ items: { id: string; title: string; body: string; url: string | null; at: string | null; unread: boolean }[] }>();
</script>

<template>
    <Head title="Notifications" />
    <AppLayout>
        <div class="mx-auto grid max-w-xl gap-4">
            <h1 class="text-xl font-bold">Notifications</h1>
            <EmptyState v-if="!items.length" title="You're all caught up" description="Approvals, returned documents and event reminders show up here." />
            <ul class="grid gap-2">
                <li v-for="n in items" :key="n.id">
                    <component :is="n.url ? Link : 'div'" :href="n.url ?? undefined" class="flex gap-3 rounded-card border border-line bg-surface p-4 transition hover:shadow-card" :class="n.unread ? 'border-brand-magenta/40 bg-brand-magenta/5' : ''">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-orange to-brand-magenta text-white"><Icon name="bell" :size="18" /></span>
                        <span class="grid gap-0.5">
                            <strong class="text-sm">{{ n.title }}</strong>
                            <span class="text-sm text-ink-muted">{{ n.body }}</span>
                            <span class="text-xs text-ink-muted">{{ n.at }}</span>
                        </span>
                    </component>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
