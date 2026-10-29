<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import Card from '@/Components/ui/Card.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime } from '@/lib/format';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps<{
    event: {
        slug: string;
        name: string;
        summary: string | null;
        venue: string | null;
        mode: string;
        is_outdoor: boolean;
        cca_participant: number;
        cca_committee: number;
        registration_open: boolean;
        attendance_open: boolean;
        status: string;
        starts_at: string;
        ends_at: string;
        poster_url: string | null;
        club: { slug: string; name: string };
        proposal_id: number | null;
    };
    committee: { position: string; name: string; photo_url: string | null; responsibility: string | null }[];
    approval: { progress: ApprovalProgress; steps: ApprovalStep[] } | null;
    registered: boolean;
    indemnity: { signed: boolean; closes_at: string; closed: boolean } | null;
    registrations: number;
    can: { organise: boolean; update: boolean };
}>();

const register = (): void => router.post(`/events/${props.event.slug}/register`, {}, { preserveScroll: true });
const unregister = (): void => router.delete(`/events/${props.event.slug}/register`, { preserveScroll: true });
</script>

<template>
    <Head :title="event.name" />
    <AppLayout>
        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="grid content-start gap-6">
                <img v-if="event.poster_url" :src="event.poster_url" :alt="`${event.name} poster`" class="w-full rounded-card border border-line" />
                <header class="grid gap-2">
                    <Link :href="`/clubs/${event.club.slug}`" class="text-sm font-medium text-brand-blue">{{ event.club.name }}</Link>
                    <h1 class="text-2xl font-bold">{{ event.name }}</h1>
                    <div class="flex flex-wrap gap-2">
                        <Badge :tone="event.status === 'approved' ? 'success' : 'neutral'">{{ event.status.replace('_', ' ') }}</Badge>
                        <Badge v-if="event.is_outdoor" tone="warning">Outdoor / off-campus · indemnity required</Badge>
                        <Badge tone="info">{{ event.cca_participant }} CCA</Badge>
                    </div>
                </header>
                <dl class="grid gap-1 text-sm">
                    <div class="flex gap-2"><dt class="w-16 text-ink-muted">When</dt><dd>{{ dateTime(event.starts_at) }} – {{ dateTime(event.ends_at) }}</dd></div>
                    <div class="flex gap-2"><dt class="w-16 text-ink-muted">Where</dt><dd>{{ event.venue }} <span class="capitalize text-ink-muted">({{ event.mode }})</span></dd></div>
                </dl>
                <p v-if="event.summary" class="whitespace-pre-line text-sm">{{ event.summary }}</p>
                <Card v-if="approval">
                    <template #title>Proposal approval</template>
                    <ApprovalTracker :progress="approval.progress" :steps="approval.steps" />
                </Card>
                <Card v-if="committee.length">
                    <template #title>Event committee</template>
                    <ul class="grid gap-3 sm:grid-cols-2">
                        <li v-for="m in committee" :key="m.position" class="flex items-center gap-3">
                            <Avatar :src="m.photo_url" :name="m.name" size="sm" />
                            <span class="grid text-sm"><strong>{{ m.name }}</strong><span class="text-ink-muted">{{ m.position }}</span></span>
                        </li>
                    </ul>
                </Card>
            </div>
            <aside class="grid content-start gap-3">
                <Card>
                    <p class="text-sm text-ink-muted">{{ registrations }} registered</p>
                    <template v-if="registered">
                        <p class="mt-2 text-sm font-medium text-success">You're registered.</p>
                        <button type="button" class="mt-1 text-xs text-ink-muted underline" @click="unregister">Cancel registration</button>
                    </template>
                    <Button v-else-if="event.registration_open" block class="mt-3" @click="register">Register</Button>
                    <p v-else class="mt-2 text-sm text-ink-muted">Registration opens once SLD approves the proposal.</p>
                    <template v-if="indemnity && (registered || can.organise)">
                        <p v-if="indemnity.signed" class="mt-3 text-sm text-success">✓ Indemnity signed</p>
                        <Link v-else-if="!indemnity.closed" :href="`/events/${event.slug}/indemnity`"><Button variant="secondary" block class="mt-3">Sign indemnity (closes {{ indemnity.closes_at }})</Button></Link>
                        <p v-else class="mt-3 text-sm text-danger">Indemnity signing has closed.</p>
                    </template>
                </Card>
                <template v-if="can.organise">
                    <Link :href="`/events/${event.slug}/attendance`"><Button block>Attendance &amp; QR</Button></Link>
                    <Link v-if="event.is_outdoor" :href="`/events/${event.slug}/indemnities`"><Button variant="secondary" block>Indemnity list</Button></Link>
                    <Link v-if="event.status === 'approved'" :href="`/events/${event.slug}/documents`"><Button variant="secondary" block>Post-event documents</Button></Link>
                </template>
                <Link v-if="can.update" :href="`/events/${event.slug}/poster`"><Button variant="ghost" block>Poster</Button></Link>
                <template v-if="can.update && event.proposal_id">
                    <Link :href="`/events/${event.slug}/proposal`"><Button variant="secondary" block>Proposal form</Button></Link>
                    <Link :href="`/documents/${event.proposal_id}`"><Button variant="ghost" block>Approval progress</Button></Link>
                </template>
            </aside>
        </div>
    </AppLayout>
</template>
