<script setup lang="ts">
// A value the system already knows: shown, never editable.
defineProps<{ label: string; value: string | number | null | undefined }>();
</script>

<template>
    <div class="grid gap-1">
        <span class="text-sm font-medium text-ink">{{ label }}</span>
        <span class="flex min-h-10 items-center gap-2 rounded-control border border-line bg-surface-muted px-3 text-sm text-ink-muted">
            <span aria-hidden="true">🔒</span>{{ value || '—' }}
        </span>
    </div>
</template>
