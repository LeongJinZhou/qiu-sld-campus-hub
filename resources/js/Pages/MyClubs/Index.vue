<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon, { type IconName } from '@/Components/ui/Icon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface MyClub {
    slug: string;
    name: string;
    logo_url: string | null;
    category: string | null;
    role: string | null;
    manages: boolean;
    membership: { status: string; expires_on: string | null } | null;
}

const props = defineProps<{ clubs: MyClub[] }>();
const managed = computed(() => props.clubs.filter((c) => c.manages));
const joined = computed(() => props.clubs.filter((c) => !c.manages));

// Everything a committee member or advisor runs, one tap away.
const tools = (slug: string): { label: string; icon: IconName; href: string }[] => [
    { label: 'New event', icon: 'plus', href: `/clubs/${slug}/events/create` },
    { label: 'Finance', icon: 'file', href: `/clubs/${slug}/finance` },
    { label: 'Members', icon: 'users', href: `/clubs/${slug}/members` },
    { label: 'Payments', icon: 'check', href: `/clubs/${slug}/payments/review` },
    { label: 'Meetings', icon: 'calendar', href: `/clubs/${slug}/meetings` },
    { label: 'AGM', icon: 'users', href: `/clubs/${slug}/agm` },
    { label: 'Venues', icon: 'pin', href: `/clubs/${slug}/venues` },
    { label: 'Club page', icon: 'home', href: `/clubs/${slug}` },
];
const statusTone = (s: string): 'success' | 'warning' | 'info' | 'neutral' => (s === 'active' ? 'success' : s === 'grace' || s === 'pending' ? 'warning' : s === 'alumni' ? 'info' : 'neutral');
const statusLabel: Record<string, string> = { active: 'Member', grace: 'Renew soon', pending: 'Payment pending', alumni: 'Alumni' };
</script>

<template>
    <Head title="My clubs" />
    <AppLayout>
        <div class="grid gap-6">
            <header>
                <h1 class="text-2xl font-bold">My clubs</h1>
                <p class="text-sm text-ink-muted">Clubs you run and clubs you've joined, without searching.</p>
            </header>

            <EmptyState v-if="!clubs.length" title="You haven't joined a club yet" description="Find a club you like and tap Join." />
            <Link v-if="!clubs.length" href="/clubs" class="justify-self-center rounded-control bg-ink px-5 py-2.5 text-sm font-semibold text-white">Discover clubs</Link>

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
                    <nav class="grid grid-cols-4 gap-1 p-3" :aria-label="`Manage ${c.name}`">
                        <Link v-for="t in tools(c.slug)" :key="t.label" :href="t.href" class="grid justify-items-center gap-1 rounded-control px-1 py-2.5 text-center text-xs font-medium transition hover:bg-surface-muted">
                            <span class="grid size-9 place-items-center rounded-full bg-surface-muted"><Icon :name="t.icon" :size="18" /></span>
                            {{ t.label }}
                        </Link>
                    </nav>
                </article>
            </section>

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
        </div>
    </AppLayout>
</template>
