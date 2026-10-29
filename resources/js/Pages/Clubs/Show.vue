<script setup lang="ts">
import ClubHeader from '@/Components/club/ClubHeader.vue';
import CommitteeBoard from '@/Components/club/CommitteeBoard.vue';
import SessionSelector from '@/Components/club/SessionSelector.vue';
import Badge from '@/Components/ui/Badge.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { MemberCard, SessionOption } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    club: {
        id: number;
        slug: string;
        name: string;
        logo_url: string | null;
        category: string | null;
        established_year: number | null;
        bio: string | null;
        instagram_handle: string | null;
        qr_svg: string;
    };
    session: string;
    isCurrentSession: boolean;
    sessions: SessionOption[];
    committee: MemberCard[];
    advisors: MemberCard[];
    missingCompulsory: string[];
    can: { manage: boolean };
}>();

// Feed, Gallery, Events, FAQ, Members and Documents tabs arrive in Phases 2–3.
const tabs = [{ key: 'committee', label: 'Committee' }];
const tab = ref('committee');
</script>

<template>
    <Head :title="club.name" />
    <AppLayout>
        <div class="grid gap-8">
            <ClubHeader :club="club" :counts="{ committee: committee.length, advisors: advisors.length }" />

            <p v-if="missingCompulsory.length" class="rounded-card bg-warning/10 px-4 py-3 text-sm text-warning">
                Compulsory positions vacant: {{ missingCompulsory.join(', ') }}. Documents can't be submitted until they are filled.
                <Link v-if="can.manage" :href="`/admin/clubs/${club.slug}/edit`" class="font-semibold underline">Assign</Link>
            </p>

            <Tabs v-model="tab" :tabs="tabs">
                <template #committee>
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <SessionSelector :sessions="sessions" :selected="session" :url="`/clubs/${props.club.slug}`" />
                        <Badge v-if="!isCurrentSession">Past session · read-only</Badge>
                    </div>
                    <CommitteeBoard :committee="committee" :advisors="advisors" />
                </template>
            </Tabs>
        </div>
    </AppLayout>
</template>
