<script setup lang="ts">
import { useId } from 'vue';

withDefaults(
    defineProps<{
        label?: string;
        type?: string;
        error?: string;
        hint?: string;
        readonly?: boolean;
        required?: boolean;
        placeholder?: string;
    }>(),
    { type: 'text', readonly: false, required: false },
);
const model = defineModel<string | number | null>();
const id = useId();
</script>

<template>
    <div class="grid gap-1.5">
        <label v-if="label" :for="id" class="text-sm font-medium text-ink">
            {{ label }}<span v-if="required" class="text-danger"> *</span>
        </label>
        <input
            :id="id"
            v-model="model"
            :type="type"
            :readonly="readonly"
            :required="required"
            :placeholder="placeholder"
            :aria-invalid="!!error"
            :aria-describedby="error || hint ? `${id}-msg` : undefined"
            class="h-10 rounded-control border border-line bg-surface px-3 text-sm outline-none transition focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/30 read-only:bg-surface-muted read-only:text-ink-muted aria-invalid:border-danger"
        />
        <p v-if="error || hint" :id="`${id}-msg`" class="text-xs" :class="error ? 'text-danger' : 'text-ink-muted'">
            {{ error ?? hint }}
        </p>
    </div>
</template>
