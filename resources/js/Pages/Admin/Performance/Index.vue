<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Select from '@/Components/ui/Select.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface ClubRow {
    slug: string;
    name: string;
    category: string;
    status: string;
    events: number;
    attendance: number;
    registered: number;
    members: number;
    engagement: number;
    on_time: number;
    score: number;
    rank: number;
    parts: Record<string, number>;
}
interface CategoryRow {
    category: string;
    clubs: number;
    events: number;
    attendance: number;
    members: number;
    engagement: number;
    score: number;
    best: string;
}
interface ClubDetail {
    slug: string;
    name: string;
    months: { key: string; label: string; events: number; attendance: number; registered: number }[];
    events: { slug: string; name: string; date: string; month: string; registered: number; attended: number; rate: number | null }[];
}

const props = defineProps<{ session: string; sessions: string[]; weights: Record<string, number>; clubs: ClubRow[]; categories: CategoryRow[]; club: ClubDetail | null }>();

// Drill path: All categories → one category's clubs → one club by month → one month's events.
const category = ref<string | null>(null);
const month = ref<string | null>(null);
const level = computed(() => (props.club ? 'club' : category.value ? 'clubs' : 'categories'));
const visibleClubs = computed(() => props.clubs.filter((c) => !category.value || c.category === category.value));
const openClub = (slug: string): void => router.get('/admin/performance', { session: props.session, club: slug }, { preserveState: true, preserveScroll: true, only: ['club'] });
const closeClub = (): void => {
    month.value = null;
    router.get('/admin/performance', { session: props.session }, { preserveState: true, preserveScroll: true, only: ['club'] });
};
const changeSession = (s: string | number | null | undefined): void =>
    router.get('/admin/performance', { session: s, ...(props.club ? { club: props.club.slug } : {}) }, { preserveScroll: true });
const leader = computed(() => props.clubs[0]);
const labels: Record<string, string> = { events: 'Events run', attendance: 'Attendance', members: 'Active members', engagement: 'Likes & comments', on_time: 'Documents on time' };

// One shared tooltip for every bar: follows the pointer, shows the exact values.
const tip = ref<{ x: number; y: number; title: string; lines: string[] } | null>(null);
const showTip = (e: PointerEvent, title: string, lines: string[]): void => {
    tip.value = { x: e.clientX, y: e.clientY, title, lines };
};
const maxMonth = computed(() => Math.max(1, ...(props.club?.months.map((m) => Math.max(m.registered, m.attendance)) ?? [1])));
const maxEvents = computed(() => Math.max(1, ...(props.club?.months.map((m) => m.events) ?? [1])));
const monthEvents = computed(() => (props.club?.events ?? []).filter((e) => !month.value || e.month === month.value));
const showTable = ref(false);
</script>

