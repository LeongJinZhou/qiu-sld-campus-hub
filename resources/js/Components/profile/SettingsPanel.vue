<script setup lang="ts">
import Icon, { type IconName } from '@/Components/ui/Icon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/** Profile → Settings: theme, replay the guides, notification settings, then Sign out (always last). */
type Theme = 'light' | 'dark' | 'system';
const page = usePage();
const user = computed(() => page.props.auth.user);
const theme = ref<Theme>((user.value?.theme as Theme | undefined) ?? 'system');

function setTheme(value: Theme): void {
    theme.value = value;
    document.documentElement.dataset.theme = value; // saved to the account, never to the device
    router.put('/me/theme', { theme: value }, { preserveScroll: true, preserveState: true, only: ['auth'] });
}

// Replaying a guide marks it unseen, then opens the page where it runs.
function replay(tour: 'student' | 'committee'): void {
    const to = tour === 'committee' && user.value?.committee_club ? `/clubs/${user.value.committee_club.slug}?tour=committee` : '/feed?tour=student';
    router.post('/me/tours', { tour, seen: false }, { onSuccess: () => router.visit(to) });
}
const options: { value: Theme; label: string; icon: IconName }[] = [
    { value: 'light', label: 'Light', icon: 'sun' },
    { value: 'dark', label: 'Dark', icon: 'moon' },
    { value: 'system', label: 'System', icon: 'monitor' },
];
</script>

<template>
    <section class="grid gap-3" aria-labelledby="settings-title">
        <h2 id="settings-title" class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Settings</h2>
        <div class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card">
            <div class="grid gap-2">
                <p class="text-sm font-medium">Appearance</p>
                <div class="grid grid-cols-3 gap-1 rounded-control bg-surface-muted p-1" role="radiogroup" aria-label="Appearance">
                    <button
                        v-for="o in options"
                        :key="o.value"
                        type="button"
                        role="radio"
                        :aria-checked="theme === o.value"
                        class="flex h-9 items-center justify-center gap-1.5 rounded-[0.6rem] text-sm font-medium transition"
                        :class="theme === o.value ? 'bg-surface text-ink shadow-card' : 'text-ink-muted hover:text-ink'"
                        @click="setTheme(o.value)"
                    >
                        <Icon :name="o.icon" :size="15" />{{ o.label }}
                    </button>
                </div>
                <p class="text-xs text-ink-muted">System follows your phone or computer.</p>
            </div>
            <div class="grid gap-1 border-t border-line pt-3">
                <button type="button" class="flex items-center gap-3 rounded-control px-1 py-2 text-left text-sm font-medium hover:bg-surface-muted" @click="replay('student')">
                    <Icon name="sparkles" :size="18" class="text-brand-magenta" /> Show me around again
                </button>
                <button
                    v-if="user?.committee_club"
                    type="button"
                    class="flex items-center gap-3 rounded-control px-1 py-2 text-left text-sm font-medium hover:bg-surface-muted"
                    @click="replay('committee')"
                >
                    <Icon name="users" :size="18" class="text-brand-blue" /> Committee guide · {{ user.committee_club.name }}
                </button>
                <Link href="/notifications" class="flex items-center gap-3 rounded-control px-1 py-2 text-sm font-medium hover:bg-surface-muted">
                    <Icon name="bell" :size="18" class="text-ink-muted" /> Notification settings
                </Link>
            </div>
        </div>
        <button
            type="button"
            class="flex items-center gap-3 rounded-card border border-line bg-surface px-4 py-3 text-left text-sm font-medium text-danger shadow-card"
            @click="router.post('/logout')"
        >
            <Icon name="logout" :size="18" /> Sign out
        </button>
    </section>
</template>
