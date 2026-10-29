<script setup lang="ts">
// Renders an SVG QR generated server-side by simple-qrcode (never user-supplied markup).
defineProps<{ svg: string; label?: string; downloadName?: string }>();
</script>

<template>
    <figure class="grid justify-items-center gap-2">
        <div class="size-48 rounded-card bg-white p-3 [&>svg]:size-full" role="img" :aria-label="label ?? 'QR code'" v-html="svg" />
        <figcaption v-if="label" class="text-sm text-ink-muted">{{ label }}</figcaption>
        <a v-if="downloadName" :href="`data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`" :download="`${downloadName}.svg`" class="text-sm font-medium text-brand-blue">
            Download QR
        </a>
    </figure>
</template>
