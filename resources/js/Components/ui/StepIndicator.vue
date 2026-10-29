<script setup lang="ts">
defineProps<{ steps: { label: string; state: 'done' | 'current' | 'returned' | 'pending' }[] }>();

const dots = {
    done: 'bg-success text-white',
    current: 'bg-ink text-white',
    returned: 'bg-warning text-white',
    pending: 'bg-surface-muted text-ink-muted',
};
</script>

<template>
    <ol class="flex flex-wrap gap-x-2 gap-y-3">
        <li v-for="(step, index) in steps" :key="step.label" class="flex items-center gap-2 text-sm">
            <span class="grid size-7 place-items-center rounded-full text-xs font-semibold" :class="dots[step.state]" :aria-current="step.state === 'current' ? 'step' : undefined">
                {{ step.state === 'done' ? '✓' : index + 1 }}
            </span>
            <span :class="step.state === 'pending' ? 'text-ink-muted' : ''">{{ step.label }}</span>
            <span v-if="index < steps.length - 1" class="h-px w-6 bg-line" aria-hidden="true" />
        </li>
    </ol>
</template>
