<script setup lang="ts">
import type { SessionOption } from '@/types';
import { router } from '@inertiajs/vue3';

const props = defineProps<{ sessions: SessionOption[]; selected: string; url: string }>();

function change(event: Event): void {
    const session = (event.target as HTMLSelectElement).value;
    router.get(props.url, { session }, { preserveScroll: true, preserveState: true, replace: true });
}
</script>

<template>
    <label class="inline-flex items-center gap-2 text-sm">
        <span class="text-ink-muted">Session</span>
        <select :value="selected" class="h-9 rounded-control border border-line bg-surface px-2 text-sm" @change="change">
            <option v-for="session in sessions" :key="session.id" :value="session.name">{{ session.name }}</option>
        </select>
    </label>
</template>
