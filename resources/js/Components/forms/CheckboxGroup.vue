<script setup lang="ts">
// Tick-box groups from the template (nature of event, SDGs, source of fund). Each option is a tappable card:
// box, number and wrapped text line up in fixed columns, so long labels never push the next one out of place.
defineProps<{ options: Record<string | number, string>; disabled?: boolean; columns?: 1 | 2 | 3; numbered?: boolean }>();
const model = defineModel<(string | number)[]>({ required: true });
const valueOf = (value: string | number): string | number => (Number.isNaN(Number(value)) ? value : Number(value));
</script>

<template>
    <div class="grid gap-2" :class="{ 'sm:grid-cols-2': columns === 2, 'sm:grid-cols-2 lg:grid-cols-3': columns === 3 }">
        <label
            v-for="(label, value) in options"
            :key="value"
            class="grid min-h-11 cursor-pointer items-center gap-2 rounded-control border px-3 py-2 text-sm leading-snug transition-colors has-[:disabled]:cursor-default"
            :class="[
                numbered ? 'grid-cols-[1rem_1.5rem_1fr]' : 'grid-cols-[1rem_1fr]',
                model.includes(valueOf(value)) ? 'border-brand-cyan bg-brand-cyan/5' : 'border-line hover:bg-surface-muted',
            ]"
        >
            <input v-model="model" type="checkbox" :value="valueOf(value)" :disabled="disabled" class="size-4 accent-ink" />
            <span v-if="numbered" class="text-right text-xs font-semibold tabular-nums text-ink-muted">{{ value }}.</span>
            <span class="min-w-0 break-words">{{ label }}</span>
        </label>
    </div>
</template>
