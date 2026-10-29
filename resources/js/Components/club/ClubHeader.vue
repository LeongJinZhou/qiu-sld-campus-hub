<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Modal from '@/Components/ui/Modal.vue';
import QrCode from '@/Components/ui/QrCode.vue';
import { router } from '@inertiajs/vue3';
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
    };
    counts: { committee: number; followers: number; events: number };
    following: boolean;
    hasStories: boolean;
}>();
const emit = defineEmits<{ openStories: [] }>();

const qrOpen = ref(false);
const follow = (): void => router.post(`/clubs/${props.club.slug}/follow`, {}, { preserveScroll: true });
</script>

<template>
    <header class="grid gap-5 sm:grid-cols-[auto_1fr] sm:items-center sm:gap-8">
        <button type="button" class="justify-self-center sm:justify-self-start" :disabled="!hasStories" :aria-label="hasStories ? 'View stories' : undefined" @click="emit('openStories')">
            <Avatar :src="club.logo_url" :name="club.name" size="xl" :ring="hasStories" />
        </button>
        <div class="grid gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold">{{ club.name }}</h1>
                <Badge v-if="club.category" tone="info">{{ club.category }}</Badge>
            </div>
            <dl class="flex gap-6 text-sm">
                <div><dt class="sr-only">Events</dt><dd><strong>{{ counts.events }}</strong> events</dd></div>
                <div><dt class="sr-only">Followers</dt><dd><strong>{{ counts.followers }}</strong> followers</dd></div>
                <div><dt class="sr-only">Committee</dt><dd><strong>{{ counts.committee }}</strong> committee</dd></div>
                <div v-if="club.established_year"><dt class="sr-only">Established</dt><dd>Est. <strong>{{ club.established_year }}</strong></dd></div>
            </dl>
            <p v-if="club.bio" class="whitespace-pre-line text-sm">{{ club.bio }}</p>
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <button type="button" class="rounded-control px-4 py-1.5 font-semibold" :class="following ? 'bg-surface-muted' : 'bg-ink text-white'" @click="follow">
                    {{ following ? 'Following' : 'Follow' }}
                </button>
                <a v-if="club.instagram_handle" :href="`https://instagram.com/${club.instagram_handle}`" target="_blank" rel="noopener" class="font-medium text-brand-blue">
                    @{{ club.instagram_handle }}
                </a>
                <button type="button" class="font-medium text-ink-muted hover:text-ink" @click="qrOpen = true">Club QR</button>
            </div>
        </div>
        <Modal v-model:open="qrOpen" :title="club.name">
            <QrCode :svg="club.qr_svg" label="Scan to open this club" :download-name="`${club.slug}-qr`" />
        </Modal>
    </header>
</template>
