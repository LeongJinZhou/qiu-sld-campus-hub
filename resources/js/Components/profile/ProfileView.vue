<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon, { type IconName } from '@/Components/ui/Icon.vue';
import type { ProfileData } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Instagram-style lifelong profile. The owner sees extra tabs and actions through slots.
const props = defineProps<{ profile: ProfileData; extraTabs?: { key: string; label: string; icon: IconName }[] }>();

const tabs = computed(() => [
    { key: 'clubs', label: 'Clubs', icon: 'users' as IconName, show: props.profile.clubs !== null },
    { key: 'positions', label: 'Positions', icon: 'briefcase' as IconName, show: props.profile.positions !== null },
    { key: 'events', label: 'Events', icon: 'calendar' as IconName, show: props.profile.events !== null },
    { key: 'cca', label: 'CCA', icon: 'award' as IconName, show: props.profile.cca !== null },
    ...(props.extraTabs ?? []).map((t) => ({ ...t, show: true })),
].filter((t) => t.show));
const tab = ref(tabs.value[0]?.key ?? 'clubs');

const subtitle = computed(() => [props.profile.programme_short, props.profile.year_group, props.profile.title].filter(Boolean).join(' · '));
const stats = computed(() =>
    [
        { label: 'Clubs', value: props.profile.stats.clubs },
        { label: 'Positions', value: props.profile.stats.positions },
        { label: 'Events', value: props.profile.stats.events },
        { label: 'CCA hours', value: props.profile.stats.cca_hours },
    ].filter((s) => s.value !== null),
);
const ccaBySession = computed(() => props.profile.cca?.records ?? []);
</script>

