<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
        size?: 'sm' | 'md' | 'lg';
        type?: 'button' | 'submit' | 'reset';
        loading?: boolean;
        disabled?: boolean;
        block?: boolean;
    }>(),
    { variant: 'primary', size: 'md', type: 'button', loading: false, disabled: false, block: false },
);

const variants = {
    primary: 'bg-ink text-white hover:bg-ink/90',
    secondary: 'bg-surface text-ink border border-line hover:bg-surface-muted',
    ghost: 'text-ink hover:bg-surface-muted',
    danger: 'bg-danger text-white hover:bg-danger/90',
};
const sizes = { sm: 'h-8 px-3 text-sm', md: 'h-10 px-4 text-sm', lg: 'h-12 px-6 text-base' };

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-control font-medium transition',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cyan',
    'disabled:cursor-not-allowed disabled:opacity-50',
    variants[props.variant],
    sizes[props.size],
    props.block ? 'w-full' : '',
]);
</script>

<template>
    <button :type="type" :class="classes" :disabled="disabled || loading" :aria-busy="loading">
        <span v-if="loading" class="size-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
        <slot />
    </button>
</template>
