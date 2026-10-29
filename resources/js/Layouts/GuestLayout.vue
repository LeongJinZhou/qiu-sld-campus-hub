<script setup lang="ts">
import Toast from '@/Components/ui/Toast.vue';
</script>

<template>
    <div class="relative grid min-h-dvh place-items-center overflow-hidden px-4 py-10">
        <div class="pointer-events-none absolute -left-24 -top-24 size-72 rounded-full bg-brand-yellow/40 blur-3xl" aria-hidden="true" />
        <div class="pointer-events-none absolute -bottom-24 -right-24 size-72 rounded-full bg-brand-magenta/25 blur-3xl" aria-hidden="true" />
        <Toast />
        <main class="relative w-full max-w-sm">
            <slot />
        </main>
    </div>
</template>
