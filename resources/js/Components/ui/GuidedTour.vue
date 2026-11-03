<script setup lang="ts">
import { TOURS, type TourName, type TourStep } from '@/lib/tours';
import { router, usePage } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

/**
 * First-time guides: the page dims, the real button is cut out of the dimmed layer and circled, and a card explains
 * it. Runs once per account (stored on the server), again from Profile → Settings, or via ?tour=student|committee.
 */
const page = usePage();
const user = computed(() => page.props.auth.user);

const tour = ref<TourName | null>(null);
const steps = ref<TourStep[]>([]);
const index = ref(0);
const rect = ref<DOMRect | null>(null);
const step = computed(() => steps.value[index.value] ?? null);

/** The visible element for a step (desktop and phone navigation both carry the same mark). */
function find(target: string): HTMLElement | null {
    return [...document.querySelectorAll<HTMLElement>(`[data-tour="${target}"]`)].find((el) => el.offsetParent !== null || getComputedStyle(el).position === 'fixed') ?? null;
}

async function start(name: TourName): Promise<void> {
    await nextTick();
    const available = TOURS[name].steps.filter((s) => find(s.target));
    if (!available.length) return;
    tour.value = name;
    steps.value = available;
    index.value = 0;
    // Shown once: recorded as seen as soon as it opens, so leaving mid-way or signing out never brings it back
    // at the next sign-in. Profile → "Show me around again" replays it on request.
    markSeen(name);
    await show();
}
function markSeen(name: TourName): void {
    if (user.value?.tours_seen?.includes(name)) return;
    router.post('/me/tours', { tour: name, seen: true }, { preserveScroll: true, preserveState: true, only: ['auth'] });
}

async function show(): Promise<void> {
    const el = step.value ? find(step.value.target) : null;
    if (!el) return next();
    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    await new Promise((r) => setTimeout(r, 350));
    measure();
}
const measure = (): void => {
    const el = step.value ? find(step.value.target) : null;
    rect.value = el ? el.getBoundingClientRect() : null;
};
useEventListener(window, 'resize', measure);
useEventListener(window, 'scroll', measure, { passive: true, capture: true });

const next = async (): Promise<void> => {
    if (index.value < steps.value.length - 1) {
        index.value++;
        await show();
    } else finish();
};
const back = async (): Promise<void> => {
    if (index.value > 0) {
        index.value--;
        await show();
    }
};
function finish(): void {
    const name = tour.value;
    tour.value = null;
    rect.value = null;
    if (name) markSeen(name);
}
useEventListener(window, 'keydown', (e: KeyboardEvent) => {
    if (!tour.value) return;
    if (e.key === 'Escape') finish();
    if (e.key === 'ArrowRight' || e.key === 'Enter') void next();
    if (e.key === 'ArrowLeft') void back();
});

// When to start: first time (not seen yet, on the right page, no other dialog open), or when asked via ?tour=.
function maybeStart(): void {
    if (!user.value || tour.value) return;
    const seen = user.value.tours_seen ?? [];
    const asked = new URLSearchParams(window.location.search).get('tour') as TourName | null;
    const club = user.value.committee_club;
    const onMyClub = club && page.url.startsWith(`/clubs/${club.slug}`) && page.component === 'Clubs/Show';
    const wanted: TourName | null =
        asked && asked in TOURS ? asked : onMyClub && !seen.includes('committee') ? 'committee' : page.component === 'Feed/Index' && !seen.includes('student') ? 'student' : null;
    if (!wanted) return;
    const tryStart = (attempt = 0): void => {
        // Let pop-ups (e.g. the committee fee reminder) go first.
        if (document.querySelector('dialog[open]') && attempt < 20) return void setTimeout(() => tryStart(attempt + 1), 1000);
        void start(wanted);
    };
    setTimeout(tryStart, 900);
}
onMounted(maybeStart);
watch(() => page.url, maybeStart);

