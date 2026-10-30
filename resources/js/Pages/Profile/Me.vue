<script setup lang="ts">
import PhotoEditor from '@/Components/profile/PhotoEditor.vue';
import ProfileView from '@/Components/profile/ProfileView.vue';
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
import type { ProfileData } from '@/types';
import { Deferred, Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    profile: ProfileData;
    account: { email: string; student_number: string | null; status: string | null; has_photo_override: boolean; personal_email: string | null; hidden_sections: string[] };
    hideableSections: string[];
    saved?: { id: number; url: string; image: string | null; caption: string | null }[];
    publicUrl: string;
    qrSvg: string;
    certificates: { kind: string; source_id: number; title: string; statement: string; date: string; code: string | null }[];
}>();

const editing = ref(false);
const photo = ref(false);
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
        <ProfileView :profile="profile" :extra-tabs="[{ key: 'certificates', label: 'Certificates', icon: 'check' }, { key: 'saved', label: 'Saved', icon: 'bookmark' }]">
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

        <PhotoEditor v-model:open="photo" :current="profile.photo_url" :name="profile.name" :has-override="account.has_photo_override" />

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
