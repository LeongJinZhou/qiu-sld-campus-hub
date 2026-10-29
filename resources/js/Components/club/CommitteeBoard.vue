<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import type { MemberCard } from '@/types';
import { ref } from 'vue';
import ProfilePopCard from './ProfilePopCard.vue';

defineProps<{ committee: MemberCard[]; advisors: MemberCard[] }>();

const selected = ref<MemberCard | null>(null);
const open = ref(false);

function show(member: MemberCard): void {
    selected.value = member;
    open.value = true;
}
</script>

<template>
    <div class="grid gap-8">
        <EmptyState v-if="committee.length === 0 && advisors.length === 0" title="No committee yet" description="SLD has not assigned a committee for this session." />

        <section v-for="group in [{ title: 'Advisor', members: advisors }, { title: 'Executive Committee', members: committee }]" v-show="group.members.length" :key="group.title">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-ink-muted">{{ group.title }}</h3>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <li v-for="member in group.members" :key="member.assignment_id">
                    <button
                        type="button"
                        class="grid w-full justify-items-center gap-2 rounded-card border border-line bg-surface p-4 text-center transition hover:shadow-card"
                        @click="show(member)"
                    >
                        <Avatar :src="member.photo_url" :name="member.name" size="lg" :ring="member.is_compulsory" />
                        <span class="line-clamp-2 text-sm font-semibold">{{ member.name }}</span>
                        <span class="text-xs font-medium text-brand-blue">{{ member.position }}</span>
                        <span class="text-xs text-ink-muted">{{ [member.programme_short, member.year_group].filter(Boolean).join(' · ') || member.title }}</span>
                    </button>
                </li>
            </ul>
        </section>

        <ProfilePopCard v-model:open="open" :member="selected" />
    </div>
</template>
