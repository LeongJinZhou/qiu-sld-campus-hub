<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon from '@/Components/ui/Icon.vue';
import type { MemberCard } from '@/types';
import { computed, ref } from 'vue';
import ProfilePopCard from './ProfilePopCard.vue';

const props = defineProps<{ committee: MemberCard[]; advisors: MemberCard[] }>();

const selected = ref<MemberCard | null>(null);
const open = ref(false);

function show(member: MemberCard): void {
    selected.value = member;
    open.value = true;
}

// Each compulsory office has its own brand colour, so the four leaders stand out from the wider team.
const accents: Record<string, string> = {
    President: 'from-brand-orange to-brand-magenta',
    'Vice President': 'from-brand-magenta to-brand-blue',
    Secretary: 'from-brand-cyan to-brand-blue',
    Treasurer: 'from-brand-yellow to-brand-orange',
};
const leaders = computed(() => props.committee.filter((m) => m.is_compulsory));
const team = computed(() => props.committee.filter((m) => !m.is_compulsory));
const subtitle = (m: MemberCard): string => [m.programme_short, m.year_group].filter(Boolean).join(' · ') || (m.title ?? '');
</script>

<template>
    <div class="grid gap-8">
        <EmptyState v-if="committee.length === 0 && advisors.length === 0" title="No committee yet" description="SLD has not assigned a committee for this session." />

        <!-- Advisor: one wide featured card each. -->
        <section v-if="advisors.length" class="grid gap-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">{{ advisors.length > 1 ? 'Advisors' : 'Advisor' }}</h3>
            <button
                v-for="a in advisors"
                :key="a.assignment_id"
                type="button"
                class="relative flex items-center gap-4 overflow-hidden rounded-card border border-line bg-surface p-4 text-left shadow-card transition hover:shadow-pop sm:p-5"
                @click="show(a)"
            >
                <span class="absolute inset-y-0 left-0 w-1.5 bg-gradient-to-b from-brand-cyan to-brand-magenta" aria-hidden="true" />
                <Avatar :src="a.photo_url" :name="a.name" size="lg" ring />
                <span class="grid min-w-0 flex-1 gap-0.5">
                    <span class="text-xs font-semibold uppercase tracking-wide text-gradient-cool">Club advisor</span>
                    <strong class="truncate text-lg">{{ a.name }}</strong>
                    <span v-if="a.title" class="truncate text-sm text-ink-muted">{{ a.title }}</span>
                    <span class="mt-1 text-xs text-ink-muted">Guides the committee and signs off every club document.</span>
                </span>
                <Icon name="user" :size="18" class="shrink-0 text-ink-muted" />
            </button>
        </section>

        <!-- The four compulsory offices. -->
        <section v-if="leaders.length" class="grid gap-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Executive committee</h3>
            <ul class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <li v-for="m in leaders" :key="m.assignment_id">
                    <button type="button" class="group grid h-full w-full content-start overflow-hidden rounded-card border border-line bg-surface text-center shadow-card transition hover:-translate-y-0.5 hover:shadow-pop" @click="show(m)">
                        <span class="h-14 bg-gradient-to-r" :class="accents[m.position ?? ''] ?? 'from-brand-cyan to-brand-magenta'" />
                        <span class="-mt-10 grid justify-items-center gap-1.5 px-3 pb-4">
                            <span class="rounded-full bg-surface p-1"><Avatar :src="m.photo_url" :name="m.name" size="lg" /></span>
                            <span class="rounded-full bg-ink px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-white">{{ m.position }}</span>
                            <span class="line-clamp-2 text-sm font-semibold">{{ m.name }}</span>
                            <span class="text-xs text-ink-muted">{{ subtitle(m) }}</span>
                        </span>
                    </button>
                </li>
            </ul>
        </section>

        <!-- Everyone else on the committee: compact rows. -->
        <section v-if="team.length" class="grid gap-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Committee members</h3>
            <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="m in team" :key="m.assignment_id">
                    <button type="button" class="flex w-full items-center gap-3 rounded-card border border-line bg-surface p-3 text-left transition hover:shadow-card" @click="show(m)">
                        <Avatar :src="m.photo_url" :name="m.name" size="sm" />
                        <span class="grid min-w-0">
                            <strong class="truncate text-sm">{{ m.name }}</strong>
                            <span class="truncate text-xs"><span class="font-medium text-brand-blue">{{ m.position }}</span><template v-if="subtitle(m)"> · <span class="text-ink-muted">{{ subtitle(m) }}</span></template></span>
                        </span>
                    </button>
                </li>
            </ul>
        </section>

        <ProfilePopCard v-model:open="open" :member="selected" />
    </div>
</template>
