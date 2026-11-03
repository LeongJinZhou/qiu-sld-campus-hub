<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Icon from '@/Components/ui/Icon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface ClubEvent {
    slug: string;
    name: string;
    venue: string | null;
    poster_url: string | null;
    starts_at: string;
    ends_at: string;
    status: string;
    registered: number;
}

const props = defineProps<{ club: { slug: string; name: string }; ongoing: ClubEvent[]; upcoming: ClubEvent[]; past: ClubEvent[]; years: number[]; year: number }>();

type Tab = 'ongoing' | 'upcoming' | 'past';
const tab = ref<Tab>(props.ongoing.length ? 'ongoing' : props.upcoming.length ? 'upcoming' : 'past');
const TABS: { key: Tab; label: string }[] = [
    { key: 'ongoing', label: 'Ongoing' },
    { key: 'upcoming', label: 'Upcoming' },
    { key: 'past', label: 'Past' },
];
const listOf = (t: Tab): ClubEvent[] => (t === 'ongoing' ? props.ongoing : t === 'upcoming' ? props.upcoming : props.past);
const EMPTY: Record<Tab, string> = { ongoing: 'Nothing is happening right now.', upcoming: 'No events coming up.', past: 'No past events in this year.' };
const statusBadge: Record<string, { tone: 'warning' | 'info'; label: string }> = { in_review: { tone: 'info', label: 'In approval' }, returned: { tone: 'warning', label: 'Returned' } };
const pickYear = (e: Event): void =>
    router.get(`/clubs/${props.club.slug}/events`, { year: (e.target as HTMLSelectElement).value }, { preserveScroll: true, preserveState: true, only: ['past', 'year'] });
</script>

<template>
    <Head :title="`Events · ${club.name}`" />
    <AppLayout>
        <div class="grid gap-5">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold">Events · {{ club.name }}</h1>
                    <Link href="/activities" class="text-sm text-brand-blue">Back to Activities</Link>
                </div>
                <Link :href="`/clubs/${club.slug}/events/create`" class="inline-flex items-center gap-1.5 rounded-control bg-ink px-3 py-2 text-sm font-semibold text-white">
                    <Icon name="plus" :size="16" /> New event
                </Link>
            </header>

            <div class="grid grid-cols-3 gap-1 rounded-control bg-surface-muted p-1 text-sm font-medium" role="tablist">
                <button
                    v-for="t in TABS"
                    :key="t.key"
                    type="button"
                    role="tab"
                    :aria-selected="tab === t.key"
                    class="rounded-control px-2 py-2 transition"
                    :class="tab === t.key ? 'bg-surface shadow-card' : 'text-ink-muted'"
                    @click="tab = t.key"
                >
                    {{ t.label }}
                    <span v-if="t.key !== 'past' && listOf(t.key).length" class="ml-1 rounded-full bg-ink/10 px-1.5 text-xs tabular-nums">{{ listOf(t.key).length }}</span>
                </button>
            </div>

            <label v-if="tab === 'past'" class="flex items-center gap-2 text-sm">
                <span class="font-medium">Year</span>
                <select :value="year" class="h-10 rounded-control border border-line bg-surface px-3" :disabled="!years.length" @change="pickYear">
                    <option v-for="y in years.length ? years : [year]" :key="y" :value="y">{{ y }}</option>
                </select>
                <span class="text-ink-muted">{{ past.length }} event{{ past.length === 1 ? '' : 's' }}</span>
            </label>

            <ul class="grid gap-2">
                <li v-for="e in listOf(tab)" :key="e.slug">
                    <Link :href="`/events/${e.slug}`" class="flex items-center gap-3 rounded-card border border-line bg-surface p-3 shadow-card transition hover:shadow-pop">
                        <img v-if="e.poster_url" :src="e.poster_url" alt="" class="size-14 shrink-0 rounded-control object-cover object-top" />
                        <span v-else class="grid size-14 shrink-0 place-items-center rounded-control bg-surface-muted"><Icon name="calendar" :size="22" /></span>
                        <span class="grid min-w-0 flex-1 text-sm">
                            <span class="flex flex-wrap items-center gap-2">
                                <strong class="truncate">{{ e.name }}</strong>
                                <Badge v-if="tab === 'ongoing'" tone="danger">Live</Badge>
                                <Badge v-else-if="statusBadge[e.status]" :tone="statusBadge[e.status]!.tone">{{ statusBadge[e.status]!.label }}</Badge>
                            </span>
                            <span class="truncate text-xs text-ink-muted">{{ dateTime(e.starts_at) }}<template v-if="e.venue"> · {{ e.venue }}</template></span>
                            <span class="text-xs text-ink-muted">{{ e.registered }} registered</span>
                        </span>
                        <Icon name="send" :size="16" class="shrink-0 text-ink-muted" />
                    </Link>
                </li>
            </ul>
            <p v-if="!listOf(tab).length" class="rounded-card border border-dashed border-line p-6 text-center text-sm text-ink-muted">{{ EMPTY[tab] }}</p>
        </div>
    </AppLayout>
</template>