<template>
    <Head title="Club performance" />
    <PortalLayout title="Club performance">
        <div class="viz grid gap-5" @pointerleave="tip = null">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <nav class="flex flex-wrap items-center gap-1 text-sm" aria-label="Drill path">
                    <button
                        type="button"
                        class="font-medium"
                        :class="level === 'categories' ? 'text-ink' : 'text-brand-blue hover:underline'"
                        @click="((category = null), club && closeClub())"
                    >
                        All categories
                    </button>
                    <template v-if="category || club">
                        <span class="text-ink-muted">›</span>
                        <button type="button" class="font-medium" :class="level === 'clubs' ? 'text-ink' : 'text-brand-blue hover:underline'" @click="club && closeClub()">
                            {{ category ?? 'All clubs' }}
                        </button>
                    </template>
                    <template v-if="club">
                        <span class="text-ink-muted">›</span>
                        <button type="button" class="font-medium" :class="month ? 'text-brand-blue hover:underline' : 'text-ink'" @click="month = null">{{ club.name }}</button>
                    </template>
                    <template v-if="month && club">
                        <span class="text-ink-muted">›</span><span class="font-medium">{{ club.months.find((m) => m.key === month)?.label }}</span>
                    </template>
                </nav>
                <div class="flex items-end gap-2">
                    <Select :model-value="session" label="Session" :options="sessions.map((s) => ({ value: s, label: s }))" @update:model-value="changeSession" />
                    <button type="button" class="h-10 rounded-control border border-line px-3 text-sm" :aria-pressed="showTable" @click="showTable = !showTable">
                        {{ showTable ? 'Chart' : 'Table' }}
                    </button>
                </div>
            </div>

            <!-- Headline: who leads -->
            <div v-if="leader && !club" class="grid gap-3 sm:grid-cols-4">
                <div class="rounded-card border border-line bg-surface p-4 shadow-card sm:col-span-2">
                    <p class="text-xs text-ink-muted">Best performing club · {{ session }}</p>
                    <p class="truncate text-2xl font-black">{{ leader.name }}</p>
                    <p class="text-sm text-ink-muted">Score {{ leader.score }} / 100 · {{ leader.events }} events · {{ leader.attendance }} check-ins</p>
                </div>
                <div class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <p class="text-xs text-ink-muted">Clubs</p>
                    <p class="text-2xl font-bold tabular-nums">{{ clubs.length }}</p>
                </div>
                <div class="rounded-card border border-line bg-surface p-4 shadow-card">
                    <p class="text-xs text-ink-muted">Events this session</p>
                    <p class="text-2xl font-bold tabular-nums">{{ clubs.reduce((s, c) => s + c.events, 0) }}</p>
                </div>
            </div>

            <!-- Level 1: categories (drill up) -->
            <section v-if="level === 'categories'" class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card sm:p-5">
                <header>
                    <h2 class="font-semibold">Average score by category</h2>
                    <p class="text-xs text-ink-muted">Click a category to see its clubs.</p>
                </header>
                <table v-if="showTable" class="w-full text-sm">
                    <thead class="text-left text-xs text-ink-muted">
                        <tr>
                            <th class="py-1">Category</th>
                            <th>Clubs</th>
                            <th>Events</th>
                            <th>Check-ins</th>
                            <th>Members</th>
                            <th>Avg score</th>
                            <th>Best club</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in categories" :key="c.category" class="cursor-pointer border-t border-line hover:bg-surface-muted" @click="category = c.category">
                            <td class="py-2 font-medium">{{ c.category }}</td>
                            <td class="tabular-nums">{{ c.clubs }}</td>
                            <td class="tabular-nums">{{ c.events }}</td>
                            <td class="tabular-nums">{{ c.attendance }}</td>
                            <td class="tabular-nums">{{ c.members }}</td>
                            <td class="tabular-nums">{{ c.score }}</td>
                            <td>{{ c.best }}</td>
                        </tr>
                    </tbody>
                </table>
                <ul v-else class="grid gap-1.5">
                    <li v-for="c in categories" :key="c.category">
                        <button
                            type="button"
                            class="grid w-full grid-cols-[8rem_1fr_2.5rem] items-center gap-3 rounded-control py-1 text-left text-sm hover:bg-surface-muted sm:grid-cols-[12rem_1fr_3rem]"
                            @click="category = c.category"
                            @pointermove="showTip($event, c.category, [`Average score ${c.score}`, `${c.clubs} clubs · ${c.events} events`, `Best: ${c.best}`])"
                            @pointerleave="tip = null"
                        >
                            <span class="truncate">{{ c.category }}</span>
                            <span class="h-5 rounded-r bg-transparent"
                                ><span class="block h-full rounded-r-[4px] bg-[var(--series)] transition-[width] duration-700" :style="{ width: `${c.score}%` }"
                            /></span>
                            <span class="text-right tabular-nums text-ink-muted">{{ c.score }}</span>
                        </button>
                    </li>
                </ul>
            </section>

            <!-- Level 2: clubs ranked -->
            <section v-else-if="level === 'clubs'" class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card sm:p-5">
                <header>
                    <h2 class="font-semibold">Clubs ranked by score · {{ category }}</h2>
                    <p class="text-xs text-ink-muted">
                        Score out of 100:
                        {{
                            Object.entries(weights)
                                .map(([k, w]) => `${labels[k]} ${w}`)
                                .join(' · ')
                        }}. Each measure is compared with the best club. Click a club to drill down.
                    </p>
                </header>
                <table v-if="showTable" class="w-full text-sm">
                    <thead class="text-left text-xs text-ink-muted">
                        <tr>
                            <th class="py-1">#</th>
                            <th>Club</th>
                            <th>Events</th>
                            <th>Check-ins</th>
                            <th>Members</th>
                            <th>Likes & comments</th>
                            <th>On time</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in visibleClubs" :key="c.slug" class="cursor-pointer border-t border-line hover:bg-surface-muted" @click="openClub(c.slug)">
                            <td class="py-2 tabular-nums text-ink-muted">{{ c.rank }}</td>
                            <td class="font-medium">{{ c.name }}</td>
                            <td class="tabular-nums">{{ c.events }}</td>
                            <td class="tabular-nums">{{ c.attendance }}</td>
                            <td class="tabular-nums">{{ c.members }}</td>
                            <td class="tabular-nums">{{ c.engagement }}</td>
                            <td class="tabular-nums">{{ c.on_time }}%</td>
                            <td class="font-semibold tabular-nums">{{ c.score }}</td>
                        </tr>
                    </tbody>
                </table>
                <ul v-else class="grid gap-1.5">
                    <li v-for="c in visibleClubs" :key="c.slug">
                        <button
                            type="button"
                            class="grid w-full grid-cols-[1.5rem_8rem_1fr_2.5rem] items-center gap-2 rounded-control py-1 text-left text-sm hover:bg-surface-muted sm:grid-cols-[2rem_14rem_1fr_3rem]"
                            @click="openClub(c.slug)"
                            @pointermove="
                                showTip($event, c.name, [
                                    `Score ${c.score} (#${c.rank})`,
                                    `${c.events} events · ${c.attendance} check-ins`,
                                    `${c.members} members · ${c.engagement} likes & comments`,
                                    `${c.on_time}% documents on time`,
                                ])
                            "
                            @pointerleave="tip = null"
                        >
                            <span class="tabular-nums text-ink-muted">{{ c.rank }}</span>
                            <span class="flex min-w-0 items-center gap-1.5"
                                ><span class="truncate">{{ c.name }}</span
                                ><Badge v-if="c.status !== 'active'" :tone="c.status === 'inactive' ? 'danger' : 'warning'">{{
                                    c.status === 'inactive' ? 'Inactive' : 'At risk'
                                }}</Badge></span
                            >
                            <span class="h-5"
                                ><span class="block h-full rounded-r-[4px] bg-[var(--series)] transition-[width] duration-700" :style="{ width: `${c.score}%` }"
                            /></span>
                            <span class="text-right font-semibold tabular-nums">{{ c.score }}</span>
                        </button>
                    </li>
                </ul>
            </section>

            <!-- Level 3: one club by month, then its events -->
            <template v-else-if="club">
                <section class="grid gap-4 rounded-card border border-line bg-surface p-4 shadow-card sm:p-5">
                    <header class="flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h2 class="font-semibold">{{ club.name }} · check-ins by month</h2>
                            <p class="text-xs text-ink-muted">Click a month to see its events.</p>
                        </div>
                        <p class="flex items-center gap-3 text-xs text-ink-muted">
                            <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-[var(--series)]" /> Checked in</span>
                            <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm border border-dashed border-[var(--series)]" /> Registered</span>
                        </p>
                    </header>
                    <div class="flex h-48 items-end gap-1 border-b border-line">
                        <button
                            v-for="m in club.months"
                            :key="m.key"
                            type="button"
                            class="group relative flex h-full flex-1 flex-col justify-end rounded-t"
                            :class="month === m.key ? 'bg-surface-muted' : ''"
                            :aria-label="`${m.label}: ${m.attendance} checked in of ${m.registered} registered, ${m.events} events`"
                            @click="month = month === m.key ? null : m.key"
                            @pointermove="showTip($event, m.label, [`${m.attendance} checked in`, `${m.registered} registered`, `${m.events} events`])"
                            @pointerleave="tip = null"
                        >
                            <span
                                class="relative mx-auto w-3/4 rounded-t-[4px] border border-b-0 border-dashed border-[var(--series)] transition-[height] duration-700"
                                :style="{ height: `${(m.registered / maxMonth) * 100}%` }"
                            >
                                <span
                                    class="absolute inset-x-0 bottom-0 rounded-t-[4px] bg-[var(--series)] transition-[height] duration-700"
                                    :style="{ height: m.registered ? `${(m.attendance / m.registered) * 100}%` : '0' }"
                                />
                            </span>
                        </button>
                    </div>
                    <div class="flex gap-1 text-center text-[10px] text-ink-muted">
                        <span v-for="m in club.months" :key="m.key" class="flex-1 truncate">{{ m.label.slice(0, 3) }}</span>
                    </div>
                    <div>
                        <h3 class="mb-2 text-sm font-semibold">Events by month</h3>
                        <div class="flex h-16 items-end gap-1 border-b border-line">
                            <span
                                v-for="m in club.months"
                                :key="m.key"
                                class="mx-auto w-3/4 flex-1 rounded-t-[4px] bg-[var(--series)] opacity-70 transition-[height] duration-700"
                                :style="{ height: `${(m.events / maxEvents) * 100}%` }"
                                @pointermove="showTip($event, m.label, [`${m.events} events`])"
                                @pointerleave="tip = null"
                            />
                        </div>
                    </div>
                </section>
                <section class="grid gap-2 rounded-card border border-line bg-surface p-4 shadow-card sm:p-5">
                    <h2 class="font-semibold">Events{{ month ? ` · ${club.months.find((m) => m.key === month)?.label}` : '' }}</h2>
                    <p v-if="!monthEvents.length" class="text-sm text-ink-muted">No approved events{{ month ? ' this month' : ' this session' }}.</p>
                    <table v-else class="w-full text-sm">
                        <thead class="text-left text-xs text-ink-muted">
                            <tr>
                                <th class="py-1">Date</th>
                                <th>Event</th>
                                <th class="text-right">Registered</th>
                                <th class="text-right">Checked in</th>
                                <th class="text-right">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in monthEvents" :key="e.slug" class="border-t border-line">
                                <td class="whitespace-nowrap py-2 tabular-nums text-ink-muted">{{ e.date }}</td>
                                <td>
                                    <Link :href="`/events/${e.slug}/attendance`" class="font-medium hover:underline">{{ e.name }}</Link>
                                </td>
                                <td class="text-right tabular-nums">{{ e.registered }}</td>
                                <td class="text-right tabular-nums">{{ e.attended }}</td>
                                <td class="text-right tabular-nums">{{ e.rate === null ? '—' : `${e.rate}%` }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </template>

            <div
                v-if="tip"
                class="pointer-events-none fixed z-50 grid gap-0.5 rounded-control border border-line bg-surface px-3 py-2 text-xs shadow-pop"
                :style="{ left: `${Math.min(tip.x + 12, 9999)}px`, top: `${tip.y + 12}px` }"
                role="tooltip"
            >
                <strong class="text-ink">{{ tip.title }}</strong>
                <span v-for="(l, i) in tip.lines" :key="i" class="text-ink-muted">{{ l }}</span>
            </div>
        </div>
    </PortalLayout>
</template>

<style scoped>
/* One hue for every bar (a single measure per chart); "registered" is an outline of the same hue, not a second colour. */
.viz {
    --series: #2a78d6;
}
</style>
