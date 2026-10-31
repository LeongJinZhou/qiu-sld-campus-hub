<script setup lang="ts">
import Icon from '@/Components/ui/Icon.vue';
import Input from '@/Components/ui/Input.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import Card from '@/Components/ui/Card.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime, rm } from '@/lib/format';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

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
        members_only: boolean;
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
    registrationStatus: string | null;
    ticketUrl: string | null;
    mayJoin: boolean;
    payment: { fee_sen: number; payment_url: string | null; payment_account_name: string | null; qr: { path: string; url: string }[] };
    indemnity: { signed: boolean; closes_at: string; closed: boolean } | null;
    registrations: number;
    can: { organise: boolean; update: boolean; checkIn: boolean };
}>();

const register = (): void => router.post(`/events/${props.event.slug}/register`, {}, { preserveScroll: true });

// Committee: fee, account, link and up to two own payment QR codes.
const pay = useForm({ fee_rm: props.payment.fee_sen / 100, payment_account_name: props.payment.payment_account_name ?? '', payment_url: props.payment.payment_url ?? '', qr: [] as File[], remove_qr: [] as string[] });
const toggleRemove = (path: string): void => {
    pay.remove_qr = pay.remove_qr.includes(path) ? pay.remove_qr.filter((p) => p !== path) : [...pay.remove_qr, path];
};
const savePayment = (): void => {
    pay.transform((d) => ({ ...d, _method: 'put' })).post(`/events/${props.event.slug}/payment-settings`, { preserveScroll: true, forceFormData: true, onSuccess: () => pay.reset('qr', 'remove_qr') });
};
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
                        <Badge :tone="event.members_only ? 'warning' : 'success'">{{ event.members_only ? `${event.club.name} members only` : 'Open to all QIU students' }}</Badge>
                    </div>
                </header>
                <dl class="grid gap-1 text-sm">
                    <div class="flex flex-wrap gap-2"><dt class="w-16 text-ink-muted">When</dt><dd>{{ dateTime(event.starts_at) }} – {{ dateTime(event.ends_at) }}</dd></div>
                    <div class="flex flex-wrap gap-2"><dt class="w-16 text-ink-muted">Where</dt><dd>{{ event.venue }} <span class="capitalize text-ink-muted">({{ event.mode }})</span></dd></div>
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
                    <p class="text-sm text-ink-muted">{{ registrations }} registered<template v-if="payment.fee_sen"> · fee {{ rm(payment.fee_sen) }}</template></p>
                    <template v-if="registrationStatus === 'pending_payment'">
                        <p class="mt-2 text-sm font-medium text-warning">Place reserved — payment pending.</p>
                        <Link :href="`/events/${event.slug}/pay`"><Button block class="mt-2">Pay {{ rm(payment.fee_sen) }}</Button></Link>
                        <button type="button" class="mt-1 text-xs text-ink-muted underline" @click="unregister">Cancel</button>
                    </template>
                    <template v-else-if="registered">
                        <p class="mt-2 text-sm font-medium text-success">You're registered.</p>
                        <Link v-if="ticketUrl" :href="ticketUrl"><Button block class="mt-2">View my ticket</Button></Link>
                        <button type="button" class="mt-1 text-xs text-ink-muted underline" @click="unregister">Cancel registration</button>
                    </template>
                    <template v-else-if="event.registration_open && !mayJoin">
                        <p class="mt-2 text-sm">This event is for <strong>{{ event.club.name }}</strong> members only.</p>
                        <Link :href="`/clubs/${event.club.slug}/join`"><Button block class="mt-2">Join {{ event.club.name }} to register</Button></Link>
                    </template>
                    <Button v-else-if="event.registration_open" block class="mt-3" @click="register">{{ payment.fee_sen ? `Register · ${rm(payment.fee_sen)}` : 'Register' }}</Button>
                    <p v-else class="mt-2 text-sm text-ink-muted">Registration opens once SLD approves the proposal.</p>
                    <template v-if="indemnity && (registered || can.organise)">
                        <p v-if="indemnity.signed" class="mt-3 text-sm text-success">✓ Indemnity signed</p>
                        <Link v-else-if="!indemnity.closed" :href="`/events/${event.slug}/indemnity`"><Button variant="secondary" block class="mt-3">Sign indemnity (closes {{ indemnity.closes_at }})</Button></Link>
                        <p v-else class="mt-3 text-sm text-danger">Indemnity signing has closed.</p>
                    </template>
                </Card>
                <Card v-if="can.update">
                    <template #title>Who can register</template>
                    <div class="grid gap-2" role="radiogroup" aria-label="Who can register">
                        <button v-for="o in [{ v: false, l: 'Anyone at QIU', d: 'Members and non-members can register.' }, { v: true, l: 'Club members only', d: `Only ${event.club.name} members (and the committee). Others are asked to join first.` }]" :key="String(o.v)" type="button" role="radio" :aria-checked="event.members_only === o.v" class="grid gap-0.5 rounded-control border p-3 text-left text-sm transition" :class="event.members_only === o.v ? 'border-ink bg-surface-muted' : 'border-line hover:bg-surface-muted'" @click="router.put(`/events/${event.slug}/registration-settings`, { members_only: o.v }, { preserveScroll: true })">
                            <span class="flex items-center gap-2 font-semibold"><span class="grid size-4 place-items-center rounded-full border-2" :class="event.members_only === o.v ? 'border-ink' : 'border-line'"><span v-if="event.members_only === o.v" class="size-2 rounded-full bg-ink" /></span>{{ o.l }}</span>
                            <span class="pl-6 text-xs text-ink-muted">{{ o.d }}</span>
                        </button>
                    </div>
                </Card>
                <Card v-if="can.update">
                    <template #title>Event payment</template>
                    <form class="grid gap-3" @submit.prevent="savePayment">
                        <Input v-model="pay.fee_rm" type="number" label="Fee (RM, 0 = free)" :error="pay.errors.fee_rm" />
                        <template v-if="Number(pay.fee_rm) > 0">
                            <Input v-model="pay.payment_account_name" label="Pay to (account name)" :error="pay.errors.payment_account_name" />
                            <Input v-model="pay.payment_url" label="Payment link" placeholder="https://…" hint="A QR code of this link is shown to payers automatically." :error="pay.errors.payment_url" />
                            <div v-if="payment.qr.length" class="flex flex-wrap gap-2">
                                <span v-for="q in payment.qr" :key="q.path" class="relative">
                                    <img :src="q.url" alt="Payment QR" class="size-20 rounded-control border border-line object-cover" :class="pay.remove_qr.includes(q.path) ? 'opacity-30' : ''" />
                                    <button type="button" class="absolute -right-1 -top-1 grid size-5 place-items-center rounded-full bg-ink text-xs text-white" :aria-label="pay.remove_qr.includes(q.path) ? 'Keep this QR' : 'Remove this QR'" @click="toggleRemove(q.path)">{{ pay.remove_qr.includes(q.path) ? '↺' : '×' }}</button>
                                </span>
                            </div>
                            <FileUpload v-if="payment.qr.length - pay.remove_qr.length < 2" v-model="pay.qr" multiple label="Your own payment QR codes (up to 2, optional)" accept="image/png,image/jpeg,image/webp" :error="pay.errors.qr" />
                        </template>
                        <Button type="submit" :loading="pay.processing">Save payment settings</Button>
                    </form>
                </Card>
                <Link v-if="can.checkIn && !can.organise" :href="`/events/${event.slug}/attendance`"><Button block><Icon name="scan" :size="18" /> Door helper: scan tickets</Button></Link>
                <template v-if="can.organise">
                    <Link :href="`/events/${event.slug}/attendance`"><Button block>Door &amp; attendance</Button></Link>
                    <Link v-if="payment.fee_sen" :href="`/clubs/${event.club.slug}/finance/events/${event.slug}`"><Button block variant="secondary">Who paid · money tally</Button></Link>
                    <Link v-if="event.is_outdoor" :href="`/events/${event.slug}/indemnities`"><Button variant="secondary" block>Indemnity list</Button></Link>
                    <Link v-if="event.status === 'approved'" :href="`/events/${event.slug}/documents`"><Button variant="secondary" block>Post-event documents</Button></Link>
                    <Link v-if="event.status === 'approved'" :href="`/events/${event.slug}/post-mortem`"><Button variant="secondary" block>Post mortem (09)</Button></Link>
                </template>
                <Link v-if="can.update" :href="`/events/${event.slug}/poster`"><Button variant="secondary" block>Poster</Button></Link>
                <template v-if="can.update && event.proposal_id">
                    <Link :href="`/events/${event.slug}/proposal`"><Button variant="secondary" block>Proposal form</Button></Link>
                    <Link :href="`/documents/${event.proposal_id}`"><Button variant="secondary" block>Approval progress</Button></Link>
                </template>
            </aside>
        </div>
    </AppLayout>
</template>
