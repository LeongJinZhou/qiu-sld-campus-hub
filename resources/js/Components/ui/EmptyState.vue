<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="grid justify-items-center gap-2 rounded-card border border-dashed border-line px-6 py-12 text-center">
        <p class="font-semibold">{{ title }}</p>
        <p v-if="description" class="max-w-sm text-sm text-ink-muted">{{ description }}</p>
        <div v-if="$slots.default" class="mt-2"><slot /></div>
    </div>
</template>
