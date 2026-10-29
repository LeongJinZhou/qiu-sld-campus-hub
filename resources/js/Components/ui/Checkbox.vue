<script setup lang="ts">
defineProps<{ label: string; disabled?: boolean }>();
const model = defineModel<boolean>({ default: false });
</script>

<template>
    <label class="inline-flex cursor-pointer items-start gap-2 text-sm has-disabled:cursor-not-allowed has-disabled:opacity-60">
        <input v-model="model" type="checkbox" :disabled="disabled" class="mt-0.5 size-4 rounded accent-ink" />
        <span>{{ label }}</span>
    </label>
</template>
