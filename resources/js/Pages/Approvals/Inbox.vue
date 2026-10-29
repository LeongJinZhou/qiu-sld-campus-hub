<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ items: { id: number; type: string; club: string; event: string | null; role: string; step_no: number; due_at: string | null; overdue: boolean }[] }>();
</script>

<template>
    <Head title="Approvals" />
    <AppLayout>
        <div class="grid gap-4">
            <h1 class="text-xl font-bold">Waiting for you</h1>
            <EmptyState v-if="items.length === 0" title="Nothing to approve" description="You'll get an email and a notification when a document reaches your step." />
            <ul class="grid gap-2">
                <li v-for="item in items" :key="item.id">
                    <Link :href="`/approvals/${item.id}`" class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-surface p-4 hover:shadow-card">
                        <span class="grid">
                            <strong>{{ item.type }} · {{ item.event ?? item.club }}</strong>
                            <span class="text-sm text-ink-muted">{{ item.club }} · step {{ item.step_no }} as {{ item.role }}</span>
                        </span>
                        <Badge v-if="item.due_at" :tone="item.overdue ? 'danger' : 'neutral'">{{ item.overdue ? 'Overdue' : 'Due' }} {{ item.due_at }}</Badge>
                    </Link>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
