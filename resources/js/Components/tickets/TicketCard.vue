<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import { rm } from '@/lib/format';
import type { Ticket } from '@/types';
import { computed } from 'vue';

/**
 * An event ticket that moves with its event: confetti for a party, drifting leaves outdoors, a glowing grid for
 * online events, and a holographic shine sweeping across. Full size shows the QR stub; compact is a list row.
 * Motion stops for people who ask their device for reduced motion.
 */
const props = withDefaults(defineProps<{ ticket: Ticket; qrSvg?: string | null; compact?: boolean }>(), { qrSvg: null, compact: false });

const themes = {
    party: { bg: 'from-brand-orange via-brand-magenta to-brand-blue', label: 'Party' },
    nature: { bg: 'from-emerald-500 via-teal-500 to-brand-cyan', label: 'Outdoor' },
    online: { bg: 'from-indigo-700 via-brand-blue to-brand-cyan', label: 'Online' },
} as const;
const theme = computed(() => themes[props.ticket.theme] ?? themes.party);

const when = computed(() => {
    const start = new Date(props.ticket.event.starts_at);
    const end = new Date(props.ticket.event.ends_at);
    const opts = { timeZone: 'Asia/Kuala_Lumpur' } as const;
    return {
        day: start.toLocaleString('en-MY', { ...opts, day: 'numeric' }),
        month: start.toLocaleString('en-MY', { ...opts, month: 'short' }).toUpperCase(),
        weekday: start.toLocaleString('en-MY', { ...opts, weekday: 'long' }),
        time: `${start.toLocaleString('en-MY', { ...opts, hour: 'numeric', minute: '2-digit' })} – ${end.toLocaleString('en-MY', { ...opts, hour: 'numeric', minute: '2-digit' })}`,
    };
});

const status = computed(() => {
    const t = props.ticket;
    return {
        paid: { text: `Paid ${rm(t.amount_sen)}`, tone: 'bg-success text-white' },
        free: { text: 'Free entry', tone: 'bg-success text-white' },
        checking: { text: 'Payment being checked', tone: 'bg-brand-cyan text-white' },
        unpaid: { text: 'Not paid yet', tone: 'bg-warning text-white' },
    }[t.status];
});
const valid = computed(() => props.ticket.status === 'paid' || props.ticket.status === 'free');

// Fixed pseudo-random particles, so the layout is the same on every render.
const particles = Array.from({ length: 14 }, (_, i) => ({
    left: `${(i * 37) % 100}%`,
    delay: `${(i * 0.73) % 6}s`,
    duration: `${6 + ((i * 1.9) % 5)}s`,
    size: 6 + ((i * 5) % 9),
    hue: ['#FDDA1A', '#1CBDFD', '#E01CEB', '#FD8F1A', '#ffffff'][i % 5],
    rotate: `${(i * 47) % 360}deg`,
}));
</script>

