<script setup lang="ts">
import { useId } from 'vue';

defineProps<{
    label?: string;
    options: { value: string | number; label: string }[];
    error?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
}>();
const model = defineModel<string | number | null>();
const id = useId();
</script>

<template>
    <div class="grid gap-1.5">
        <label v-if="label" :for="id" class="text-sm font-medium text-ink">{{ label }}</label>
        <select
            :id="id"
            v-model="model"
            :required="required"
            :disabled="disabled"
            :aria-invalid="!!error"
            class="h-10 rounded-control border border-line bg-surface px-3 text-sm outline-none focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/30 disabled:bg-surface-muted"
        >
            <option v-if="placeholder" :value="null" disabled>{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <p v-if="error" class="text-xs text-danger">{{ error }}</p>
    </div>
</template>
