<script setup lang="ts">
withDefaults(defineProps<{ placeholder?: string }>(), { placeholder: 'Search' });
const search = defineModel<string>('search', { default: '' });
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <input
            v-model="search"
            type="search"
            :placeholder="placeholder"
            class="h-10 min-w-48 flex-1 rounded-control border border-line bg-surface px-3 text-sm outline-none focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/30"
        />
        <slot />
    </div>
</template>