<template>
    <!-- Compact: one row in a list. -->
    <div v-if="compact" class="ticket relative flex overflow-hidden rounded-2xl text-white shadow-card" :class="['bg-gradient-to-r', theme.bg]">
        <span class="shine" aria-hidden="true" />
        <div class="relative grid w-16 shrink-0 place-items-center bg-black/15 py-3 text-center leading-none">
            <span class="text-[10px] font-semibold tracking-wider">{{ when.month }}</span>
            <span class="text-2xl font-bold">{{ when.day }}</span>
        </div>
        <div class="relative min-w-0 flex-1 px-3 py-3">
            <p class="truncate font-semibold">{{ ticket.event.name }}</p>
            <p class="truncate text-xs text-white/80">{{ when.time }}<template v-if="ticket.event.venue"> · {{ ticket.event.venue }}</template></p>
            <p class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
                <span class="rounded-full px-2 py-0.5 font-semibold" :class="status.tone">{{ status.text }}</span>
                <span v-if="ticket.clock_in" class="rounded-full bg-white/20 px-2 py-0.5">In {{ ticket.clock_in }}</span>
            </p>
        </div>
        <div class="notch-divider relative grid w-14 shrink-0 place-items-center" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="size-7 opacity-90" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h3v3h-3zm4 4h3v3h-3zm-4 3h1m6-7h1" /></svg>
        </div>
    </div>

    <!-- Full ticket. -->
    <article v-else class="ticket relative mx-auto w-full max-w-sm overflow-hidden rounded-[28px] text-white shadow-pop" :class="['bg-gradient-to-br', theme.bg, `theme-${ticket.theme}`]">
        <div class="aurora" aria-hidden="true" />
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <span
                v-for="(p, i) in particles"
                :key="i"
                class="particle"
                :style="{ left: p.left, animationDelay: p.delay, animationDuration: p.duration, width: `${p.size}px`, height: `${ticket.theme === 'party' ? p.size * 0.5 : p.size}px`, background: ticket.theme === 'party' ? p.hue : undefined, '--r': p.rotate }"
            />
        </div>
        <span class="shine" aria-hidden="true" />

        <header class="relative">
            <div v-if="ticket.event.poster_url" class="relative m-3 mb-0 h-32 overflow-hidden rounded-[20px] ring-1 ring-white/30">
                <img :src="ticket.event.poster_url" alt="" class="poster size-full object-cover object-top" />
            </div>
            <div class="relative px-6 pb-4 pt-4">
                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-white/90">
                    <img v-if="ticket.event.club_logo" :src="ticket.event.club_logo" alt="" class="size-6 rounded-full bg-white object-cover" />
                    {{ ticket.event.club }}
                </p>
                <h1 class="mt-2 text-2xl font-extrabold leading-tight drop-shadow">{{ ticket.event.name }}</h1>
            </div>
        </header>

        <dl class="relative grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-2 px-6 pb-5 text-sm">
            <div class="row-span-2 grid w-16 place-items-center rounded-2xl bg-white/15 py-2 text-center leading-none backdrop-blur">
                <span class="text-[11px] font-semibold tracking-wider">{{ when.month }}</span>
                <span class="text-3xl font-black">{{ when.day }}</span>
            </div>
            <div><dt class="text-[11px] uppercase tracking-wider text-white/70">{{ when.weekday }}</dt><dd class="font-semibold">{{ when.time }}</dd></div>
            <div><dt class="text-[11px] uppercase tracking-wider text-white/70">Venue</dt><dd class="font-semibold">{{ ticket.event.venue ?? 'To be announced' }}</dd></div>
        </dl>

        <!-- Tear line -->
        <div class="relative flex items-center" aria-hidden="true">
            <span class="-ml-3 size-6 rounded-full bg-surface-muted" />
            <span class="flex-1 border-t-2 border-dashed border-white/50" />
            <span class="-mr-3 size-6 rounded-full bg-surface-muted" />
        </div>

        <!-- Stub -->
        <section class="relative m-4 grid gap-4 rounded-3xl bg-surface p-5 text-ink">
            <div class="flex items-center gap-3">
                <Avatar :src="ticket.attendee.photo_url" :name="ticket.attendee.name" size="sm" />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ ticket.attendee.name }}</p>
                    <p class="text-xs text-ink-muted">{{ ticket.attendee.student_number }}</p>
                </div>
                <span class="whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="status.tone">{{ status.text }}</span>
            </div>

            <div v-if="qrSvg && valid" class="qr-frame relative mx-auto grid place-items-center rounded-2xl p-3">
                <div class="size-48 [&_svg]:size-full" v-html="qrSvg" />
            </div>
            <p v-else-if="!valid" class="rounded-control bg-warning/10 px-3 py-2 text-center text-sm text-warning">Your QR ticket appears once payment is confirmed.</p>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div><p class="text-ink-muted">Ticket / transaction ID</p><p class="break-all font-mono font-semibold">{{ ticket.order_id ?? '—' }}</p></div>
                <div class="text-right">
                    <p class="text-ink-muted">Check-in</p>
                    <p class="font-semibold">
                        <template v-if="ticket.clock_in">In {{ ticket.clock_in }}<template v-if="ticket.clock_out"> · Out {{ ticket.clock_out }}</template></template>
                        <template v-else>{{ ticket.event.past ? 'Not attended' : 'Not yet' }}</template>
                    </p>
                </div>
            </div>
            <p v-if="valid && !ticket.event.past" class="text-center text-[11px] text-ink-muted">Show this QR at the door. Screenshots work, but the committee checks the name.</p>
        </section>
    </article>