<template>
    <article class="overflow-hidden rounded-card border border-line bg-surface shadow-card">
        <!-- Cover -->
        <div class="relative aspect-[3/1] max-h-56 w-full bg-gradient-to-r from-brand-yellow via-brand-orange to-brand-magenta">
            <img v-if="profile.cover_url" :src="profile.cover_url" alt="" class="absolute inset-0 size-full object-cover" />
            <div v-else class="absolute inset-0 opacity-30 [background-image:radial-gradient(circle_at_20%_20%,white_0,transparent_40%),radial-gradient(circle_at_80%_60%,#1CBDFD_0,transparent_45%)]" />
            <div class="absolute right-3 top-3 flex gap-2"><slot name="cover-actions" /></div>
        </div>

        <div class="px-5 pb-6 sm:px-8">
            <div class="-mt-14 flex flex-wrap items-end justify-between gap-4 sm:-mt-16">
                <div class="relative">
                    <Avatar :src="profile.photo_url" :name="profile.name" size="xl" ring class="[&>span]:size-28 sm:[&>span]:size-32" />
                    <slot name="avatar-action" />
                </div>
                <div class="flex flex-wrap gap-2 pb-1"><slot name="actions" /></div>
            </div>

            <div class="mt-4 grid gap-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold">{{ profile.name }}</h1>
                    <Badge v-if="profile.status === 'active'" tone="success">Active student</Badge>
                    <Badge v-else-if="profile.status === 'alumni'" tone="info">Alumni</Badge>
                    <Badge v-else-if="profile.title" tone="neutral">Staff</Badge>
                </div>
                <p v-if="subtitle" class="font-medium text-gradient-warm">{{ subtitle }}</p>
                <p v-if="profile.programme" class="text-sm text-ink-muted">{{ profile.programme }}<template v-if="profile.intake"> · Intake {{ profile.intake }}</template></p>
                <p v-if="profile.department" class="text-sm text-ink-muted">{{ profile.department }}</p>
                <slot name="meta" />
            </div>

            <dl v-if="stats.length" class="mt-5 grid divide-x divide-line rounded-card border border-line text-center" :style="{ gridTemplateColumns: `repeat(${stats.length}, minmax(0, 1fr))` }">
                <div v-for="s in stats" :key="s.label" class="py-3">
                    <dd class="text-xl font-bold tabular-nums">{{ s.value }}</dd>
                    <dt class="text-xs text-ink-muted">{{ s.label }}</dt>
                </div>
            </dl>
        </div>

        <!-- Tabs -->
        <div class="border-t border-line">
            <div role="tablist" class="flex gap-1 overflow-x-auto px-2 [scrollbar-width:none] sm:justify-center sm:gap-8">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    role="tab"
                    :aria-selected="tab === t.key"
                    class="-mt-px flex items-center gap-1.5 border-t-2 px-3 py-3 text-xs font-semibold uppercase tracking-wide transition"
                    :class="tab === t.key ? 'border-ink text-ink' : 'border-transparent text-ink-muted hover:text-ink'"
                    @click="tab = t.key"
                >
                    <Icon :name="t.icon" :size="16" />{{ t.label }}
                </button>
            </div>

            <div class="px-5 pb-6 pt-2 sm:px-8" role="tabpanel">
                <template v-if="tab === 'clubs'">
                    <EmptyState v-if="!profile.clubs?.length" title="No clubs yet" description="Clubs you join or serve will show here." />
                    <ul class="grid gap-3 sm:grid-cols-2">
                        <li v-for="c in profile.clubs" :key="c.url">
                            <Link :href="c.url" class="flex items-center gap-3 rounded-card border border-line p-3 transition hover:shadow-card">
                                <Avatar :src="c.logo_url" :name="c.name" size="md" ring />
                                <span class="grid min-w-0">
                                    <strong class="truncate">{{ c.name }}</strong>
                                    <span class="text-xs text-ink-muted">{{ c.category }}</span>
                                    <span v-if="c.current_position" class="text-xs font-semibold text-brand-blue">{{ c.current_position }}</span>
                                    <span v-else-if="c.membership" class="text-xs font-medium text-success">{{ c.membership }}</span>
                                </span>
                            </Link>
                        </li>
                    </ul>
                </template>

                <template v-else-if="tab === 'positions'">
                    <EmptyState v-if="!profile.positions?.length" title="No positions yet" />
                    <ol class="relative grid gap-5 border-l-2 border-line pl-6">
                        <li v-for="(p, i) in profile.positions" :key="i" class="relative">
                            <span class="absolute -left-[33px] top-1 grid size-4 place-items-center rounded-full ring-4 ring-surface" :class="p.kind === 'club' ? 'bg-brand-orange' : 'bg-brand-cyan'" />
                            <p class="text-xs font-medium uppercase tracking-wide text-ink-muted">{{ p.session }} · {{ p.kind === 'club' ? 'Club' : 'Event committee' }}</p>
                            <p class="font-semibold">{{ p.position }}</p>
                            <Link v-if="p.url" :href="p.url" class="text-sm text-brand-blue">{{ p.organisation }}</Link>
                        </li>
                    </ol>
                </template>

                <template v-else-if="tab === 'events'">
                    <EmptyState v-if="!profile.events?.length" title="No events attended yet" description="Events you clock in to will appear here." />
                    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <li v-for="e in profile.events" :key="e.url">
                            <Link :href="e.url" class="group block overflow-hidden rounded-card border border-line">
                                <img v-if="e.poster_url" :src="e.poster_url" alt="" class="aspect-square w-full object-cover transition group-hover:scale-105" />
                                <div v-else class="grid aspect-square place-items-center bg-gradient-to-br from-brand-cyan to-brand-blue p-3 text-center text-sm font-bold text-white">{{ e.name }}</div>
                                <div class="grid gap-0.5 p-2 text-xs">
                                    <strong class="truncate text-sm">{{ e.name }}</strong>
                                    <span class="text-ink-muted">{{ e.date }} · {{ e.role }}</span>
                                </div>
                            </Link>
                        </li>
                    </ul>
                </template>

                <template v-else-if="tab === 'cca'">
                    <div class="mb-4 flex items-center gap-4 rounded-card bg-gradient-to-r from-brand-cyan/15 to-brand-magenta/15 p-4">
                        <Icon name="award" :size="36" class="text-brand-magenta" />
                        <div>
                            <p class="text-3xl font-bold tabular-nums">{{ profile.cca?.total ?? 0 }}</p>
                            <p class="text-sm text-ink-muted">CCA hours earned (1 event hour = 1 CCA)</p>
                        </div>
                    </div>
                    <EmptyState v-if="!ccaBySession.length" title="No CCA records yet" />
                    <ul class="divide-y divide-line">
                        <li v-for="(r, i) in ccaBySession" :key="i" class="flex items-center justify-between gap-3 py-2.5 text-sm">
                            <span class="grid"><strong>{{ r.event }}</strong><span class="text-xs text-ink-muted">{{ r.date }} · {{ r.role }}</span></span>
                            <Badge tone="info">+{{ r.hours }}</Badge>
                        </li>
                    </ul>
                </template>

                <slot v-else :name="`tab-${tab}`" />
            </div>
        </div>
    </article>
</template>
