<script setup lang="ts">
// Tick-box groups from the template (nature of event, SDGs, source of fund).
defineProps<{ options: Record<string | number, string>; disabled?: boolean; columns?: 1 | 2 | 3; numbered?: boolean }>();
const model = defineModel<(string | number)[]>({ required: true });
</script>

<template>
    <div class="grid gap-2" :class="{ 'sm:grid-cols-2': columns === 2, 'sm:grid-cols-3': columns === 3 }">
        <label v-for="(label, value) in options" :key="value" class="inline-flex items-start gap-2 text-sm">
            <input
                v-model="model"
                type="checkbox"
                :value="Number.isNaN(Number(value)) ? value : Number(value)"
                :disabled="disabled"
                class="mt-0.5 size-4 accent-ink"
            />
            <span>{{ numbered ? `${value}. ` : '' }}{{ label }}</span>
        </label>
    </div>
</template>
