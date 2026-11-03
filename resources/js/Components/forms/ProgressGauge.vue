<script setup lang="ts">
import Icon from '@/Components/ui/Icon.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * How much of a form's compulsory parts is done: a ring that eases to the new value (and a count that follows it),
 * plus the list of parts, each tappable to jump there. The motion is one continuous ease, never a looping clip.
 */
const props = defineProps<{ items: { key: string; label: string; done: boolean }[]; title?: string }>();
const emit = defineEmits<{ jump: [key: string] }>();

const done = computed(() => props.items.filter((i) => i.done).length);
const percent = computed(() => (props.items.length ? Math.round((done.value / props.items.length) * 100) : 0));

// The number counts towards the target in step with the ring (same duration and easing).
const DURATION = 1200;
const shown = ref(0);
let frame = 0;
function animateTo(target: number): void {
    cancelAnimationFrame(frame);
    const from = shown.value;
    const start = performance.now();
    const ease = (t: number): number => 1 - Math.pow(1 - t, 4);
    const step = (now: number): void => {
        const t = Math.min(1, (now - start) / DURATION);
        shown.value = from + (target - from) * ease(t);
        if (t < 1) frame = requestAnimationFrame(step);
    };
    frame = requestAnimationFrame(step);
}
onMounted(() => animateTo(percent.value));
watch(percent, animateTo);
onBeforeUnmount(() => cancelAnimationFrame(frame));

const R = 42;
const CIRC = 2 * Math.PI * R;
const offset = computed(() => CIRC * (1 - shown.value / 100));
const complete = computed(() => percent.value === 100);
</script>

<template>
    <section class="grid gap-4 rounded-card border border-line bg-surface p-5 shadow-card sm:grid-cols-[auto_1fr] sm:items-center" aria-label="Compulsory parts done">
        <div class="relative mx-auto size-28 shrink-0" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100">
            <svg viewBox="0 0 100 100" class="size-full -rotate-90">
                <defs>
                    <linearGradient id="gauge-fill" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="var(--color-brand-orange)" />
                        <stop offset="55%" stop-color="var(--color-brand-magenta)" />
                        <stop offset="100%" stop-color="var(--color-brand-cyan)" />
                    </linearGradient>
                </defs>
                <circle cx="50" cy="50" :r="R" fill="none" stroke="var(--color-line)" stroke-width="9" />
                <circle
                    cx="50"
                    cy="50"
                    :r="R"
                    fill="none"
                    :stroke="complete ? 'var(--color-success)' : 'url(#gauge-fill)'"
                    stroke-width="9"
                    stroke-linecap="round"
                    :stroke-dasharray="CIRC"
                    :stroke-dashoffset="offset"
                />
            </svg>
            <div class="absolute inset-0 grid place-items-center text-center">
                <div>
                    <p class="text-2xl font-bold tabular-nums" :class="complete ? 'text-success' : ''">{{ Math.round(shown) }}%</p>
                    <p class="text-[11px] text-ink-muted">{{ done }}/{{ items.length }} parts</p>
                </div>
            </div>
        </div>
        <div class="grid gap-2">
            <p class="text-sm font-semibold">{{ complete ? 'Every compulsory part is done. Ready to sign and submit.' : (title ?? 'Compulsory parts') }}</p>
            <ul class="flex flex-wrap gap-1.5">
                <li v-for="item in items" :key="item.key">
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors duration-500"
                        :class="item.done ? 'border-success/40 bg-success/10 text-success' : 'border-line bg-surface-muted text-ink-muted hover:border-danger/50 hover:text-danger'"
                        @click="emit('jump', item.key)"
                    >
                        <Icon :name="item.done ? 'check' : 'clock'" :size="12" :stroke-width="3" />{{ item.label }}
                    </button>
                </li>
            </ul>
        </div>
    </section>
</template>
