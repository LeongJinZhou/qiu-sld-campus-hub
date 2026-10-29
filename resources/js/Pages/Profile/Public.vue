<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Card from '@/Components/ui/Card.vue';
import { Head } from '@inertiajs/vue3';

export interface ProfileData {
    name: string;
    photo_url: string | null;
    programme_short: string | null;
    programme: string | null;
    year_group: string | null;
    title: string | null;
    clubs: { name: string; url: string }[] | null;
    positions: { session: string | null; club: string | null; position: string }[] | null;
}

defineProps<{ profile: ProfileData }>();
</script>

<template>
    <Head :title="profile.name" />
    <main class="mx-auto grid max-w-2xl gap-6 px-4 py-8">
        <header class="grid justify-items-center gap-3 text-center">
            <Avatar :src="profile.photo_url" :name="profile.name" size="xl" ring />
            <h1 class="text-xl font-bold">{{ profile.name }}</h1>
            <p class="text-sm text-ink-muted">
                {{ [profile.programme_short, profile.year_group, profile.title].filter(Boolean).join(' · ') }}
            </p>
            <p v-if="profile.programme" class="text-xs text-ink-muted">{{ profile.programme }}</p>
        </header>

        <Card v-if="profile.clubs?.length">
            <template #title>Clubs</template>
            <ul class="flex flex-wrap gap-2">
                <li v-for="club in profile.clubs" :key="club.url">
                    <a :href="club.url" class="inline-block rounded-full bg-surface-muted px-3 py-1 text-sm hover:bg-line">{{ club.name }}</a>
                </li>
            </ul>
        </Card>

        <Card v-if="profile.positions?.length">
            <template #title>Positions</template>
            <ul class="grid gap-2 text-sm">
                <li v-for="(item, index) in profile.positions" :key="index" class="flex justify-between gap-4">
                    <span><strong>{{ item.position }}</strong> · {{ item.club }}</span>
                    <span class="text-ink-muted">{{ item.session }}</span>
                </li>
            </ul>
        </Card>
    </main>
</template>