// Spotlight geometry: padded hole around the element, circle drawn just outside it, card placed above or below.
const PAD = 8;
const hole = computed(() => (rect.value ? { x: rect.value.left - PAD, y: rect.value.top - PAD, w: rect.value.width + PAD * 2, h: rect.value.height + PAD * 2 } : null));
const card = computed(() => {
    if (!hole.value) return { top: '50%', left: '50%', transform: 'translate(-50%, -50%)' };
    const below = hole.value.y + hole.value.h + 16;
    const fitsBelow = below + 190 < window.innerHeight;
    const width = Math.min(340, window.innerWidth - 32);
    const left = Math.max(16, Math.min(hole.value.x + hole.value.w / 2 - width / 2, window.innerWidth - width - 16));
    return { top: `${fitsBelow ? below : Math.max(16, hole.value.y - 16 - 190)}px`, left: `${left}px`, width: `${width}px` };
});
</script>

<template>
    <Teleport to="body">
        <div v-if="tour && step" class="fixed inset-0 z-[90]" role="dialog" aria-modal="true" :aria-label="TOURS[tour].title">
            <!-- Dim everything except the highlighted element (clicks on the dim layer are blocked). -->
            <div
                v-if="hole"
                class="pointer-events-none absolute rounded-2xl transition-all duration-300 ease-out"
                :style="{ left: `${hole.x}px`, top: `${hole.y}px`, width: `${hole.w}px`, height: `${hole.h}px`, boxShadow: '0 0 0 9999px rgb(10 10 14 / 0.62)' }"
            />
            <div v-else class="absolute inset-0 bg-[rgb(10_10_14/0.62)]" />
            <div class="absolute inset-0" @click.self="next" />
            <!-- The circle: a pulsing ring around the highlighted element. -->
            <span
                v-if="hole"
                class="tour-ring pointer-events-none absolute rounded-2xl border-[3px] border-brand-yellow transition-all duration-300 ease-out"
                :style="{ left: `${hole.x - 4}px`, top: `${hole.y - 4}px`, width: `${hole.w + 8}px`, height: `${hole.h + 8}px` }"
            />
            <section class="absolute grid gap-3 rounded-card bg-surface p-4 text-ink shadow-pop transition-all duration-300" :style="card">
                <div class="flex items-center justify-between gap-2 text-xs text-ink-muted">
                    <span class="font-semibold uppercase tracking-wide">{{ TOURS[tour].title }}</span>
                    <span class="tabular-nums">{{ index + 1 }} / {{ steps.length }}</span>
                </div>
                <div class="grid gap-1">
                    <h2 class="text-base font-bold">{{ step.title }}</h2>
                    <p class="text-sm text-ink-muted">{{ step.body }}</p>
                </div>
                <div class="h-1 overflow-hidden rounded-full bg-surface-muted">
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-brand-orange to-brand-magenta transition-[width] duration-300"
                        :style="{ width: `${((index + 1) / steps.length) * 100}%` }"
                    />
                </div>
                <div class="flex items-center justify-between gap-2">
                    <button type="button" class="text-sm text-ink-muted hover:text-ink" @click="finish">Skip guide</button>
                    <div class="flex gap-2">
                        <button v-if="index > 0" type="button" class="h-9 rounded-control border border-line px-3 text-sm font-medium" @click="back">Back</button>
                        <button type="button" class="h-9 rounded-control bg-gradient-to-r from-brand-orange to-brand-magenta px-4 text-sm font-semibold text-white" @click="next">
                            {{ index === steps.length - 1 ? 'Got it' : 'Next' }}
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </Teleport>
</template>

<style scoped>
.tour-ring {
    animation: tour-pulse 1.6s ease-out infinite;
}
@keyframes tour-pulse {
    0% {
        box-shadow: 0 0 0 0 rgb(253 218 26 / 0.7);
    }
    80%,
    100% {
        box-shadow: 0 0 0 14px rgb(253 218 26 / 0);
    }
}
@media (prefers-reduced-motion: reduce) {
    .tour-ring {
        animation: none;
    }
}
</style>
