<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon from '@/Components/ui/Icon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime, rm } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';

type Type = 'all' | 'clubs' | 'events' | 'people';

const props = defineProps<{
    q: string;
    type: Type;
    clubs: { slug: string; name: string; logo_url: string | null; category: string | null }[];
    events: { slug: string; name: string; club: string; starts_at: string; venue: string | null; poster_url: string | null; past: boolean; fee_sen: number }[];
    people: { name: string; photo_url: string | null; url: string; subtitle: string }[];
}>();

const q = ref(props.q);
const tabs: { key: Type; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'clubs', label: 'Clubs' },
    { key: 'events', label: 'Events' },
    { key: 'people', label: 'People' },
];
const go = (type: Type = props.type): void => {
    router.get('/search', { ...(q.value.trim() ? { q: q.value.trim() } : {}), ...(type !== 'all' ? { type } : {}) }, { preserveState: true, replace: true, preserveScroll: true });
};
watchDebounced(q, () => go(), { debounce: 300 });

const show = (t: Exclude<Type, 'all'>): boolean => props.type === 'all' || props.type === t;
const nothing = computed(() => !props.clubs.length && !props.events.length && !props.people.length);
const peopleHint = computed(() => show('people') && q.value.trim().length < 2);
</script>

<template>
    <Head title="Search" />
    <AppLayout>
        <div class="grid gap-5">
            <div class="sticky top-0 z-10 -mx-4 grid gap-3 bg-surface-muted/90 px-4 pb-3 pt-1 backdrop-blur md:static md:mx-0 md:bg-transparent md:px-0 md:backdrop-blur-none">
                <label class="flex h-12 items-center gap-3 rounded-full border border-line bg-surface px-4 shadow-card focus-within:ring-2 focus-within:ring-brand-cyan">
                    <Icon name="search" :size="20" class="text-ink-muted" />
                    <input v-model="q" type="search" placeholder="Search clubs, events and people" class="h-full flex-1 bg-transparent text-[15px] outline-none" aria-label="Search" autofocus />
                </label>
                <div class="flex gap-2 overflow-x-auto [scrollbar-width:none]" role="tablist">
                    <button
                        v-for="t in tabs"
                        :key="t.key"
                        type="button"
                        role="tab"
                        :aria-selected="type === t.key"
                        class="whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-medium transition"
                        :class="type === t.key ? 'bg-ink text-white' : 'border border-line bg-surface hover:bg-surface-muted'"
                        @click="go(t.key)"
                    >
                        {{ t.label }}
                    </button>
                </div>
            </div>

            <EmptyState v-if="nothing && !peopleHint" title="Nothing found" :description="`No clubs, events or people match “${q}”.`" />

            <!-- People -->
            <section v-if="show('people') && (people.length || type === 'people')" class="grid gap-3">
                <h2 v-if="type === 'all'" class="text-sm font-semibold uppercase tracking-wide text-ink-muted">People</h2>
                <p v-if="peopleHint" class="text-sm text-ink-muted">Type at least 2 letters of a name to find students and staff.</p>
                <ul class="grid gap-2 sm:grid-cols-2">
                    <li v-for="p in people" :key="p.url">
                        <a :href="p.url" class="flex items-center gap-3 rounded-card border border-line bg-surface p-3 transition hover:shadow-card">
                            <Avatar :src="p.photo_url" :name="p.name" size="sm" />
                            <span class="grid min-w-0"><strong class="truncate text-sm">{{ p.name }}</strong><span class="truncate text-xs text-ink-muted">{{ p.subtitle }}</span></span>
                        </a>
                    </li>
                </ul>
            </section>

            <!-- Events -->
            <section v-if="show('events') && events.length" class="grid gap-3">
                <h2 v-if="type === 'all'" class="text-sm font-semibold uppercase tracking-wide text-ink-muted">{{ q ? 'Events' : 'Upcoming events' }}</h2>
                <ul class="grid gap-3 sm:grid-cols-2">
                    <li v-for="e in events" :key="e.slug">
                        <Link :href="`/events/${e.slug}`" class="flex items-center gap-3 rounded-card border border-line bg-surface p-3 transition hover:shadow-card" :class="e.past ? 'opacity-70' : ''">
                            <img v-if="e.poster_url" :src="e.poster_url" alt="" class="size-16 shrink-0 rounded-control object-cover object-top" loading="lazy" />
                            <span v-else class="grid size-16 shrink-0 place-items-center rounded-control bg-gradient-to-br from-brand-cyan to-brand-blue text-white"><Icon name="calendar" :size="24" /></span>
                            <span class="grid min-w-0 flex-1 gap-0.5 text-sm">
                                <strong class="truncate">{{ e.name }}</strong>
                                <span class="truncate text-xs text-ink-muted">{{ dateTime(e.starts_at) }}<template v-if="e.venue"> · {{ e.venue }}</template></span>
                                <span class="truncate text-xs text-ink-muted">{{ e.club }}</span>
                            </span>
                            <Badge v-if="e.past">Past</Badge>
                            <Badge v-else :tone="e.fee_sen ? 'warning' : 'success'">{{ e.fee_sen ? rm(e.fee_sen) : 'Free' }}</Badge>
                        </Link>
                    </li>
                </ul>
            </section>

            <!-- Clubs -->
            <section v-if="show('clubs') && clubs.length" class="grid gap-3">
                <div v-if="type === 'all'" class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">{{ q ? 'Clubs' : 'Discover clubs' }}</h2>
                    <button type="button" class="text-sm font-medium text-brand-blue" @click="go('clubs')">See all</button>
                </div>
                <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <li v-for="club in clubs" :key="club.slug">
                        <Link :href="`/clubs/${club.slug}`" class="group grid overflow-hidden rounded-card border border-line bg-surface text-center shadow-card transition hover:-translate-y-0.5 hover:shadow-pop">
                            <span class="h-14 bg-gradient-to-r from-brand-yellow via-brand-orange to-brand-magenta" />
                            <span class="-mt-9 grid justify-items-center gap-1.5 px-3 pb-4">
                                <span class="rounded-full bg-surface p-0.5"><Avatar :src="club.logo_url" :name="club.name" size="lg" ring /></span>
                                <span class="line-clamp-2 text-sm font-semibold">{{ club.name }}</span>
                                <span v-if="club.category" class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] text-ink-muted">{{ club.category }}</span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
