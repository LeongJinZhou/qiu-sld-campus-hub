<script setup lang="ts">
defineProps<{ title: string; hint?: string }>();
</script>

<template>
    <section class="grid gap-4 rounded-card border border-line bg-surface p-5 shadow-card">
        <header>
            <h2 class="text-base font-semibold">{{ title }}</h2>
            <p v-if="hint" class="text-sm text-ink-muted">{{ hint }}</p>
        </header>
        <slot />
    </section>
</template>
