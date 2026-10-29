<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Icon, { type IconName } from '@/Components/ui/Icon.vue';
import Toast from '@/Components/ui/Toast.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

withDefaults(defineProps<{ wide?: boolean }>(), { wide: false });

const page = usePage();
const user = computed(() => page.props.auth.user);
const nav = computed(() => page.props.nav);

interface NavItem {
    label: string;
    icon: IconName;
    href: string;
    match: string;
    badge?: number;
}

// Mobile tab bar: Feed, Search, Create, Notifications, Profile.
const items = computed<NavItem[]>(() => [
    { label: 'Feed', icon: 'home', href: '/feed', match: '/feed' },
    { label: 'Clubs', icon: 'search', href: '/clubs', match: '/clubs' },
    ...(nav.value?.canPublish ? [{ label: 'Create', icon: 'plus' as const, href: '/feed?create=1', match: '/feed?create' }] : []),
    { label: 'Notifications', icon: 'bell', href: '/notifications', match: '/notifications', badge: nav.value?.unread ?? 0 },
    ...(nav.value?.isStaff ? [{ label: 'Approvals', icon: 'check' as const, href: '/approvals', match: '/approvals' }] : []),
    { label: 'Profile', icon: 'user', href: '/me', match: '/me' },
]);
const mobileItems = computed(() => items.value.filter((i) => i.label !== 'Approvals'));
const isActive = (item: NavItem): boolean => page.url === item.href || (page.url.startsWith(item.match) && !page.url.startsWith('/feed?create'));
const logout = (): void => router.post('/logout');
</script>

<template>
    <div class="min-h-dvh bg-surface-muted md:grid md:grid-cols-[4.5rem_1fr] lg:grid-cols-[15rem_1fr]">
        <Toast />

        <!-- Desktop / tablet sidebar -->
        <aside class="sticky top-0 hidden h-dvh flex-col border-r border-line bg-surface px-3 py-6 md:flex">
            <Link href="/feed" class="mb-8 flex items-center gap-3 px-2">
                <img src="/brand/QIU_Student_Activity_Black.png" alt="QIU Student Activity" class="h-9 w-auto" />
                <span class="hidden text-lg font-bold lg:inline"><span class="text-gradient-warm">Campus</span> <span class="text-gradient-cool">Hub</span></span>
            </Link>
            <nav class="grid gap-1" aria-label="Primary">
                <Link
                    v-for="item in items"
                    :key="item.label"
                    :href="item.href"
                    class="group relative flex items-center gap-4 rounded-control px-3 py-2.5 text-[15px] transition hover:bg-surface-muted"
                    :class="isActive(item) ? 'font-semibold text-ink' : 'text-ink/80'"
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    <span class="relative">
                        <Icon :name="item.icon" :stroke-width="isActive(item) ? 2.4 : 1.8" class="transition group-hover:scale-105" />
                        <span v-if="item.badge" class="absolute -right-1.5 -top-1.5 grid min-w-4 place-items-center rounded-full bg-brand-magenta px-1 text-[10px] font-bold text-white">{{ item.badge > 9 ? '9+' : item.badge }}</span>
                    </span>
                    <span class="hidden lg:inline">{{ item.label }}</span>
                </Link>
            </nav>
            <div class="mt-auto grid gap-1">
                <Link v-if="nav?.isAdmin" href="/admin" class="flex items-center gap-4 rounded-control px-3 py-2.5 text-[15px] text-ink/80 hover:bg-surface-muted">
                    <Icon name="shield" /><span class="hidden lg:inline">SLD Admin</span>
                </Link>
                <button type="button" class="flex items-center gap-4 rounded-control px-3 py-2.5 text-left text-[15px] text-ink/80 hover:bg-surface-muted" @click="logout">
                    <Icon name="logout" /><span class="hidden lg:inline">Sign out</span>
                </button>
                <Link v-if="user" href="/me" class="mt-2 flex items-center gap-3 rounded-control px-2 py-2 hover:bg-surface-muted">
                    <Avatar :src="user.avatar_url" :name="user.name" size="sm" />
                    <span class="hidden min-w-0 lg:grid">
                        <span class="truncate text-sm font-semibold">{{ user.name }}</span>
                        <span class="truncate text-xs text-ink-muted">{{ user.email }}</span>
                    </span>
                </Link>
            </div>
        </aside>

        <div class="min-w-0 pb-20 md:pb-0">
            <!-- Mobile top bar -->
            <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-line bg-surface/90 px-4 backdrop-blur md:hidden">
                <Link href="/feed" class="flex items-center gap-2">
                    <img src="/brand/QIU_Student_Activity_Black.png" alt="QIU Student Activity" class="h-8 w-auto" />
                    <span class="font-bold"><span class="text-gradient-warm">Campus</span> <span class="text-gradient-cool">Hub</span></span>
                </Link>
                <div class="flex items-center gap-4">
                    <Link v-if="nav?.isStaff" href="/approvals" aria-label="Approvals"><Icon name="check" /></Link>
                    <Link v-if="nav?.isAdmin" href="/admin" aria-label="SLD Admin"><Icon name="shield" /></Link>
                </div>
            </header>

            <main class="mx-auto px-4 py-6 md:px-8" :class="wide ? 'max-w-6xl' : 'max-w-4xl'">
                <slot />
            </main>
        </div>

        <!-- Mobile bottom tab bar -->
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 backdrop-blur md:hidden" aria-label="Primary">
            <ul class="flex justify-around">
                <li v-for="item in mobileItems" :key="item.label">
                    <Link :href="item.href" class="relative grid h-14 w-14 place-items-center" :class="isActive(item) ? 'text-ink' : 'text-ink/60'" :aria-label="item.label">
                        <Avatar v-if="item.icon === 'user' && user" :src="user.avatar_url" :name="user.name" size="xs" :class="isActive(item) ? 'ring-2 ring-ink rounded-full' : ''" />
                        <Icon v-else :name="item.icon" :size="26" :stroke-width="isActive(item) ? 2.4 : 1.8" />
                        <span v-if="item.badge" class="absolute right-2.5 top-2.5 size-2.5 rounded-full bg-brand-magenta ring-2 ring-surface" />
                    </Link>
                </li>
            </ul>
        </nav>
    </div>
</template>
