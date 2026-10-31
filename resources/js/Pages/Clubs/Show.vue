<script setup lang="ts">
import ClubHeader from '@/Components/club/ClubHeader.vue';
import CommitteeBoard from '@/Components/club/CommitteeBoard.vue';
import SessionSelector from '@/Components/club/SessionSelector.vue';
import CreatePost from '@/Components/feed/CreatePost.vue';
import StoryViewer from '@/Components/feed/StoryViewer.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import Input from '@/Components/ui/Input.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime, rm } from '@/lib/format';
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import type { ApprovalProgress, MemberCard, SessionOption } from '@/types';
import { Deferred, Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Story {
    id: number;
    url: string;
    at: string | null;
}

const props = defineProps<{
    club: { id: number; slug: string; name: string; logo_url: string | null; category: string | null; established_year: number | null; bio: string | null; instagram_handle: string | null; qr_svg: string; status: string };
    session: string;
    isCurrentSession: boolean;
    sessions: SessionOption[];
    committee: MemberCard[];
    advisors: MemberCard[];
    missingCompulsory: string[];
    stories: Story[];
    highlights: { id: number; title: string; items: string[] }[];
    counts: { followers: number; events: number; members: number; alumni: number };
    memberWall: { name: string; photo_url: string | null; profile_url: string }[];
    upcoming: { slug: string; name: string; starts_at: string; venue: string | null; poster_url: string | null; fee_sen: number; registration_open: boolean }[];
    membership: { status: string | null; fee_sen: number | null; drive: string | null; can_join: boolean };
    following: boolean;
    tab: string;
    posts?: { id: number; url: string; image: string | null; caption: string | null; count: number }[];
    albums?: { id: number; title: string; session: string; photos: string[] }[];
    events?: { id: number; slug: string; name: string; starts_at: string; venue: string | null; status: string; poster_url: string | null }[];
    faqs: { id: number; question: string; answer: string }[];
    agm: { days_left: number; deadline: string } | null;
    sldRequests: { id: number; subject: string; body: string; due_on: string; overdue: boolean; reply: string | null }[];
    documents?: { id: number; type: string; event: string | null; status: string; updated: string | null; progress: ApprovalProgress }[] | null;
    can: { manage: boolean; publish: boolean; createEvent: boolean; internal: boolean; treasure: boolean };
}>();

const tabs = computed(() => [
    { key: 'committee', label: 'Committee' },
    { key: 'feed', label: 'Feed' },
    { key: 'gallery', label: 'Gallery' },
    { key: 'events', label: 'Events' },
    { key: 'faq', label: 'FAQ' },
    ...(props.can.internal ? [{ key: 'documents', label: 'Documents' }] : []),
]);
const replies = ref<Record<number, string>>({});
const tab = ref(tabs.value.some((t) => t.key === props.tab) ? props.tab : 'committee');

const viewing = ref<{ name: string; logo_url: string | null; stories: Story[] } | null>(null);
const openStories = (): void => {
    viewing.value = { name: props.club.name, logo_url: props.club.logo_url, stories: props.stories };
};
const openHighlight = (h: { title: string; items: string[] }): void => {
    viewing.value = { name: h.title, logo_url: props.club.logo_url, stories: h.items.map((url, i) => ({ id: -i - 1, url, at: null })) };
};
const highlight = (storyId: number): void => {
    const title = prompt('Add to which highlight? (e.g. Events, Recruitment)');
    if (title) router.post(`/clubs/${props.club.slug}/stories/${storyId}/highlight`, { title }, { preserveScroll: true });
};

const creating = ref(false);
const album = useForm({ title: '', photos: [] as File[] });
const faq = useForm({ question: '', answer: '' });
const month = (iso: string): string => new Date(iso).toLocaleString('en-MY', { month: 'short', timeZone: 'Asia/Kuala_Lumpur' });
const day = (iso: string): string => new Date(iso).toLocaleString('en-MY', { day: 'numeric', timeZone: 'Asia/Kuala_Lumpur' });
const statusTone = (s: string) => (s === 'approved' ? 'success' : s === 'returned' ? 'warning' : s === 'rejected' ? 'danger' : 'neutral');
</script>

<template>
    <Head :title="club.name" />
    <AppLayout>
        <div class="grid gap-6">
            <ClubHeader :club="club" :membership="membership" :counts="{ committee: committee.length, ...counts }" :following="following" :has-stories="stories.length > 0" @open-stories="openStories" />

            <div v-if="highlights.length || can.publish" class="flex gap-4 overflow-x-auto pb-1">
                <button v-for="h in highlights" :key="h.id" type="button" class="grid w-16 shrink-0 justify-items-center gap-1" @click="openHighlight(h)">
                    <img :src="h.items[0]" alt="" class="size-14 rounded-full border border-line object-cover p-0.5" />
                    <span class="w-full truncate text-center text-[11px]">{{ h.title }}</span>
                </button>
            </div>

            <p v-if="membership.drive" class="rounded-card bg-gradient-to-r from-brand-yellow/30 to-brand-magenta/20 px-4 py-3 text-sm font-medium">🎉 {{ membership.drive }} is on — join {{ club.name }} now!</p>

            <section v-for="r in sldRequests" :key="r.id" class="grid gap-2 rounded-card border p-4 text-sm" :class="r.reply ? 'border-line bg-surface' : r.overdue ? 'border-danger/40 bg-danger/5' : 'border-warning/40 bg-warning/5'">
                <p class="flex flex-wrap items-center justify-between gap-2">
                    <strong>SLD request: {{ r.subject }}</strong>
                    <Badge v-if="r.reply" tone="success">Replied</Badge>
                    <Badge v-else :tone="r.overdue ? 'danger' : 'warning'">{{ r.overdue ? 'Overdue' : 'Reply by' }} {{ r.due_on }}</Badge>
                </p>
                <p class="whitespace-pre-line">{{ r.body }}</p>
                <p v-if="r.reply" class="whitespace-pre-line text-ink-muted">Your reply: {{ r.reply }}</p>
                <form v-else class="grid gap-2" @submit.prevent="router.post(`/sld-requests/${r.id}/reply`, { reply: replies[r.id] ?? '' }, { preserveScroll: true })">
                    <Textarea v-model="replies[r.id]" :rows="2" placeholder="Reply to SLD" />
                    <div><Button type="submit" size="sm">Send reply</Button></div>
                </form>
            </section>

            <div v-if="can.publish || can.createEvent || can.internal" class="flex flex-wrap gap-2">
                <Link v-if="can.createEvent" :href="`/clubs/${club.slug}/events/create`"><Button size="sm">＋ New event proposal</Button></Link>
                <Button v-if="can.publish" size="sm" variant="secondary" @click="creating = true">＋ Post / story</Button>
                <Link v-if="can.internal" :href="`/clubs/${club.slug}/members`"><Button size="sm" variant="secondary">Members</Button></Link>
                <Link v-if="can.treasure" :href="`/clubs/${club.slug}/finance`"><Button size="sm" variant="secondary">Finance</Button></Link>
                <Link v-if="can.treasure" :href="`/clubs/${club.slug}/payments/review`"><Button size="sm" variant="secondary">Payment review</Button></Link>
                <Link v-if="can.internal" :href="`/clubs/${club.slug}/meetings`"><Button size="sm" variant="secondary">Meetings</Button></Link>
                <Link v-if="can.internal" :href="`/clubs/${club.slug}/agm`"><Button size="sm" :variant="agm ? 'primary' : 'secondary'">AGM<template v-if="agm"> · {{ agm.days_left }} days left</template></Button></Link>
                <Link v-if="can.internal" :href="`/clubs/${club.slug}/venues`"><Button size="sm" variant="secondary">Venues</Button></Link>
            </div>

            <p v-if="missingCompulsory.length" class="rounded-card bg-warning/10 px-4 py-3 text-sm text-warning">
                Compulsory positions vacant: {{ missingCompulsory.join(', ') }}. Documents can't be submitted until they are filled.
                <Link v-if="can.manage" :href="`/admin/clubs/${club.slug}/edit`" class="font-semibold underline">Assign</Link>
            </p>

            <Tabs v-model="tab" :tabs="tabs">
                <template #committee>
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <SessionSelector :sessions="sessions" :selected="session" :url="`/clubs/${club.slug}`" />
                        <Badge v-if="!isCurrentSession">Past session · read-only</Badge>
                    </div>
                    <div class="grid gap-8">
                        <CommitteeBoard :committee="committee" :advisors="advisors" />

                        <section v-if="isCurrentSession && upcoming.length" class="grid gap-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Coming up</h3>
                            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <li v-for="e in upcoming" :key="e.slug">
                                    <Link :href="`/events/${e.slug}`" class="group grid h-full overflow-hidden rounded-card border border-line bg-surface shadow-card transition hover:shadow-pop">
                                        <span class="relative block aspect-[16/9] overflow-hidden bg-gradient-to-br from-brand-cyan to-brand-blue">
                                            <img v-if="e.poster_url" :src="e.poster_url" alt="" class="size-full object-cover object-top transition group-hover:scale-105" loading="lazy" />
                                            <span class="absolute left-3 top-3 grid min-w-12 justify-items-center rounded-control bg-surface px-2 py-1 leading-none shadow-card">
                                                <span class="text-[10px] font-semibold uppercase text-brand-magenta">{{ month(e.starts_at) }}</span>
                                                <span class="text-lg font-bold">{{ day(e.starts_at) }}</span>
                                            </span>
                                        </span>
                                        <span class="grid gap-1 p-3 text-sm">
                                            <strong class="line-clamp-2">{{ e.name }}</strong>
                                            <span class="truncate text-xs text-ink-muted">{{ dateTime(e.starts_at) }}<template v-if="e.venue"> · {{ e.venue }}</template></span>
                                            <span v-if="e.registration_open" class="text-xs font-semibold text-brand-blue">Register · {{ e.fee_sen ? rm(e.fee_sen) : 'Free' }}</span>
                                        </span>
                                    </Link>
                                </li>
                            </ul>
                        </section>

                        <section v-if="isCurrentSession && (memberWall.length || can.internal)" class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card sm:p-5">
                            <div class="flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    <h3 class="font-semibold">Our members</h3>
                                    <p class="text-sm text-ink-muted">{{ counts.members }} active<template v-if="counts.alumni"> · {{ counts.alumni }} alumni</template></p>
                                </div>
                                <Link v-if="can.internal" :href="`/clubs/${club.slug}/members`" class="text-sm font-medium text-brand-blue">Full members list →</Link>
                            </div>
                            <ul v-if="memberWall.length" class="flex flex-wrap gap-3">
                                <li v-for="m in memberWall" :key="m.profile_url">
                                    <a :href="m.profile_url" class="grid w-16 justify-items-center gap-1 text-center" :title="m.name">
                                        <Avatar :src="m.photo_url" :name="m.name" size="md" />
                                        <span class="w-full truncate text-[11px]">{{ m.name.split(' ')[0] }}</span>
                                    </a>
                                </li>
                                <li v-if="counts.members > memberWall.length" class="grid w-16 justify-items-center gap-1 text-center">
                                    <span class="grid size-14 place-items-center rounded-full bg-surface-muted text-sm font-semibold">+{{ counts.members - memberWall.length }}</span>
                                    <span class="text-[11px] text-ink-muted">more</span>
                                </li>
                            </ul>
                            <p v-else class="text-sm text-ink-muted">No members yet — share the club QR to get the first sign-ups.</p>
                        </section>
                    </div>
                </template>

                <template #feed>
                    <Deferred data="posts">
                        <template #fallback><p class="text-sm text-ink-muted">Loading…</p></template>
                        <EmptyState v-if="!posts?.length" title="No posts yet" />
                        <div class="grid grid-cols-3 gap-1">
                            <Link v-for="p in posts" :key="p.id" :href="p.url" class="relative aspect-square overflow-hidden bg-surface-muted">
                                <img v-if="p.image" :src="p.image" :alt="p.caption ?? ''" class="size-full object-cover" loading="lazy" />
                                <span v-else class="grid size-full place-items-center p-2 text-center text-xs">{{ p.caption }}</span>
                                <span v-if="p.count > 1" class="absolute right-1 top-1 text-xs text-white drop-shadow">❐</span>
                            </Link>
                        </div>
                    </Deferred>
                </template>

                <template #gallery>
                    <Deferred data="albums">
                        <template #fallback><p class="text-sm text-ink-muted">Loading…</p></template>
                        <div class="grid gap-6">
                            <form v-if="can.publish" class="grid gap-3 rounded-card border border-dashed border-line p-4" @submit.prevent="album.post(`/clubs/${club.slug}/albums`, { preserveScroll: true, onSuccess: () => album.reset() })">
                                <Input v-model="album.title" label="New album" placeholder="e.g. Orientation Week 2026" :error="album.errors.title" />
                                <FileUpload v-model="album.photos" accept="image/jpeg,image/png,image/webp" multiple :error="album.errors.photos" />
                                <Button type="submit" size="sm" :loading="album.processing" class="justify-self-start">Create album</Button>
                            </form>
                            <EmptyState v-if="!albums?.length" title="No albums yet" />
                            <section v-for="a in albums" :key="a.id" class="grid gap-2">
                                <h3 class="text-sm font-semibold">{{ a.title }} <span class="font-normal text-ink-muted">· {{ a.session }}</span></h3>
                                <div class="grid grid-cols-3 gap-1 sm:grid-cols-4">
                                    <img v-for="src in a.photos" :key="src" :src="src" alt="" class="aspect-square w-full object-cover" loading="lazy" />
                                </div>
                            </section>
                        </div>
                    </Deferred>
                </template>

                <template #events>
                    <Deferred data="events">
                        <template #fallback><p class="text-sm text-ink-muted">Loading…</p></template>
                        <EmptyState v-if="!events?.length" title="No events yet" />
                        <ul class="grid gap-2">
                            <li v-for="e in events" :key="e.slug">
                                <Link :href="`/events/${e.slug}`" class="flex items-center gap-3 rounded-card border border-line bg-surface p-3 hover:shadow-card">
                                    <img v-if="e.poster_url" :src="e.poster_url" alt="" class="size-14 rounded-control object-cover" />
                                    <span class="grid flex-1 text-sm"><strong>{{ e.name }}</strong><span class="text-ink-muted">{{ dateTime(e.starts_at) }} · {{ e.venue }}</span></span>
                                    <Badge v-if="can.internal" :tone="statusTone(e.status)">{{ e.status.replace('_', ' ') }}</Badge>
                                </Link>
                            </li>
                        </ul>
                    </Deferred>
                </template>

                <template #faq>
                    <div class="grid gap-3">
                        <EmptyState v-if="!faqs.length" title="No FAQ yet" />
                        <details v-for="f in faqs" :key="f.id" class="rounded-card border border-line bg-surface p-4">
                            <summary class="cursor-pointer font-medium">{{ f.question }}</summary>
                            <p class="mt-2 whitespace-pre-line text-sm">{{ f.answer }}</p>
                            <button v-if="can.publish" type="button" class="mt-2 text-xs text-danger" @click="router.delete(`/clubs/${club.slug}/faqs/${f.id}`, { preserveScroll: true })">Remove</button>
                        </details>
                        <form v-if="can.publish" class="grid gap-3 rounded-card border border-dashed border-line p-4" @submit.prevent="faq.post(`/clubs/${club.slug}/faqs`, { preserveScroll: true, onSuccess: () => faq.reset() })">
                            <Input v-model="faq.question" label="Question" :error="faq.errors.question" />
                            <Textarea v-model="faq.answer" label="Answer" :rows="3" :error="faq.errors.answer" />
                            <Button type="submit" size="sm" class="justify-self-start">Add FAQ</Button>
                        </form>
                    </div>
                </template>

                <template #documents>
                    <Deferred data="documents">
                        <template #fallback><p class="text-sm text-ink-muted">Loading…</p></template>
                        <EmptyState v-if="!documents?.length" title="No documents yet" />
                        <ul class="grid gap-2">
                            <li v-for="d in documents" :key="d.id">
                                <Link :href="`/documents/${d.id}`" class="grid gap-2 rounded-card border border-line bg-surface p-3 text-sm hover:shadow-card">
                                    <span class="flex items-center justify-between gap-3">
                                        <span><strong>{{ d.type }}</strong><template v-if="d.event"> · {{ d.event }}</template> <span class="text-ink-muted">· {{ d.updated }}</span></span>
                                        <Badge :tone="statusTone(d.status)">{{ d.status.replace('_', ' ') }}</Badge>
                                    </span>
                                    <ApprovalTracker v-if="d.progress.total" :progress="d.progress" compact />
                                </Link>
                            </li>
                        </ul>
                    </Deferred>
                </template>
            </Tabs>
        </div>

        <StoryViewer :group="viewing" @close="viewing = null">
            <template #default="{ storyId }">
                <button v-if="can.publish && storyId && storyId > 0" type="button" class="absolute bottom-4 right-4 z-10 rounded-control bg-white/90 px-3 py-1.5 text-xs font-semibold" @click="highlight(storyId)">＋ Highlight</button>
            </template>
        </StoryViewer>
        <CreatePost v-if="can.publish" v-model:open="creating" :clubs="[{ slug: club.slug, name: club.name }]" :events="(events ?? []).filter((e) => e.status === 'approved').map((e) => ({ id: e.id, name: e.name }))" />
    </AppLayout>
</template>
