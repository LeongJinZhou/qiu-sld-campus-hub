<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Modal from '@/Components/ui/Modal.vue';
import QrCode from '@/Components/ui/QrCode.vue';
import { ref } from 'vue';

defineProps<{
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
    counts: { committee: number; advisors: number };
}>();

const qrOpen = ref(false);
</script>

<template>
    <header class="grid gap-5 sm:grid-cols-[auto_1fr] sm:items-center sm:gap-8">
        <Avatar :src="club.logo_url" :name="club.name" size="xl" ring class="justify-self-center sm:justify-self-start" />
        <div class="grid gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold">{{ club.name }}</h1>
                <Badge v-if="club.category" tone="info">{{ club.category }}</Badge>
            </div>
            <dl class="flex gap-6 text-sm">
                <div><dt class="sr-only">Committee</dt><dd><strong>{{ counts.committee }}</strong> committee</dd></div>
                <div><dt class="sr-only">Advisors</dt><dd><strong>{{ counts.advisors }}</strong> advisor{{ counts.advisors === 1 ? '' : 's' }}</dd></div>
                <div v-if="club.established_year"><dt class="sr-only">Established</dt><dd>Est. <strong>{{ club.established_year }}</strong></dd></div>
            </dl>
            <p v-if="club.bio" class="whitespace-pre-line text-sm">{{ club.bio }}</p>
            <div class="flex flex-wrap items-center gap-4 text-sm">
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