</template>

<style scoped>
.ticket {
    background-size: 200% 200%;
    animation: drift 12s ease-in-out infinite;
}
.shine {
    position: absolute;
    inset: -50%;
    background: linear-gradient(115deg, transparent 40%, rgb(255 255 255 / 0.35) 50%, transparent 60%);
    animation: shine 5s ease-in-out infinite;
    pointer-events: none;
    z-index: 1;
}
.aurora {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 20% 15%, rgb(255 255 255 / 0.25), transparent 40%),
        radial-gradient(circle at 85% 70%, rgb(255 255 255 / 0.18), transparent 45%);
    animation: pulse 6s ease-in-out infinite alternate;
}
.poster {
    animation: zoom 18s ease-in-out infinite alternate;
}
.qr-frame {
    background: linear-gradient(white, white) padding-box, conic-gradient(from var(--angle, 0deg), #fdda1a, #1cbdfd, #e01ceb, #fd8f1a, #fdda1a) border-box;
    border: 3px solid transparent;
    animation: spin-border 4s linear infinite;
}
.particle {
    position: absolute;
    top: -20px;
    border-radius: 2px;
    opacity: 0.85;
    animation-name: fall;
    animation-timing-function: linear;
    animation-iteration-count: infinite;
}
.theme-nature .particle {
    border-radius: 0 70% 0 70%;
    background: rgb(220 252 231 / 0.8);
    animation-name: leaf;
}
.theme-online .particle {
    border-radius: 9999px;
    background: rgb(165 243 252 / 0.9);
    box-shadow: 0 0 10px rgb(165 243 252);
    animation-name: rise;
    top: auto;
    bottom: -20px;
}
.theme-online::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image: linear-gradient(rgb(255 255 255 / 0.08) 1px, transparent 1px), linear-gradient(90deg, rgb(255 255 255 / 0.08) 1px, transparent 1px);
    background-size: 22px 22px;
    animation: grid 8s linear infinite;
    pointer-events: none;
}
.notch-divider {
    border-left: 2px dashed rgb(255 255 255 / 0.5);
}

@property --angle {
    syntax: '<angle>';
    initial-value: 0deg;
    inherits: false;
}
@keyframes drift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}
@keyframes shine {
    0% { transform: translateX(-60%); }
    60%, 100% { transform: translateX(60%); }
}
@keyframes pulse {
    to { opacity: 0.55; transform: scale(1.08); }
}
@keyframes zoom {
    to { transform: scale(1.12); }
}
@keyframes spin-border {
    to { --angle: 360deg; }
}
@keyframes fall {
    0% { transform: translateY(0) rotate(0deg); }
    100% { transform: translateY(720px) rotate(var(--r)); }
}
@keyframes leaf {
    0% { transform: translate(0, 0) rotate(0deg); }
    50% { transform: translate(30px, 360px) rotate(180deg); }
    100% { transform: translate(-20px, 720px) rotate(var(--r)); }
}
@keyframes rise {
    0% { transform: translateY(0); opacity: 0; }
    20% { opacity: 0.9; }
    100% { transform: translateY(-720px); opacity: 0; }
}
@keyframes grid {
    to { background-position: 22px 22px; }
}
@media (prefers-reduced-motion: reduce) {
    .ticket, .shine, .aurora, .poster, .qr-frame, .particle, .theme-online::after {
        animation: none !important;
    }
    .particle { display: none; }
}
</style>
