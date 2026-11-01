<script setup lang="ts">
import Checkbox from '@/Components/ui/Checkbox.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon, { type IconName } from '@/Components/ui/Icon.vue';
import Modal from '@/Components/ui/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    actions: { key: string; kind: string; title: string; body: string; url: string; since: string | null }[];
    items: { id: string; title: string; body: string; url: string | null; category: string; group: string; at: string | null; unread: boolean }[];
    hasMore: boolean;
    limit: number;
    group: string | null;
    groups: { key: string; label: string; count: number }[];
    total: number;
    mutable: Record<string, string>;
    muted: string[];
}>();

// Live: new notifications and finished actions appear without reloading.
usePoll(15_000, { only: ['actions', 'items', 'groups', 'total', 'hasMore'] });

// Filter by group, load more, clear one or many. "Action needed" is never cleared: it goes when the task is done.
const show = (group: string | null): void => router.get('/notifications', group ? { group } : {}, { preserveScroll: true, preserveState: true, replace: true });
const more = (): void =>
    router.get('/notifications', { ...(props.group ? { group: props.group } : {}), limit: props.limit + 30 }, { preserveScroll: true, preserveState: true, replace: true });
const removing = ref<Set<string>>(new Set());
const clearOne = (id: string): void => {
    removing.value.add(id);
    router.delete(`/notifications/${id}`, { preserveScroll: true, preserveState: true, onFinish: () => removing.value.delete(id) });
};
const clearAll = (): void => {
    const what = props.group ? (props.groups.find((g) => g.key === props.group)?.label ?? 'these') : 'all';
    if (confirm(`Clear ${what} notifications? Action needed items stay until they are done.`))
        router.delete('/notifications', { data: props.group ? { group: props.group } : {}, preserveScroll: true });
};

const icons: Record<string, IconName> = {
    payment: 'send',
    handover: 'qr',
    review: 'check',
    indemnity: 'file',
    approval: 'check',
    likes: 'heart',
    comments: 'message',
    saves: 'bookmark',
    follows: 'users',
    club_posts: 'calendar',
};

const settings = ref(false);
const mutes = ref<string[]>([...props.muted]);
const toggle = (key: string, on: boolean): void => {
    mutes.value = on ? mutes.value.filter((k) => k !== key) : [...mutes.value, key];
};
const saveMutes = (): void => router.put('/notifications/mutes', { muted: mutes.value }, { preserveScroll: true, onSuccess: () => (settings.value = false) });

// Phone notifications (used for "you left this half-way" reminders) need the person's permission once.
const permission = ref<NotificationPermission | 'unsupported'>('Notification' in window ? Notification.permission : 'unsupported');
const askPermission = async (): Promise<void> => {
    permission.value = await Notification.requestPermission();
};
</script>

<template>
    <Head title="Notifications" />
    <AppLayout>
        <div class="mx-auto grid max-w-xl gap-5">
            <header class="flex items-center justify-between gap-3">
                <h1 class="text-xl font-bold">Notifications</h1>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-control px-3 py-1.5 text-sm font-medium text-ink-muted hover:bg-surface-muted"
                    @click="settings = true"
                >
                    <Icon name="settings" :size="16" /> Settings
                </button>
            </header>

            <div v-if="permission === 'default'" class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-brand-cyan/40 bg-brand-cyan/5 p-4 text-sm">
                <span>Get a phone notification when you leave a payment or form half-way.</span>
                <Button size="sm" @click="askPermission">Turn on</Button>
            </div>

            <section v-if="actions.length" class="grid gap-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Action needed</h2>
                <Link v-for="a in actions" :key="a.key" :href="a.url" class="flex gap-3 rounded-card border-2 border-warning/40 bg-warning/5 p-4 transition hover:shadow-card">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-warning text-white"><Icon :name="icons[a.kind] ?? 'bell'" :size="18" /></span>
                    <span class="grid min-w-0 flex-1 gap-0.5">
                        <strong class="text-sm">{{ a.title }}</strong>
                        <span class="text-sm text-ink-muted">{{ a.body }}</span>
                    </span>
                    <span class="self-center text-sm font-semibold text-warning">Continue</span>
                </Link>
            </section>

            <section class="grid gap-3">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Updates</h2>
                    <button v-if="items.length" type="button" class="text-sm font-medium text-danger hover:underline" @click="clearAll">Clear {{ group ? 'these' : 'all' }}</button>
                </div>
                <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none]">
                    <button
                        type="button"
                        class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                        :class="!group ? 'border-ink bg-ink text-white' : 'border-line bg-surface text-ink-muted hover:bg-surface-muted'"
                        @click="show(null)"
                    >
                        All {{ total }}
                    </button>
                    <button
                        v-for="g in groups.filter((x) => x.count > 0)"
                        :key="g.key"
                        type="button"
                        class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                        :class="group === g.key ? 'border-ink bg-ink text-white' : 'border-line bg-surface text-ink-muted hover:bg-surface-muted'"
                        @click="show(g.key)"
                    >
                        {{ g.label }} {{ g.count }}
                    </button>
                </div>
                <EmptyState v-if="!items.length && !actions.length" title="You're all caught up" description="Payments, approvals, club activity and reminders show up here." />
                <TransitionGroup tag="ul" name="list" class="relative grid gap-2">
                    <li v-for="n in items" :key="n.id" class="group relative" :class="removing.has(n.id) ? 'opacity-40' : ''">
                        <component
                            :is="n.url ? Link : 'div'"
                            :href="n.url ?? undefined"
                            class="flex gap-3 rounded-card border border-line bg-surface p-4 pr-11 transition hover:shadow-card"
                            :class="n.unread ? 'border-brand-magenta/40 bg-brand-magenta/5' : ''"
                        >
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-orange to-brand-magenta text-white"
                                ><Icon :name="icons[n.category] ?? 'bell'" :size="18"
                            /></span>
                            <span class="grid min-w-0 gap-0.5">
                                <strong class="text-sm">{{ n.title }}</strong>
                                <span class="break-words text-sm text-ink-muted">{{ n.body }}</span>
                                <span class="text-xs text-ink-muted">{{ n.at }}</span>
                            </span>
                        </component>
                        <button
                            type="button"
                            class="absolute right-2 top-2 grid size-8 place-items-center rounded-full text-ink-muted transition hover:bg-surface-muted hover:text-danger"
                            :aria-label="`Clear: ${n.title}`"
                            @click="clearOne(n.id)"
                        >
                            <Icon name="x" :size="16" />
                        </button>
                    </li>
                </TransitionGroup>
                <Button v-if="hasMore" variant="secondary" block @click="more">Show older</Button>
            </section>
        </div>

        <Modal v-model:open="settings" title="Notification settings">
            <div class="grid gap-4">
                <p class="text-sm text-ink-muted">Choose which club activity you hear about. Payments, approvals and reminders always come through.</p>
                <div class="grid gap-2">
                    <Checkbox
                        v-for="(label, key) in mutable"
                        :key="key"
                        :model-value="!mutes.includes(key)"
                        :label="label"
                        @update:model-value="(on: boolean) => toggle(key, on)"
                    />
                </div>
                <Button @click="saveMutes">Save</Button>
            </div>
        </Modal>
    </AppLayout>
</template>
