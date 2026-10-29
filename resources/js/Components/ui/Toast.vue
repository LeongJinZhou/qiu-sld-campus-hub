<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const page = usePage();
const message = ref<{ text: string; tone: 'success' | 'error' } | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => page.props.flash,
    (flash) => {
        const text = flash.error ?? flash.success;
        if (!text) {
            return;
        }
        message.value = { text, tone: flash.error ? 'error' : 'success' };
        clearTimeout(timer);
        timer = setTimeout(() => (message.value = null), 5000);
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-50 flex justify-center px-4" aria-live="polite">
        <p
            v-if="message"
            class="pointer-events-auto rounded-control px-4 py-3 text-sm font-medium text-white shadow-pop"
            :class="message.tone === 'error' ? 'bg-danger' : 'bg-ink'"
            role="status"
        >
            {{ message.text }}
        </p>
    </div>
</template>
