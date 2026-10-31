<script setup lang="ts">
defineProps<{ steps: { label: string; state: 'done' | 'current' | 'returned' | 'pending' }[] }>();

const dots = {
    done: 'bg-success text-white',
    current: 'bg-ink text-white ring-4 ring-ink/10',
    returned: 'bg-warning text-white',
    pending: 'bg-surface-muted text-ink-muted',
};
</script>

<template>
    <!-- One evenly spaced row on any screen: circles joined by a line, labels underneath. -->
    <ol class="grid" :style="{ gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))` }">
        <li v-for="(step, index) in steps" :key="step.label" class="relative grid justify-items-center gap-1.5 text-center">
            <span v-if="index < steps.length - 1" class="absolute left-1/2 top-3.5 h-0.5 w-full" :class="step.state === 'done' ? 'bg-success' : 'bg-line'" aria-hidden="true" />
            <span class="relative grid size-7 place-items-center rounded-full text-xs font-semibold" :class="dots[step.state]" :aria-current="step.state === 'current' ? 'step' : undefined">
                {{ step.state === 'done' ? '✓' : index + 1 }}
            </span>
            <span class="text-xs" :class="step.state === 'pending' ? 'text-ink-muted' : 'font-medium'">{{ step.label }}</span>
        </li>
    </ol>
</template>
