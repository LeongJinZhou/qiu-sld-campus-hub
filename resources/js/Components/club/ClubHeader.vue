<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Icon from '@/Components/ui/Icon.vue';
import Modal from '@/Components/ui/Modal.vue';
import QrCode from '@/Components/ui/QrCode.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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
    counts: { committee: number; followers: number; events: number; members: number };
    membership: { status: string | null; fee_sen: number | null; drive: string | null };
    following: boolean;
    hasStories: boolean;
}>();
const emit = defineEmits<{ openStories: [] }>();

const qrOpen = ref(false);
const follow = (): void => router.post(`/clubs/${props.club.slug}/follow`, {}, { preserveScroll: true });
</script>

<template>
    <header class="overflow-hidden rounded-card border border-line bg-surface shadow-card">
        <div class="h-28 bg-gradient-to-r from-brand-cyan via-brand-blue to-brand-magenta sm:h-36" />
        <div class="grid gap-5 px-5 pb-6 sm:grid-cols-[auto_1fr] sm:gap-8 sm:px-8">
        <button type="button" class="-mt-14 justify-self-center sm:justify-self-start" :disabled="!hasStories" :aria-label="hasStories ? 'View stories' : undefined" @click="emit('openStories')">
            <span class="block rounded-full bg-surface p-1"><Avatar :src="club.logo_url" :name="club.name" size="xl" :ring="hasStories" /></span>
        </button>
        <div class="grid gap-3 pt-2 sm:pt-4">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold">{{ club.name }}</h1>
                <Badge v-if="club.category" tone="info">{{ club.category }}</Badge>
                <Badge v-if="club.status !== 'active'" :tone="club.status === 'inactive' ? 'danger' : 'warning'">{{ club.status === 'inactive' ? 'Inactive' : 'At risk' }}</Badge>
            </div>
            <dl class="flex gap-6 text-sm">
                <div><dt class="sr-only">Members</dt><dd><strong>{{ counts.members }}</strong> members</dd></div>
                <div><dt class="sr-only">Events</dt><dd><strong>{{ counts.events }}</strong> events</dd></div>
                <div><dt class="sr-only">Followers</dt><dd><strong>{{ counts.followers }}</strong> followers</dd></div>
                <div><dt class="sr-only">Committee</dt><dd><strong>{{ counts.committee }}</strong> committee</dd></div>
                <div v-if="club.established_year"><dt class="sr-only">Established</dt><dd>Est. <strong>{{ club.established_year }}</strong></dd></div>
            </dl>
            <p v-if="club.bio" class="whitespace-pre-line text-sm">{{ club.bio }}</p>
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <Link
                    v-if="membership.fee_sen !== null && !['active', 'alumni'].includes(membership.status ?? '')"
                    :href="`/clubs/${club.slug}/join`"
                    class="rounded-control bg-gradient-to-r from-brand-orange to-brand-magenta px-4 py-1.5 font-semibold text-white shadow-card"
                    :class="membership.drive ? 'animate-tracker-pulse' : ''"
                >
                    {{ membership.status === 'grace' ? 'Renew' : 'Join' }} · {{ membership.fee_sen ? `RM ${(membership.fee_sen / 100).toFixed(2)}` : 'Free' }}
                </Link>
                <span v-else-if="membership.status === 'active' || membership.status === 'alumni'" class="rounded-control bg-success/10 px-3 py-1.5 font-semibold text-success">✓ Member</span>
                <button type="button" class="rounded-control px-4 py-1.5 font-semibold" :class="following ? 'bg-surface-muted' : 'bg-ink text-white'" @click="follow">
                    {{ following ? 'Following' : 'Follow' }}
                </button>
                <a v-if="club.instagram_handle" :href="`https://instagram.com/${club.instagram_handle}`" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-medium text-brand-blue">
                    <Icon name="instagram" :size="16" />@{{ club.instagram_handle }}
                </a>
                <button type="button" class="inline-flex items-center gap-1.5 font-medium text-ink-muted hover:text-ink" @click="qrOpen = true"><Icon name="qr" :size="16" />Club QR</button>
            </div>
        </div>
        </div>
        <Modal v-model:open="qrOpen" :title="club.name">
            <QrCode :svg="club.qr_svg" label="Scan to open this club" :download-name="`${club.slug}-qr`" />
        </Modal>
    </header>
</template>
