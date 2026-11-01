<script setup lang="ts">
import Icon from '@/Components/ui/Icon.vue';
import type { SessionOption } from '@/types';
import { router } from '@inertiajs/vue3';
import { nextTick, onMounted, ref, watch } from 'vue';

/**
 * The committee year as a timeline: one card per session, newest first, the current one marked "Now".
 * Choosing a year slides the highlight across and loads that year's committee.
 */
const props = defineProps<{
    sessions: SessionOption[];
    selected: string;
    url: string;
}>();

const rail = ref<HTMLElement | null>(null);
const pill = ref({ left: 0, width: 0, ready: false });
const loading = ref<string | null>(null);

function measure(): void {
    const el = rail.value?.querySelector<HTMLElement>('[aria-current="true"]');
    if (!el) return;
    pill.value = { left: el.offsetLeft, width: el.offsetWidth, ready: true };
    el.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest',
        inline: 'center',
    });
}
onMounted(() => void nextTick(measure));
watch(
    () => props.selected,
    () => void nextTick(measure),
);

function choose(name: string): void {
    if (name === props.selected) return;
    loading.value = name;
    router.get(
        props.url,
        { session: name },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onFinish: () => (loading.value = null),
        },
    );
}
const short = (name: string): string => name.replace(/^(\d{4})\/\d{2}(\d{2})$/, '$1/$2');
const step = (dir: -1 | 1): void => {
    const i = props.sessions.findIndex((s) => s.name === props.selected);
    const next = props.sessions[i - dir];
    if (next) choose(next.name);
};
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center justify-between gap-2">
            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-ink-muted"><Icon name="calendar" :size="14" /> Committee year</p>
            <div v-if="sessions.length > 1" class="flex gap-1">
                <button
                    type="button"
                    class="grid size-7 place-items-center rounded-full border border-line text-ink-muted transition hover:bg-surface-muted disabled:opacity-30"
                    aria-label="Earlier year"
                    :disabled="sessions.at(-1)?.name === selected"
                    @click="step(-1)"
                >
                    ‹
                </button>
                <button
                    type="button"
                    class="grid size-7 place-items-center rounded-full border border-line text-ink-muted transition hover:bg-surface-muted disabled:opacity-30"
                    aria-label="Later year"
                    :disabled="sessions[0]?.name === selected"
                    @click="step(1)"
                >
                    ›
                </button>
            </div>
        </div>
        <div ref="rail" class="relative -mx-1 flex snap-x gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none]" role="tablist" aria-label="Committee year">
            <span
                class="absolute top-0 h-full rounded-card bg-gradient-to-br from-brand-orange via-brand-magenta to-brand-blue shadow-pop transition-all duration-500 ease-[cubic-bezier(0.2,0.8,0.2,1)]"
                :class="pill.ready ? 'opacity-100' : 'opacity-0'"
                :style="{ left: `${pill.left}px`, width: `${pill.width}px` }"
                aria-hidden="true"
            />
            <button
                v-for="s in sessions"
                :key="s.id"
                type="button"
                role="tab"
                :aria-selected="s.name === selected"
                :aria-current="s.name === selected"
                class="relative z-10 grid min-w-28 shrink-0 snap-center gap-0.5 rounded-card border px-4 py-2.5 text-left transition-colors duration-300"
                :class="s.name === selected ? 'border-transparent text-white' : 'border-line bg-surface hover:border-brand-cyan/60 hover:bg-surface-muted'"
                @click="choose(s.name)"
            >
                <span class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide" :class="s.name === selected ? 'text-white/80' : 'text-ink-muted'">
                    <span v-if="s.current" class="relative flex size-2">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-60" />
                        <span class="relative inline-flex size-2 rounded-full bg-success" />
                    </span>
                    {{ s.current ? 'Now' : 'Session' }}
                </span>
                <span class="text-lg font-black tabular-nums leading-tight">{{ short(s.name) }}</span>
                <span class="text-[11px]" :class="s.name === selected ? 'text-white/80' : 'text-ink-muted'">
                    <span v-if="loading === s.name" class="inline-block size-3 animate-spin rounded-full border-2 border-current border-t-transparent align-middle" />
                    <template v-else>{{ s.size ? `${s.size} on the committee` : 'No committee yet' }}</template>
                </span>
            </button>
        </div>
    </div>
</template>
