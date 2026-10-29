<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Toast from '@/Components/ui/Toast.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps<{ title: string }>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const menuOpen = ref(false);

const links = computed(() => [
    { label: 'Dashboard', href: '/admin' },
    { label: 'Clubs', href: '/admin/clubs' },
    { label: 'Staff', href: '/admin/staff' },
    { label: 'All members', href: '/admin/members' },
    { label: 'Fees & drives', href: '/admin/fees' },
    { label: 'Venue requests', href: '/admin/venues' },
    { label: 'AGM', href: '/admin/agm' },
    { label: 'Club finance', href: '/admin/finance' },
    { label: 'Moderation', href: '/admin/moderation' },
    { label: 'Settings', href: '/admin/settings' },
    ...(user.value?.roles.includes('superadmin')
        ? [
              { label: 'Roles', href: '/admin/roles' },
              { label: 'camsys sync', href: '/admin/sync' },
              { label: 'Audit log', href: '/admin/audit' },
          ]
        : []),
    { label: '← Back to Campus Hub', href: '/clubs' },
]);
const isActive = (href: string): boolean => (href === '/admin' ? page.url === '/admin' : page.url.startsWith(href));
</script>

<template>
    <div class="min-h-dvh md:grid md:grid-cols-[15rem_1fr]">
        <Toast />
        <aside class="hidden border-r border-line bg-surface md:block">
            <div class="sticky top-0 grid gap-6 p-5">
                <Link href="/clubs"><img src="/brand/QIU_Student_Activity_Black.png" alt="QIU Student Activity" class="h-10 w-auto" /></Link>
                <nav class="grid gap-1 text-sm">
                    <Link
                        v-for="link in links"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-control px-3 py-2"
                        :class="isActive(link.href) ? 'bg-surface-muted font-medium' : 'text-ink-muted hover:bg-surface-muted'"
                    >
                        {{ link.label }}
                    </Link>
                </nav>
            </div>
        </aside>

        <div>
            <header class="sticky top-0 z-30 flex h-14 items-center justify-between gap-3 border-b border-line bg-surface/90 px-4 backdrop-blur">
                <button type="button" class="md:hidden" aria-label="Open menu" @click="menuOpen = true">☰</button>
                <h1 class="truncate text-base font-semibold">{{ title }}</h1>
                <Link v-if="user" href="/me"><Avatar :src="user.avatar_url" :name="user.name" size="sm" /></Link>
            </header>
            <main class="mx-auto max-w-6xl px-4 py-6">
                <slot />
            </main>
        </div>

        <Drawer v-model:open="menuOpen" title="SLD Admin" side="left">
            <nav class="grid gap-1 text-sm">
                <Link v-for="link in links" :key="link.href" :href="link.href" class="rounded-control px-3 py-2 hover:bg-surface-muted" @click="menuOpen = false">
                    {{ link.label }}
                </Link>
            </nav>
        </Drawer>
    </div>
</template>
