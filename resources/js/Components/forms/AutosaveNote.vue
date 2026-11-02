<script setup lang="ts">
import type { AutosaveState } from '@/composables/useAutosave';

defineProps<{ state: AutosaveState }>();
</script>

<template>
    <span class="text-xs" :class="state === 'error' ? 'text-danger' : 'text-ink-muted'" aria-live="polite">
        {{ { idle: 'Changes save automatically', saving: 'Saving…', saved: 'All changes saved', error: "Couldn't save yet — check the highlighted fields" }[state] }}
    </span>
</template>
