<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import Modal from '@/Components/ui/Modal.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { rm } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { useSwipe } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

interface CalEvent {
    slug: string;
    name: string;
    club: string;
    club_slug: string;
    starts_at: string;
    ends_at: string;
    venue: string | null;
    poster_url: string | null;
    fee_sen: number;
    registration_open: boolean;
    registered: boolean;
    followed: boolean;
    google_url: string;
}

// Meetings, weekly activities, weekly-report due dates and AGMs for the clubs this person belongs to.
interface CalItem {
    key: string;
    kind: 'meeting' | 'activity' | 'report_due' | 'agm';
    title: string;
    club: string;
    starts_at: string;
    ends_at: string;
    url: string;
}

const props = defineProps<{ month: string; today: string; events: CalEvent[]; years: number[]; feedUrl: string; items?: CalItem[]; view?: 'month' | 'year' }>();
const ITEM_ICON: Record<CalItem['kind'], 'users' | 'calendar' | 'file' | 'award'> = { meeting: 'users', activity: 'calendar', report_due: 'file', agm: 'award' };

const TZ = 'Asia/Kuala_Lumpur';
const dayKey = (iso: string): string => new Date(iso).toLocaleDateString('en-CA', { timeZone: TZ });
// The end is exclusive: an event ending at 00:00 does not appear on the next day.
const lastDayKey = (startIso: string, endIso: string): string => {
    const end = new Date(endIso).getTime();
    return dayKey(new Date(Math.max(new Date(startIso).getTime(), end - 1)).toISOString());
};
const time = (iso: string): string => new Date(iso).toLocaleTimeString('en-MY', { hour: 'numeric', minute: '2-digit', timeZone: TZ });
const ym = computed(() => props.month.split('-').map(Number) as [number, number]);
const title = computed(() => new Date(ym.value[0], ym.value[1] - 1, 1).toLocaleDateString('en-MY', { month: 'long', year: 'numeric' }));

// Which events: everything, clubs I follow, or events I registered for.
const filter = ref<'all' | 'followed' | 'mine'>('all');
const shown = computed(() => props.events.filter((e) => filter.value === 'all' || (filter.value === 'mine' ? e.registered : e.followed)));

// Monday-first grid covering the whole month.
const days = computed(() => {
    const [year, mon] = ym.value;
    const first = new Date(year, mon - 1, 1);
    const start = new Date(first);
    start.setDate(1 - ((first.getDay() + 6) % 7));
    return Array.from({ length: 42 }, (_, i) => {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        return {
            key,
            day: d.getDate(),
            inMonth: d.getMonth() === mon - 1,
            events: shown.value.filter((e) => dayKey(e.starts_at) <= key && lastDayKey(e.starts_at, e.ends_at) >= key),
            items: filter.value === 'followed' ? [] : (props.items ?? []).filter((it) => dayKey(it.starts_at) <= key && lastDayKey(it.starts_at, it.ends_at) >= key),
        };
    }).filter((d, i, all) => i < 35 || all.slice(35).some((x) => x.inMonth));
});

const go = (month: string, view: 'month' | 'year' = props.view ?? 'month'): void =>
    router.get(
        '/calendar',
        { month, ...(view === 'year' ? { view } : {}) },
        { preserveState: true, preserveScroll: true, replace: true, only: ['month', 'events', 'items', 'view'] },
    );

