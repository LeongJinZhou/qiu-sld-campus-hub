<script setup lang="ts">
import { useId } from 'vue';

withDefaults(defineProps<{ label?: string; error?: string; rows?: number; readonly?: boolean; placeholder?: string }>(), {
    rows: 4,
    readonly: false,
});
const model = defineModel<string | null>();
const id = useId();
</script>

<template>
    <div class="grid gap-1.5">
        <label v-if="label" :for="id" class="text-sm font-medium text-ink">{{ label }}</label>
        <textarea
            :id="id"
            v-model="model"
            :rows="rows"
            :readonly="readonly"
            :placeholder="placeholder"
            :aria-invalid="!!error"
            class="rounded-control border border-line bg-surface px-3 py-2 text-sm outline-none focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/30 read-only:bg-surface-muted"
        />
        <p v-if="error" class="text-xs text-danger">{{ error }}</p>
    </div>
</template>
