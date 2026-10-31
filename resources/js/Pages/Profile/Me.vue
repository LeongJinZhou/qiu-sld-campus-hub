<script setup lang="ts">
import PhotoEditor from '@/Components/profile/PhotoEditor.vue';
import ProfileView from '@/Components/profile/ProfileView.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon from '@/Components/ui/Icon.vue';
import Input from '@/Components/ui/Input.vue';
import Modal from '@/Components/ui/Modal.vue';
import QrCode from '@/Components/ui/QrCode.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime } from '@/lib/format';
import type { ProfileData } from '@/types';
import { Deferred, Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    profile: ProfileData;
    account: { email: string; student_number: string | null; status: string | null; has_photo_override: boolean; has_cover: boolean; personal_email: string | null; hidden_sections: string[] };
    hideableSections: string[];
    saved?: { id: number; url: string; image: string | null; caption: string | null }[];
    publicUrl: string;
    qrSvg: string;
    certificates: { kind: string; source_id: number; title: string; statement: string; date: string; code: string | null }[];
    upcoming: { name: string; slug: string; club: string; starts_at: string; venue: string | null; poster_url: string | null; needs_payment: boolean; needs_indemnity: boolean }[];
}>();

// First steps for a new student; the card disappears once every step is done.
const steps = computed(() => [
    { label: 'Add a profile photo', done: props.account.has_photo_override || !!props.profile.photo_url, action: () => (photo.value = true) },
    { label: 'Join a club', done: (props.profile.clubs?.length ?? 0) > 0, href: '/search?type=clubs' },
    { label: 'Sign up for an event', done: props.upcoming.length > 0 || (props.profile.events?.length ?? 0) > 0, href: '/feed' },
    { label: 'Link a personal email (keeps your login after graduation)', done: !!props.account.personal_email, action: () => (editing.value = true) },
]);
const stepsDone = computed(() => steps.value.filter((s) => s.done).length);

const nav = computed(() => usePage().props.nav);
const editing = ref(false);
const photo = ref(false);
const cover = ref(false);
const qr = ref(false);
const copied = ref(false);

const form = useForm({ hidden_sections: [...props.account.hidden_sections], personal_email: props.account.personal_email ?? '' });
const toggles = props.hideableSections.map((section) => ({
    section,
    model: computed({
        get: () => !form.hidden_sections.includes(section),
        set: (show: boolean) => (form.hidden_sections = show ? form.hidden_sections.filter((s) => s !== section) : [...form.hidden_sections, section]),
    }),
}));
const labels: Record<string, string> = { clubs: 'Clubs', positions: 'Positions', events: 'Events attended', cca: 'CCA record' };
const save = (): void => form.post('/me', { preserveScroll: true, onSuccess: () => (editing.value = false) });

async function share(): Promise<void> {
    if (navigator.share) await navigator.share({ title: props.profile.name, url: props.publicUrl }).catch(() => undefined);
    else {
        await navigator.clipboard.writeText(props.publicUrl);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    }
}
</script>

