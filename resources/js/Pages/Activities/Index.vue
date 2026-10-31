<script setup lang="ts">
import TicketCard from '@/Components/tickets/TicketCard.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon, { type IconName } from '@/Components/ui/Icon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime } from '@/lib/format';
import type { Ticket } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface EventCard {
    slug: string;
    name: string;
    club: string | null;
    starts_at: string;
    venue: string | null;
    poster_url: string | null;
    live: boolean;
    past: boolean;
    registered: number;
    unpaid: number;
}
interface MyClub {
    slug: string;
    name: string;
    logo_url: string | null;
    category: string | null;
    role: string | null;
    manages: boolean;
    events: EventCard[];
    membership: { status: string; expires_on: string | null } | null;
}

const props = defineProps<{ tickets: Ticket[]; helping: EventCard[]; clubs: MyClub[] }>();
const managed = computed(() => props.clubs.filter((c) => c.manages));
const joined = computed(() => props.clubs.filter((c) => !c.manages));
const upcoming = computed(() => props.tickets.filter((t) => !t.event.past));
const past = computed(() => props.tickets.filter((t) => t.event.past));

// Everything a committee member or advisor runs, one tap away.
const tools = (slug: string): { label: string; icon: IconName; href: string }[] => [
    { label: 'New event', icon: 'plus', href: `/clubs/${slug}/events/create` },
    { label: 'Finance', icon: 'file', href: `/clubs/${slug}/finance` },
    { label: 'Members', icon: 'users', href: `/clubs/${slug}/members` },
    { label: 'Participants', icon: 'ticket', href: `/clubs/${slug}/participants` },
    { label: 'Payments', icon: 'check', href: `/clubs/${slug}/payments/review` },
    { label: 'Meetings', icon: 'calendar', href: `/clubs/${slug}/meetings` },
    { label: 'AGM', icon: 'award', href: `/clubs/${slug}/agm` },
    { label: 'Venues', icon: 'pin', href: `/clubs/${slug}/venues` },
];
const statusTone = (s: string): 'success' | 'warning' | 'info' | 'neutral' => (s === 'active' ? 'success' : s === 'grace' || s === 'pending' ? 'warning' : s === 'alumni' ? 'info' : 'neutral');
const statusLabel: Record<string, string> = { active: 'Member', grace: 'Renew soon', pending: 'Payment pending', alumni: 'Alumni' };
const ticketHref = (t: Ticket): string => (t.status === 'unpaid' ? `/events/${t.event.slug}/pay` : (t.url ?? `/events/${t.event.slug}`));
</script>

