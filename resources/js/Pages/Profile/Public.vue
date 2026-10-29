<script setup lang="ts">
import ProfileView from '@/Components/profile/ProfileView.vue';
import type { ProfileData } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ profile: ProfileData }>();
</script>

<template>
    <Head :title="profile.name" />
    <div class="min-h-dvh bg-surface-muted">
        <header class="flex h-14 items-center justify-between border-b border-line bg-surface px-4">
            <Link href="/feed" class="flex items-center gap-2">
                <img src="/brand/student-activity-black.png" alt="QIU Student Activity" class="h-8 w-auto" />
                <span class="font-bold"><span class="text-gradient-warm">Campus</span> <span class="text-gradient-cool">Hub</span></span>
            </Link>
            <span class="text-xs text-ink-muted">Verified QIU profile</span>
        </header>
        <main class="mx-auto max-w-3xl px-4 py-6">
            <ProfileView :profile="profile" />
        </main>
    </div>
</template>