<template>
    <Head title="My profile" />
    <AppLayout>
        <section v-if="account.student_number && stepsDone < steps.length" class="mb-4 rounded-card border border-line bg-surface p-4 shadow-card">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="font-semibold">Make the most of Campus Hub</h2>
                <span class="text-xs font-medium text-ink-muted">{{ stepsDone }}/{{ steps.length }} done</span>
            </div>
            <div class="mb-3 h-1.5 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full bg-gradient-to-r from-brand-orange to-brand-magenta" :style="{ width: `${(stepsDone / steps.length) * 100}%` }" /></div>
            <ul class="grid gap-1 text-sm">
                <li v-for="s in steps" :key="s.label">
                    <component :is="s.href && !s.done ? Link : 'button'" :href="s.href" type="button" class="flex w-full items-center gap-3 rounded-control px-2 py-2 text-left" :class="s.done ? 'text-ink-muted' : 'hover:bg-surface-muted'" :disabled="s.done" @click="!s.done && s.action?.()">
                        <span class="grid size-5 shrink-0 place-items-center rounded-full text-[11px]" :class="s.done ? 'bg-success text-white' : 'border border-line'">{{ s.done ? '✓' : '' }}</span>
                        <span :class="s.done ? 'line-through' : 'font-medium'">{{ s.label }}</span>
                    </component>
                </li>
            </ul>
        </section>

        <ProfileView :profile="profile" :extra-tabs="[{ key: 'upcoming', label: 'Coming up', icon: 'clock' }, { key: 'certificates', label: 'Certificates', icon: 'check' }, { key: 'saved', label: 'Saved', icon: 'bookmark' }]">
            <template #cover-actions>
                <button type="button" class="flex items-center gap-1.5 rounded-full bg-black/50 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur hover:bg-black/60" @click="cover = true">
                    <Icon name="camera" :size="14" /> {{ account.has_cover ? 'Edit cover' : 'Add cover' }}
                </button>
            </template>
            <template #avatar-action>
                <button type="button" class="absolute bottom-1 right-1 grid size-9 place-items-center rounded-full border-2 border-surface bg-ink text-white shadow-pop" aria-label="Change photo" @click="photo = true">
                    <Icon name="camera" :size="18" />
                </button>
            </template>
            <template #actions>
                <Button variant="secondary" size="sm" @click="editing = true"><Icon name="edit" :size="16" /> Edit profile</Button>
                <Button variant="secondary" size="sm" @click="qr = true"><Icon name="qr" :size="16" /> My QR</Button>
                <Button variant="secondary" size="sm" @click="share"><Icon name="send" :size="16" /> {{ copied ? 'Copied!' : 'Share' }}</Button>
            </template>
            <template #meta>
                <p class="text-xs text-ink-muted">{{ [account.student_number, account.email].filter(Boolean).join(" · ") }}</p>
            </template>
            <template #tab-upcoming>
                <EmptyState v-if="!upcoming.length" title="Nothing coming up" description="Events you register for show here, with anything you still need to do." />
                <ul class="grid gap-2">
                    <li v-for="e in upcoming" :key="e.slug">
                        <Link :href="e.needs_payment ? `/events/${e.slug}/pay` : e.needs_indemnity ? `/events/${e.slug}/indemnity` : `/events/${e.slug}`" class="flex items-center gap-3 rounded-card border border-line bg-surface p-3 transition hover:shadow-card">
                            <img v-if="e.poster_url" :src="e.poster_url" alt="" class="size-14 shrink-0 rounded-control object-cover" />
                            <span v-else class="grid size-14 shrink-0 place-items-center rounded-control bg-gradient-to-br from-brand-cyan to-brand-blue text-white"><Icon name="calendar" :size="22" /></span>
                            <span class="grid min-w-0 flex-1 text-sm">
                                <strong class="truncate">{{ e.name }}</strong>
                                <span class="truncate text-xs text-ink-muted">{{ dateTime(e.starts_at) }}<template v-if="e.venue"> · {{ e.venue }}</template> · {{ e.club }}</span>
                            </span>
                            <Badge v-if="e.needs_payment" tone="warning">Pay now</Badge>
                            <Badge v-else-if="e.needs_indemnity" tone="warning">Sign indemnity</Badge>
                            <Badge v-else tone="success">Ready</Badge>
                        </Link>
                    </li>
                </ul>
            </template>
            <template #tab-certificates>
                <EmptyState v-if="!certificates.length" title="No certificates yet" description="Committee positions and events you attend (clock in and out) appear here as downloadable certificates." />
                <ul class="grid gap-2">
                    <li v-for="c in certificates" :key="`${c.kind}-${c.source_id}`" class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-surface p-4">
                        <span class="min-w-0 flex-1">
                            <strong class="block">{{ c.title }}</strong>
                            <span class="text-sm text-ink-muted">You {{ c.statement }}</span>
                            <a v-if="c.code" :href="`/certificates/${c.code}`" class="block text-xs text-brand-blue">Verification code {{ c.code }}</a>
                        </span>
                        <a :href="`/me/certificates/${c.kind}/${c.source_id}`"><Button size="sm" variant="secondary">Download PDF</Button></a>
                    </li>
                </ul>
            </template>
            <template #tab-saved>
                <Deferred data="saved">
                    <template #fallback><p class="text-sm text-ink-muted">Loading…</p></template>
                    <EmptyState v-if="!saved?.length" title="No saved posts" description="Tap the bookmark on any post to keep it here." />
                    <div class="grid grid-cols-3 gap-1">
                        <Link v-for="p in saved" :key="p.id" :href="p.url" class="aspect-square overflow-hidden bg-surface-muted">
                            <img v-if="p.image" :src="p.image" :alt="p.caption ?? ''" class="size-full object-cover" />
                            <span v-else class="grid size-full place-items-center p-2 text-center text-xs">{{ p.caption }}</span>
                        </Link>
                    </div>
                </Deferred>
            </template>
        </ProfileView>

        <!-- Phones have no sidebar, so account actions sit at the bottom of the profile. -->
        <section class="mt-6 grid gap-2 md:hidden">
            <Link href="/my-clubs" class="flex items-center gap-3 rounded-card border border-line bg-surface px-4 py-3 text-sm font-medium shadow-card"><Icon name="users" :size="18" /> My clubs</Link>
            <Link v-if="nav?.isAdmin" href="/admin" class="flex items-center gap-3 rounded-card border border-line bg-surface px-4 py-3 text-sm font-medium shadow-card"><Icon name="shield" :size="18" /> SLD Admin</Link>
            <button type="button" class="flex items-center gap-3 rounded-card border border-line bg-surface px-4 py-3 text-left text-sm font-medium text-danger shadow-card" @click="router.post('/logout')"><Icon name="logout" :size="18" /> Sign out</button>
        </section>

        <PhotoEditor v-model:open="photo" :current="profile.photo_url" :name="profile.name" :has-override="account.has_photo_override" />
        <PhotoEditor v-model:open="cover" kind="cover" :current="profile.cover_url" :name="profile.name" :has-override="account.has_cover" />

        <Modal v-model:open="qr" title="My QIU profile QR">
            <div class="grid justify-items-center gap-3">
                <QrCode :svg="qrSvg" label="Anyone who scans sees your public profile" download-name="my-qiu-profile" />
                <a :href="publicUrl" class="text-sm font-medium text-brand-blue">Open public profile</a>
            </div>
        </Modal>

        <Drawer v-model:open="editing" title="Edit profile">
            <form class="grid gap-5" @submit.prevent="save">
                <Card>
                    <p class="mb-3 text-sm font-medium">Show on my public profile</p>
                    <div class="grid gap-2">
                        <Checkbox v-for="t in toggles" :key="t.section" v-model="t.model.value" :label="labels[t.section] ?? t.section" />
                    </div>
                </Card>
                <Input v-model="form.personal_email" type="email" label="Personal email" hint="Keep signing in as alumni after graduation." :error="form.errors.personal_email" />
                <p class="text-xs text-ink-muted">Your name, student ID and programme come from university records and can't be edited here.</p>
                <Button type="submit" :loading="form.processing">Save</Button>
            </form>
        </Drawer>
    </AppLayout>
</template>