<template>
    <Head title="Activities" />
    <AppLayout>
        <div class="grid gap-8">
            <header>
                <h1 class="text-2xl font-bold">Activities</h1>
                <p class="text-sm text-ink-muted">Your tickets, the clubs you run and the clubs you've joined.</p>
            </header>

            <!-- Tickets -->
            <section class="grid gap-3">
                <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-ink-muted"><Icon name="ticket" :size="16" /> My tickets</h2>
                <div v-if="!upcoming.length" class="rounded-card border border-dashed border-line p-5 text-center text-sm text-ink-muted">
                    No upcoming events. <Link href="/search?type=events" class="font-medium text-brand-blue">Find one to join</Link>
                </div>
                <ul class="grid gap-3 md:grid-cols-2">
                    <li v-for="t in upcoming" :key="`${t.event.slug}`">
                        <Link :href="ticketHref(t)" class="block transition hover:-translate-y-0.5"><TicketCard :ticket="t" compact /></Link>
                    </li>
                </ul>
                <details v-if="past.length" class="text-sm">
                    <summary class="cursor-pointer text-ink-muted">Past tickets ({{ past.length }})</summary>
                    <ul class="mt-3 grid gap-3 opacity-80 md:grid-cols-2">
                        <li v-for="t in past" :key="t.event.slug"><Link :href="ticketHref(t)"><TicketCard :ticket="t" compact /></Link></li>
                    </ul>
                </details>
            </section>

            <!-- Door helper -->
            <section v-if="helping.length" class="grid gap-3">
                <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-ink-muted"><Icon name="scan" :size="16" /> Helping at</h2>
                <ul class="grid gap-2 md:grid-cols-2">
                    <li v-for="e in helping" :key="e.slug">
                        <Link :href="`/events/${e.slug}/attendance`" class="flex items-center gap-3 rounded-card border border-line bg-surface p-3 shadow-card transition hover:shadow-pop">
                            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-cyan to-brand-blue text-white"><Icon name="scan" :size="20" /></span>
                            <span class="grid min-w-0 flex-1 text-sm"><strong class="truncate">{{ e.name }}</strong><span class="truncate text-xs text-ink-muted">{{ dateTime(e.starts_at) }} · {{ e.club }}</span></span>
                            <Badge v-if="e.live" tone="danger">Live</Badge>
                            <span class="text-xs font-semibold text-brand-blue">Scan →</span>
                        </Link>
                    </li>
                </ul>
            </section>

            <!-- Clubs I run -->
            <section v-if="managed.length" class="grid gap-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Clubs you run</h2>
                <article v-for="c in managed" :key="c.slug" class="overflow-hidden rounded-card border border-line bg-surface shadow-card">
                    <Link :href="`/clubs/${c.slug}`" class="flex items-center gap-3 bg-gradient-to-r from-brand-cyan/10 via-brand-blue/5 to-brand-magenta/10 p-4">
                        <Avatar :src="c.logo_url" :name="c.name" size="md" ring />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-base font-semibold">{{ c.name }}</span>
                            <span class="text-sm text-ink-muted">{{ c.role }}<template v-if="c.category"> · {{ c.category }}</template></span>
                        </span>
                        <Icon name="send" :size="18" class="text-ink-muted" />
                    </Link>

                    <!-- This club's events: one tap to the door desk. -->
                    <div v-if="c.events.length" class="grid gap-2 border-t border-line p-3">
                        <p class="px-1 text-xs font-semibold uppercase tracking-wide text-ink-muted">Events</p>
                        <div v-for="e in c.events" :key="e.slug" class="flex items-center gap-3 rounded-control border border-line p-2.5">
                            <img v-if="e.poster_url" :src="e.poster_url" alt="" class="size-12 shrink-0 rounded-control object-cover object-top" />
                            <span v-else class="grid size-12 shrink-0 place-items-center rounded-control bg-surface-muted"><Icon name="calendar" :size="20" /></span>
                            <Link :href="`/events/${e.slug}`" class="grid min-w-0 flex-1 text-sm">
                                <span class="flex items-center gap-2"><strong class="truncate">{{ e.name }}</strong><Badge v-if="e.live" tone="danger">Live</Badge><Badge v-else-if="e.past">Ended</Badge></span>
                                <span class="truncate text-xs text-ink-muted">{{ dateTime(e.starts_at) }} · {{ e.registered }} registered<template v-if="e.unpaid"> · <span class="font-semibold text-warning">{{ e.unpaid }} not paid</span></template></span>
                            </Link>
                            <Link :href="`/events/${e.slug}/attendance`" class="flex shrink-0 items-center gap-1.5 rounded-control bg-ink px-3 py-2 text-xs font-semibold text-white"><Icon name="scan" :size="14" /> Door</Link>
                        </div>
                    </div>

                    <nav class="grid grid-cols-4 gap-1 border-t border-line p-3" :aria-label="`Manage ${c.name}`">
                        <Link v-for="t in tools(c.slug)" :key="t.label" :href="t.href" class="grid justify-items-center gap-1 rounded-control px-1 py-2.5 text-center text-xs font-medium transition hover:bg-surface-muted">
                            <span class="grid size-9 place-items-center rounded-full bg-surface-muted"><Icon :name="t.icon" :size="18" /></span>
                            {{ t.label }}
                        </Link>
                    </nav>
                </article>
            </section>

            <!-- Clubs I joined -->
            <section v-if="joined.length" class="grid gap-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Clubs you've joined</h2>
                <ul class="grid gap-2 sm:grid-cols-2">
                    <li v-for="c in joined" :key="c.slug">
                        <Link :href="c.membership?.status === 'pending' ? `/clubs/${c.slug}/join` : `/clubs/${c.slug}`" class="flex items-center gap-3 rounded-card border border-line bg-surface p-4 shadow-card transition hover:shadow-pop">
                            <Avatar :src="c.logo_url" :name="c.name" size="md" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ c.name }}</span>
                                <span v-if="c.membership?.expires_on" class="text-xs text-ink-muted">Until {{ c.membership.expires_on }}</span>
                            </span>
                            <Badge v-if="c.membership" :tone="statusTone(c.membership.status)">{{ statusLabel[c.membership.status] ?? c.membership.status }}</Badge>
                        </Link>
                    </li>
                </ul>
            </section>

            <template v-if="!clubs.length && !tickets.length">
                <EmptyState title="Nothing here yet" description="Join a club or register for an event and it will show up here." />
                <Link href="/search" class="justify-self-center rounded-control bg-ink px-5 py-2.5 text-sm font-semibold text-white">Explore clubs and events</Link>
            </template>
        </div>
    </AppLayout>
</template>
