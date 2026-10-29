<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Toast from '@/Components/ui/Toast.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

// href null = page arrives in a later phase.
const tabs = computed(() => [
    { label: 'Feed', icon: '⌂', href: null },
    { label: 'Search', icon: '⌕', href: '/clubs' },
    { label: 'Create', icon: '＋', href: null },
    { label: 'Notifications', icon: '♡', href: null },
    { label: 'Profile', icon: '◉', href: '/me' },
]);
</script>

<template>
    <div class="min-h-dvh pb-20 md:pb-0">
        <Toast />
        <header class="sticky top-0 z-30 border-b border-line bg-surface/90 backdrop-blur">
            <div class="mx-auto flex h-14 max-w-5xl items-center justify-between gap-4 px-4">
                <Link href="/clubs" class="flex items-center gap-2 font-semibold">
                    <img src="/brand/QIU_Student_Activity_Black.png" alt="" class="h-8 w-auto" />
                    <span class="text-gradient-warm hidden sm:inline">Campus Hub</span>
                </Link>
                <nav class="hidden items-center gap-6 text-sm md:flex">
                    <template v-for="tab in tabs" :key="tab.label">
                        <Link v-if="tab.href" :href="tab.href" class="text-ink-muted hover:text-ink">{{ tab.label }}</Link>
                    </template>
                    <Link v-if="user?.roles.some((r) => r === 'admin' || r === 'superadmin')" href="/admin/clubs" class="text-ink-muted hover:text-ink">SLD Admin</Link>
                </nav>
                <Link v-if="user" href="/me" aria-label="My profile">
                    <Avatar :src="user.avatar_url" :name="user.name" size="sm" />
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6">
            <slot />
        </main>

        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface md:hidden" aria-label="Primary">
            <ul class="grid grid-cols-5">
                <li v-for="tab in tabs" :key="tab.label">
                    <Link v-if="tab.href" :href="tab.href" class="grid h-16 place-items-center text-xs text-ink-muted">
                        <span class="text-xl leading-none" aria-hidden="true">{{ tab.icon }}</span>{{ tab.label }}
                    </Link>
                    <span v-else class="grid h-16 place-items-center text-xs text-ink-muted/40" aria-disabled="true">
                        <span class="text-xl leading-none" aria-hidden="true">{{ tab.icon }}</span>{{ tab.label }}
                    </span>
                </li>
            </ul>
        </nav>
    </div>
</template>
