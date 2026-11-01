<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Icon from '@/Components/ui/Icon.vue';
import Modal from '@/Components/ui/Modal.vue';
import QrCode from '@/Components/ui/QrCode.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    club: {
        slug: string;
        name: string;
        logo_url: string | null;
        category: string | null;
        established_year: number | null;
        bio: string | null;
        instagram_handle: string | null;
        qr_svg: string;
        status: string;
    };
    counts: { committee: number; followers: number; events: number; members: number; alumni: number };
    membership: { status: string | null; fee_sen: number | null; drive: string | null; can_join: boolean };
    following: boolean;
    hasStories: boolean;
}>();
const emit = defineEmits<{ openStories: [] }>();

const qrOpen = ref(false);
const hasMembershipAction = computed(
    () => (props.membership.can_join && !['active', 'alumni'].includes(props.membership.status ?? '')) || ['active', 'alumni'].includes(props.membership.status ?? ''),
);
const follow = (): void => router.post(`/clubs/${props.club.slug}/follow`, {}, { preserveScroll: true });
</script>

<template>
    <!-- Instagram-style profile: logo with story ring, stats beside it, name and bio, then one row of actions. -->
    <header class="grid gap-4 rounded-card border border-line bg-surface p-4 shadow-card sm:p-6">
        <div class="grid grid-cols-[auto_1fr] items-center gap-4 sm:gap-8">
            <button type="button" class="shrink-0" :disabled="!hasStories" :aria-label="hasStories ? 'View stories' : undefined" @click="emit('openStories')">
                <span class="grid size-[5.5rem] place-items-center rounded-full sm:size-32" :class="hasStories ? 'bg-gradient-to-tr from-brand-yellow via-brand-orange to-brand-magenta' : 'bg-line'">
                    <span class="grid size-[calc(100%-6px)] place-items-center overflow-hidden rounded-full border-[3px] border-surface bg-surface [&>span]:!size-full [&>span>span]:!size-full">
                        <Avatar :src="club.logo_url" :name="club.name" size="lg" />
                    </span>
                </span>
            </button>
            <div class="grid min-w-0 gap-2">
                <div class="hidden flex-wrap items-center gap-2 sm:flex">
                    <h1 class="truncate text-xl font-bold">{{ club.name }}</h1>
                    <Badge v-if="club.status !== 'active'" :tone="club.status === 'inactive' ? 'danger' : 'warning'">{{
                        club.status === 'inactive' ? 'Inactive' : 'At risk'
                    }}</Badge>
                </div>
                <dl class="grid grid-cols-4 gap-1 text-center sm:max-w-md sm:text-left">
                    <div
                        v-for="stat in [
                            { label: 'events', value: counts.events },
                            { label: 'members', value: counts.members },
                            { label: 'followers', value: counts.followers },
                            { label: 'alumni', value: counts.alumni },
                        ]"
                        :key="stat.label"
                        class="min-w-0"
                    >
                        <dd class="text-base font-bold tabular-nums sm:text-lg">{{ stat.value }}</dd>
                        <dt class="text-[11px] leading-tight text-ink-muted sm:text-xs">{{ stat.label }}</dt>
                    </div>
                </dl>
            </div>
        </div>

        <div class="grid gap-1 text-sm">
            <div class="flex flex-wrap items-center gap-2 sm:hidden">
                <h1 class="text-base font-bold">{{ club.name }}</h1>
                <Badge v-if="club.status !== 'active'" :tone="club.status === 'inactive' ? 'danger' : 'warning'">{{ club.status === 'inactive' ? 'Inactive' : 'At risk' }}</Badge>
            </div>
            <p v-if="club.category" class="text-ink-muted">
                {{ club.category }}<template v-if="club.established_year"> · Est. {{ club.established_year }}</template> · {{ counts.committee }} on the committee
            </p>
            <p v-if="club.bio" class="whitespace-pre-line break-words">{{ club.bio }}</p>
            <a
                v-if="club.instagram_handle"
                :href="`https://instagram.com/${club.instagram_handle}`"
                target="_blank"
                rel="noopener"
                class="inline-flex w-fit items-center gap-1.5 font-semibold text-brand-blue"
            >
                <Icon name="instagram" :size="15" />@{{ club.instagram_handle }}
            </a>
        </div>

        <div class="grid grid-cols-[1fr_1fr_auto] gap-2 text-sm">
            <button
                type="button"
                class="inline-flex h-9 items-center justify-center gap-1.5 rounded-control px-3 font-semibold transition active:scale-[0.97]"
                :class="[following ? 'bg-surface-muted text-ink' : 'bg-brand-blue text-white hover:bg-brand-blue/90', hasMembershipAction ? '' : 'col-span-2']"
                :aria-pressed="following"
                @click="follow"
            >
                <Icon v-if="following" name="check" :size="15" />{{ following ? 'Following' : 'Follow' }}
            </button>
            <Link
                v-if="membership.can_join && !['active', 'alumni'].includes(membership.status ?? '')"
                :href="`/clubs/${club.slug}/join`"
                class="inline-flex h-9 items-center justify-center truncate rounded-control bg-gradient-to-r from-brand-orange to-brand-magenta px-3 font-semibold text-white transition active:scale-[0.97]"
                :class="membership.drive ? 'animate-tracker-pulse' : ''"
            >
                {{ membership.status === 'grace' ? 'Renew' : membership.status === 'pending' ? 'Continue joining' : 'Join'
                }}<template v-if="membership.fee_sen !== null">&nbsp;· {{ membership.fee_sen ? `RM ${(membership.fee_sen / 100).toFixed(2)}` : 'Free' }}</template>
            </Link>
            <span
                v-else-if="membership.status === 'active' || membership.status === 'alumni'"
                class="inline-flex h-9 items-center justify-center rounded-control bg-success/10 px-3 font-semibold text-success"
            >
                ✓ {{ membership.status === 'alumni' ? 'Alumni' : 'Member' }}
            </span>
            <button
                type="button"
                class="grid size-9 place-items-center rounded-control bg-surface-muted text-ink transition hover:bg-line active:scale-[0.97]"
                aria-label="Club QR code"
                @click="qrOpen = true"
            >
                <Icon name="qr" :size="18" />
            </button>
        </div>
        <Modal v-model:open="qrOpen" :title="club.name">
            <div class="grid justify-items-center gap-3">
                <QrCode :svg="club.qr_svg" label="Scan to open this club" :download-name="`${club.slug}-qr`" />
                <p class="text-center text-xs text-ink-muted">Print it on posters or show it at booths; it always opens this page.</p>
            </div>
        </Modal>
    </header>
</template>
