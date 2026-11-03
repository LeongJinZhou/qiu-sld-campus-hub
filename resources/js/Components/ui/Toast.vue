<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * The message from the action this person just took (flash data lives in their own session, so nobody else sees it).
 * Shown once per real page visit: the background badge refresh (a partial reload every 20 s) must never replay it.
 */
const page = usePage();
const message = ref<{ text: string; tone: 'success' | 'error' } | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

function show(): void {
    const flash = page.props.flash;
    const text = flash.error ?? flash.success;
    if (!text) return;
    message.value = { text, tone: flash.error ? 'error' : 'success' };
    clearTimeout(timer);
    timer = setTimeout(() => (message.value = null), 4000);
}
const dismiss = (): void => {
    clearTimeout(timer);
    message.value = null;
};

onMounted(show);
const off = router.on('finish', (e) => {
    const visit = e.detail.visit;
    if (visit.completed && !visit.only.length && !visit.except.length) show();
});
onUnmounted(() => {
    off();
    clearTimeout(timer);
});
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-50 flex justify-center px-4" aria-live="polite">
        <Transition name="fade-up">
            <button
                v-if="message"
                type="button"
                class="pointer-events-auto rounded-control px-4 py-3 text-left text-sm font-medium text-white shadow-pop"
                :class="message.tone === 'error' ? 'bg-danger' : 'bg-ink'"
                role="status"
                title="Dismiss"
                @click="dismiss"
            >
                {{ message.text }}
            </button>
        </Transition>
    </div>
</template>