// Year view: 12 small months; each day shows how many things are on.
const yearMonths = computed(() =>
    Array.from({ length: 12 }, (_, m) => {
        const first = new Date(ym.value[0], m, 1);
        const lead = (first.getDay() + 6) % 7;
        const count = new Date(ym.value[0], m + 1, 0).getDate();
        const cells = Array.from({ length: lead + count }, (_, i) => {
            if (i < lead) return null;
            const key = `${ym.value[0]}-${String(m + 1).padStart(2, '0')}-${String(i - lead + 1).padStart(2, '0')}`;
            const n =
                shown.value.filter((e) => dayKey(e.starts_at) <= key && lastDayKey(e.starts_at, e.ends_at) >= key).length +
                (props.items ?? []).filter((it) => dayKey(it.starts_at) <= key && lastDayKey(it.starts_at, it.ends_at) >= key).length;
            return { key, day: i - lead + 1, n };
        });
        return { key: `${ym.value[0]}-${String(m + 1).padStart(2, '0')}`, label: first.toLocaleDateString('en-MY', { month: 'long' }), cells };
    }),
);
const shift = (by: number): void => {
    const d = new Date(ym.value[0], ym.value[1] - 1 + by, 1);
    go(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
};
const pickMonth = (e: Event): void => go((e.target as HTMLInputElement).value);
const thisMonth = props.today.slice(0, 7);

const grid = ref<HTMLElement | null>(null);
const { direction } = useSwipe(grid, { onSwipeEnd: () => (direction.value === 'left' ? shift(1) : direction.value === 'right' ? shift(-1) : undefined) });

const selectedDay = ref<string | null>(props.today.startsWith(props.month) ? props.today : null);
watch(
    () => props.month,
    (m) => (selectedDay.value = props.today.startsWith(m) ? props.today : null),
);
const dayEvents = computed(() => days.value.find((d) => d.key === selectedDay.value)?.events ?? []);
const dayItems = computed(() => days.value.find((d) => d.key === selectedDay.value)?.items ?? []);
const open = ref<CalEvent | null>(null);
const subscribing = ref(false);
const googleSubscribe = computed(() => `https://calendar.google.com/calendar/r?cid=${encodeURIComponent(props.feedUrl.replace(/^https?:/, 'webcal:'))}`);
const copied = ref(false);
const copyFeed = async (): Promise<void> => {
    await navigator.clipboard.writeText(props.feedUrl);
    copied.value = true;
};
const tone = (e: CalEvent): string => (e.registered ? 'bg-success text-white' : e.followed ? 'bg-brand-blue text-white' : 'bg-brand-magenta/15 text-brand-magenta');
</script>

<template>
    <Head title="Calendar" />
    <AppLayout wide>
        <div class="grid gap-4">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="grid size-9 place-items-center rounded-full border border-line bg-surface transition hover:bg-surface-muted"
                        aria-label="Previous month"
                        @click="shift(-1)"
                    >
                        ‹
                    </button>
                    <h1 class="min-w-40 text-center text-xl font-bold">{{ title }}</h1>
                    <button
                        type="button"
                        class="grid size-9 place-items-center rounded-full border border-line bg-surface transition hover:bg-surface-muted"
                        aria-label="Next month"
                        @click="shift(1)"
                    >
                        ›
                    </button>
                    <Button v-if="month !== thisMonth" size="sm" variant="ghost" @click="go(thisMonth)">Today</Button>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        type="month"
                        :value="month"
                        :min="`${years[0]}-01`"
                        :max="`${years.at(-1)}-12`"
                        class="h-9 rounded-control border border-line bg-surface px-2 text-sm"
                        aria-label="Go to month"
                        @change="pickMonth"
                    />
                    <div class="flex rounded-control border border-line p-0.5 text-xs font-semibold" role="radiogroup" aria-label="View">
                        <button
                            type="button"
                            role="radio"
                            :aria-checked="(view ?? 'month') === 'month'"
                            class="rounded-[0.55rem] px-3 py-1.5"
                            :class="(view ?? 'month') === 'month' ? 'bg-ink text-white' : 'text-ink-muted'"
                            @click="go(month, 'month')"
                        >
                            Month
                        </button>
                        <button
                            type="button"
                            role="radio"
                            :aria-checked="view === 'year'"
                            class="rounded-[0.55rem] px-3 py-1.5"
                            :class="view === 'year' ? 'bg-ink text-white' : 'text-ink-muted'"
                            @click="go(month, 'year')"
                        >
                            Year
                        </button>
                    </div>
                    <Button size="sm" variant="secondary" @click="subscribing = true"><Icon name="calendar" :size="16" /> Add to Google Calendar</Button>
                </div>
            </header>

            <div class="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none]">
                <button
                    v-for="f in [
                        { k: 'all', l: 'All events' },
                        { k: 'followed', l: 'Clubs I follow' },
                        { k: 'mine', l: 'My registrations' },
                    ] as const"
                    :key="f.k"
                    type="button"
                    class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                    :class="filter === f.k ? 'border-ink bg-ink text-white' : 'border-line bg-surface text-ink-muted hover:bg-surface-muted'"
                    @click="filter = f.k"
                >
                    {{ f.l }}
                </button>
            </div>

            <!-- Year view: every month at a glance; tap a month to open it. -->
            <section v-if="view === 'year'" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" aria-label="Year">
                <button
                    v-for="m in yearMonths"
                    :key="m.key"
                    type="button"
                    class="grid gap-1 rounded-card border border-line bg-surface p-3 text-left shadow-card transition hover:shadow-pop"
                    @click="go(m.key, 'month')"
                >
                    <span class="font-semibold">{{ m.label }}</span>
                    <span class="grid grid-cols-7 gap-0.5 text-center text-[10px]">
                        <span
                            v-for="(c, i) in m.cells"
                            :key="i"
                            class="grid aspect-square place-items-center rounded"
                            :class="c && c.n ? 'bg-brand-magenta/80 font-bold text-white' : c && c.key === today ? 'ring-1 ring-brand-magenta' : 'text-ink-muted'"
                        >
                            {{ c?.day ?? '' }}
                        </span>
                    </span>
                    <span class="text-xs text-ink-muted">{{ m.cells.reduce((s, c) => s + (c?.n ?? 0), 0) }} things on</span>
                </button>
            </section>

            <div v-else ref="grid" class="overflow-hidden rounded-card border border-line bg-surface shadow-card" data-no-motion>
                <div class="grid grid-cols-7 border-b border-line bg-surface-muted text-center text-[11px] font-semibold uppercase tracking-wide text-ink-muted">
                    <span v-for="d in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="d" class="py-2">{{ d }}</span>
                </div>
                <Transition name="fade-up" mode="out-in">
                    <div :key="month" class="grid grid-cols-7">
                        <button
                            v-for="d in days"
                            :key="d.key"
                            type="button"
                            class="flex min-h-16 flex-col gap-1 border-b border-r border-line p-1 text-left transition sm:min-h-28 sm:p-1.5 [&:nth-child(7n)]:border-r-0"
                            :class="[d.inMonth ? '' : 'bg-surface-muted/60 text-ink-muted', selectedDay === d.key ? 'bg-brand-cyan/10' : 'hover:bg-surface-muted']"
                            :aria-label="`${d.key}, ${d.events.length} events`"
                            @click="selectedDay = d.key"
                        >
                            <span class="grid size-6 place-items-center rounded-full text-xs font-semibold" :class="d.key === today ? 'bg-brand-magenta text-white' : ''">{{
                                d.day
                            }}</span>
                            <span class="hidden gap-0.5 sm:grid">
                                <span
                                    v-for="e in d.events.slice(0, 3)"
                                    :key="e.slug"
                                    class="truncate rounded px-1 py-0.5 text-[11px] font-medium"
                                    :class="tone(e)"
                                    role="link"
                                    @click.stop="open = e"
                                >
                                    {{ time(e.starts_at) }} {{ e.name }}
                                </span>
                                <span v-if="d.events.length > 3" class="px-1 text-[11px] text-ink-muted">+{{ d.events.length - 3 }} more</span>
                                <Link
                                    v-for="it in d.items.slice(0, 2)"
                                    :key="it.key"
                                    :href="it.url"
                                    class="flex items-center gap-1 truncate rounded border border-line px-1 py-0.5 text-[11px] font-medium"
                                    :class="it.kind === 'report_due' ? 'text-danger' : 'text-ink'"
                                    @click.stop
                                >
                                    <Icon :name="ITEM_ICON[it.kind]" :size="11" class="shrink-0" /><span class="truncate">{{ it.title }}</span>
                                </Link>
                            </span>
                            <span v-if="d.events.length" class="flex flex-wrap gap-0.5 sm:hidden">
                                <span
                                    v-for="e in d.events.slice(0, 3)"
                                    :key="e.slug"
                                    class="size-1.5 rounded-full"
                                    :class="e.registered ? 'bg-success' : e.followed ? 'bg-brand-blue' : 'bg-brand-magenta'"
                                />
                            </span>
                        </button>
                    </div>
                </Transition>
            </div>
            <p class="flex flex-wrap gap-3 text-xs text-ink-muted">
                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-success" /> Registered</span>
                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-brand-blue" /> Club you follow</span>
                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-brand-magenta" /> Other events</span>
                <span class="flex items-center gap-1"><Icon name="users" :size="12" /> Meeting</span>
                <span class="flex items-center gap-1"><Icon name="calendar" :size="12" /> Weekly activity</span>
                <span class="flex items-center gap-1 text-danger"><Icon name="file" :size="12" /> Report due</span>
                <span class="flex items-center gap-1"><Icon name="award" :size="12" /> AGM</span>
            </p>

            <section v-if="selectedDay && view !== 'year'" class="grid gap-2">
                <h2 class="font-semibold">{{ new Date(selectedDay).toLocaleDateString('en-MY', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}</h2>
                <p v-if="!dayEvents.length && !dayItems.length" class="rounded-card border border-dashed border-line p-4 text-sm text-ink-muted">Nothing on this day.</p>
                <ul v-if="dayItems.length" class="grid gap-2">
                    <li v-for="it in dayItems" :key="it.key">
                        <Link :href="it.url" class="flex items-center gap-3 rounded-card border border-line bg-surface p-3 text-sm shadow-card transition hover:shadow-pop">
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-lg"
                                :class="it.kind === 'report_due' ? 'bg-danger/10 text-danger' : 'bg-surface-muted text-ink'"
                                ><Icon :name="ITEM_ICON[it.kind]" :size="18"
                            /></span>
                            <span class="min-w-0 flex-1">
                                <strong class="block truncate">{{ it.title }}</strong>
                                <span class="block truncate text-xs text-ink-muted"
                                    >{{ it.kind === 'report_due' ? 'Due today' : `${time(it.starts_at)} – ${time(it.ends_at)}` }} · {{ it.club }}</span
                                >
                            </span>
                        </Link>
                    </li>
                </ul>
                <TransitionGroup tag="ul" name="list" class="relative grid gap-2">
                    <li v-for="e in dayEvents" :key="e.slug">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-card border border-line bg-surface p-3 text-left shadow-card transition hover:shadow-pop"
                            @click="open = e"
                        >
                            <img v-if="e.poster_url" :src="e.poster_url" alt="" class="size-14 shrink-0 rounded-lg object-cover" loading="lazy" />
                            <span v-else class="grid size-14 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-brand-yellow to-brand-magenta text-white"
                                ><Icon name="calendar" :size="22"
                            /></span>
                            <span class="min-w-0 flex-1 text-sm">
                                <strong class="block truncate">{{ e.name }}</strong>
                                <span class="block truncate text-xs text-ink-muted"
                                    >{{ time(e.starts_at) }} – {{ time(e.ends_at) }} · {{ e.club }}<template v-if="e.venue"> · {{ e.venue }}</template></span
                                >
                            </span>
                            <Badge v-if="e.registered" tone="success">Going</Badge>
                        </button>
                    </li>
                </TransitionGroup>
            </section>
        </div>

        <Modal :open="open !== null" :title="open?.name" @update:open="(v: boolean) => v || (open = null)">
            <div v-if="open" class="grid gap-3 text-sm">
                <img v-if="open.poster_url" :src="open.poster_url" alt="" class="max-h-56 w-full rounded-card object-cover object-top" />
                <p class="flex items-center gap-2">
                    <Icon name="clock" :size="16" class="text-ink-muted" />{{
                        new Date(open.starts_at).toLocaleString('en-MY', { dateStyle: 'full', timeStyle: 'short', timeZone: TZ })
                    }}
                    – {{ time(open.ends_at) }}
                </p>
                <p v-if="open.venue" class="flex items-center gap-2"><Icon name="pin" :size="16" class="text-ink-muted" />{{ open.venue }}</p>
                <p class="flex items-center gap-2">
                    <Icon name="users" :size="16" class="text-ink-muted" /><Link :href="`/clubs/${open.club_slug}`" class="font-medium hover:underline">{{ open.club }}</Link>
                </p>
                <p v-if="open.fee_sen" class="text-ink-muted">Fee {{ rm(open.fee_sen) }}</p>
                <div class="grid grid-cols-2 gap-2">
                    <Link :href="`/events/${open.slug}`"
                        ><Button block>{{ open.registered ? 'View my ticket' : open.registration_open ? 'Details & register' : 'Details' }}</Button></Link
                    >
                    <a :href="open.google_url" target="_blank" rel="noopener"><Button block variant="secondary">Add to Google</Button></a>
                </div>
            </div>
        </Modal>

        <Modal v-model:open="subscribing" title="Add all campus events to Google Calendar">
            <div class="grid gap-3 text-sm">
                <p class="text-ink-muted">
                    Subscribe once and every approved event appears in your Google Calendar, kept up to date automatically (Google refreshes it every few hours).
                </p>
                <a :href="googleSubscribe" target="_blank" rel="noopener"
                    ><Button block><Icon name="calendar" :size="16" /> Open Google Calendar to subscribe</Button></a
                >
                <p class="text-xs text-ink-muted">Or in Google Calendar: Other calendars → ＋ → From URL, and paste this private link:</p>
                <div class="flex gap-2">
                    <input
                        :value="feedUrl"
                        readonly
                        class="h-9 min-w-0 flex-1 rounded-control border border-line bg-surface-muted px-2 font-mono text-xs"
                        aria-label="Calendar link"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <Button size="sm" variant="secondary" @click="copyFeed">{{ copied ? 'Copied' : 'Copy' }}</Button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
