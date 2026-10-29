<script setup lang="ts">
withDefaults(defineProps<{ padded?: boolean }>(), { padded: true });
</script>

<template>
    <section class="rounded-card border border-line bg-surface shadow-card" :class="padded ? 'p-5' : ''">
        <header v-if="$slots.title" class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold"><slot name="title" /></h2>
            <slot name="actions" />
        </header>
        <slot />
    </section>
</template>
