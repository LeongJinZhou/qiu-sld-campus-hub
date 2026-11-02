<script setup lang="ts">
import { ref, watch } from 'vue';

/** "Approved!" — a check that draws itself and a burst of confetti, shown once for a moment worth marking. */
const props = defineProps<{ text?: string | null; detail?: string | null }>();
const open = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
const colours = ['#FDDA1A', '#1CBDFD', '#E01CEB', '#FD8F1A', '#22c55e'];
const pieces = Array.from({ length: 36 }, (_, i) => ({
    left: `${(i * 37) % 100}%`,
    delay: `${(i % 9) * 0.06}s`,
    duration: `${1.6 + (i % 5) * 0.25}s`,
    colour: colours[i % colours.length],
    rotate: `${(i * 47) % 360}deg`,
}));

watch(
    () => props.text,
    (text) => {
        if (!text) return;
        open.value = true;
        clearTimeout(timer);
        timer = setTimeout(() => (open.value = false), 4200);
    },
    { immediate: true },
);
</script>

<template>
    <Transition name="fade-up">
        <div v-if="open" class="fixed inset-0 z-[60] grid place-items-center bg-ink/40 p-6 backdrop-blur-sm" role="status" aria-live="polite" @click="open = false">
            <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                <span
                    v-for="(p, i) in pieces"
                    :key="i"
                    class="confetti"
                    :style="{ left: p.left, animationDelay: p.delay, animationDuration: p.duration, background: p.colour, '--r': p.rotate }"
                />
            </div>
            <div class="pop relative grid justify-items-center gap-3 rounded-card bg-surface px-10 py-8 text-center shadow-pop">
                <svg viewBox="0 0 52 52" class="size-20" aria-hidden="true">
                    <circle cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="3" class="ring text-success" />
                    <path d="M15 27l7 7 15-15" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" class="tick text-success" />
                </svg>
                <p class="text-xl font-bold">{{ text }}</p>
                <p class="text-sm text-ink-muted">{{ detail ?? 'Every signature is in. Well done, everyone.' }}</p>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.ring {
    stroke-dasharray: 151;
    stroke-dashoffset: 151;
    animation: draw 0.6s ease-out forwards;
}
.tick {
    stroke-dasharray: 36;
    stroke-dashoffset: 36;
    animation: draw 0.4s 0.5s ease-out forwards;
}
.pop {
    animation: pop 0.45s cubic-bezier(0.2, 1.4, 0.4, 1) both;
}
.confetti {
    position: absolute;
    top: -16px;
    width: 9px;
    height: 14px;
    border-radius: 2px;
    animation-name: fall;
    animation-timing-function: ease-in;
    animation-fill-mode: forwards;
}
@keyframes draw {
    to {
        stroke-dashoffset: 0;
    }
}
@keyframes pop {
    from {
        transform: scale(0.6);
        opacity: 0;
    }
}
@keyframes fall {
    to {
        transform: translateY(105vh) rotate(var(--r));
    }
}
@media (prefers-reduced-motion: reduce) {
    .ring,
    .tick {
        animation: none;
        stroke-dashoffset: 0;
    }
    .pop,
    .confetti {
        animation: none;
    }
    .confetti {
        display: none;
    }
}
</style>
