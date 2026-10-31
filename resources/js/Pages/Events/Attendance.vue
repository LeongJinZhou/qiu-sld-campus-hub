<script setup lang="ts">
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Icon from '@/Components/ui/Icon.vue';
import Input from '@/Components/ui/Input.vue';
import Modal from '@/Components/ui/Modal.vue';
import QrScanner from '@/Components/ui/QrScanner.vue';
import SignaturePad from '@/Components/ui/SignaturePad.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { rm } from '@/lib/format';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useIntervalFn, useLocalStorage } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

interface Person {
    user_id: number;
    name: string;
    photo_url: string | null;
    student_number: string | null;
    registered: boolean;
    status: 'paid' | 'free' | 'unpaid' | 'checking' | 'committee' | 'walk_in';
    order_id: string | null;
    in: string | null;
    out: string | null;
    method: string | null;
    key: string;
}

const props = defineProps<{
    event: { slug: string; name: string; is_outdoor: boolean; attendance_open: boolean; fee_sen: number; starts_at: string; club_slug: string };
    action: 'in' | 'out';
    qr: { svg: string; seconds: number; seconds_left: number } | null;
    find: string;
    results: Person[];
    people: Person[];
    counts: { registered: number; paid: number; unpaid: number; in: number; out: number };
    helpers: { id: number; name: string; student_number: string | null; photo_url: string | null }[];
    signOff: { id: number; status: string; progress: ApprovalProgress; steps: ApprovalStep[] } | null;
    can: { manage: boolean };
    limits: { min: number; max: number };
}>();

const url = `/events/${props.event.slug}/attendance`;
const stats = computed(() => [
    { l: 'Registered', v: props.counts.registered, warn: false },
    { l: props.event.fee_sen ? 'Paid' : 'Confirmed', v: props.counts.paid, warn: false },
    { l: 'Not paid', v: props.counts.unpaid, warn: true },
    { l: 'In', v: props.counts.in, warn: false },
    { l: 'Out', v: props.counts.out, warn: false },
]);

// What the desk shows: scan people's QRs, show the rotating QR for them to scan, or both side by side.
const view = useLocalStorage<'scan' | 'qr' | 'both'>(`desk-view-${props.event.slug}`, props.can.manage ? 'both' : 'scan');
const scanAction = ref<'check' | 'in' | 'out'>('in');
const statusInfo: Record<Person['status'], { label: string; tone: 'success' | 'warning' | 'danger' | 'info' | 'neutral' }> = {
    paid: { label: 'Paid', tone: 'success' },
    free: { label: 'Registered', tone: 'success' },
    unpaid: { label: 'NOT PAID', tone: 'danger' },
    checking: { label: 'Payment being checked', tone: 'warning' },
    committee: { label: 'Committee', tone: 'info' },
    walk_in: { label: 'Walk-in', tone: 'neutral' },
};

/* ---------- Scan / look up ---------- */
const scanning = ref(false);
let lastScan = '';
function onScan(code: string): void {
    if (code === lastScan) return;
    lastScan = code;
    router.post(`${url}?action=${props.action}`, { action: scanAction.value, code }, { preserveScroll: true, preserveState: true, onFinish: () => setTimeout(() => (lastScan = ''), 2500) });
}
const query = ref(props.find);
const lookup = (): void => router.get(url, { find: query.value.trim(), action: props.action }, { preserveState: true, preserveScroll: true, replace: true });
const clearLookup = (): void => {
    query.value = '';
    router.get(url, { action: props.action }, { preserveState: true, preserveScroll: true, replace: true });
};
const act = (p: Person, action: 'in' | 'out' | 'register'): void => {
    router.post(`${url}?action=${props.action}`, { action, code: p.student_number ?? p.key }, { preserveScroll: true, preserveState: true });
};

/* ---------- Rotating QR ---------- */
const setAction = (action: 'in' | 'out'): void => router.get(url, { action, ...(props.find ? { find: props.find } : {}) }, { preserveState: true, preserveScroll: true, replace: true });
const left = ref(props.qr?.seconds_left ?? 0);
const tick = (): void => {
    left.value = Math.max(0, left.value - 1);
    if (left.value === 0 && props.qr) router.reload({ only: ['qr'], onFinish: () => (left.value = props.qr?.seconds_left ?? 0) });
};
useIntervalFn(tick, 1000, { immediate: !!props.qr });
watch(() => props.qr?.seconds_left, (s) => (left.value = s ?? 0));
const pace = useForm({ qr_rotate_seconds: props.qr?.seconds ?? 60 });
const presets = [10, 15, 30, 60, 120, 300];
const savePace = (seconds: number): void => {
    pace.qr_rotate_seconds = seconds;
    pace.put(`${url}/settings`, { preserveScroll: true, onSuccess: () => router.reload({ only: ['qr'] }) });
};
const qrBox = ref<HTMLElement | null>(null);
const fullscreen = (): void => void qrBox.value?.requestFullscreen?.().catch(() => undefined);

/* ---------- People list (refreshes by itself) ---------- */
useIntervalFn(() => router.reload({ only: ['people', 'counts'] }), 15_000);
const filter = ref<'all' | 'paid' | 'unpaid' | 'in' | 'notin'>('all');
const search = ref('');
const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.people.filter((p) => {
        const ok = { all: true, paid: ['paid', 'free'].includes(p.status), unpaid: ['unpaid', 'checking'].includes(p.status), in: !!p.in, notin: !p.in }[filter.value];
        return ok && (!q || `${p.name} ${p.student_number ?? ''} ${p.order_id ?? ''}`.toLowerCase().includes(q));
    });
});

/* ---------- Helpers & sign-off (organisers) ---------- */
const helper = useForm({ student_number: '' });
const addHelper = (): void => helper.post(`/events/${props.event.slug}/helpers`, { preserveScroll: true, onSuccess: () => helper.reset() });
const removeHelper = (id: number): void => router.delete(`/events/${props.event.slug}/helpers/${id}`, { preserveScroll: true });
const signing = ref(false);
const sign = useForm({ signature: null as string | null, signature_strokes: null as string | null });
const submitList = (): void => sign.post(`${url}/submit`, { preserveScroll: true, onSuccess: () => { signing.value = false; sign.reset(); } });
const canSubmit = computed(() => props.can.manage && props.event.attendance_open && (!props.signOff || ['draft', 'returned'].includes(props.signOff.status)));
</script>

<template>
    <Head :title="`Door · ${event.name}`" />
    <AppLayout>
        <div class="grid gap-5">
            <header class="grid gap-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <Link :href="`/events/${event.slug}`" class="text-sm text-brand-blue">← {{ event.name }}</Link>
                        <h1 class="text-xl font-bold">Door &amp; attendance</h1>
                    </div>
                    <div v-if="can.manage" class="flex flex-wrap gap-2">
                        <Button v-if="canSubmit" size="sm" @click="signing = true">Sign &amp; submit list</Button>
                        <a :href="`${url}/export`"><Button size="sm" variant="secondary">03 Attendance (XLSX)</Button></a>
                        <Link v-if="event.is_outdoor" :href="`/events/${event.slug}/indemnities`"><Button size="sm" variant="ghost">Indemnities</Button></Link>
                    </div>
                </div>
                <dl class="grid grid-cols-5 divide-x divide-line rounded-card border border-line bg-surface text-center shadow-card">
                    <div v-for="s in stats" :key="s.l" class="py-2.5">
                        <dd class="text-lg font-bold tabular-nums" :class="s.warn && s.v ? 'text-danger' : ''">{{ s.v }}</dd>
                        <dt class="text-[11px] text-ink-muted">{{ s.l }}</dt>
                    </div>
                </dl>
            </header>

            <p v-if="!event.attendance_open" class="rounded-card bg-warning/10 px-4 py-3 text-sm text-warning">Clock-in opens once the proposal is approved. You can still check tickets and payments.</p>

            <!-- How the desk works right now -->
            <div class="grid gap-2 sm:grid-cols-2">
                <div class="grid grid-cols-3 gap-1 rounded-control bg-surface-muted p-1 text-sm" role="radiogroup" aria-label="Desk view">
                    <button v-for="v in [{ k: 'scan', l: 'Scan people' }, { k: 'qr', l: 'Show QR' }, { k: 'both', l: 'Both' }] as const" :key="v.k" type="button" role="radio" :aria-checked="view === v.k" class="rounded-control px-2 py-2" :class="view === v.k ? 'bg-surface font-semibold shadow-card' : ''" @click="view = v.k">{{ v.l }}</button>
                </div>
                <div class="grid grid-cols-2 gap-1 rounded-control bg-surface-muted p-1 text-sm" role="radiogroup" aria-label="Clock in or out">
                    <button v-for="a in ['in', 'out'] as const" :key="a" type="button" role="radio" :aria-checked="action === a" class="rounded-control px-2 py-2" :class="action === a ? (a === 'in' ? 'bg-success font-semibold text-white' : 'bg-ink font-semibold text-white') : ''" @click="setAction(a); scanAction = a">Clock {{ a }}</button>
                </div>
            </div>

            <div class="grid gap-5" :class="view === 'both' ? 'lg:grid-cols-2' : ''">
                <!-- Scan participants -->
                <Card v-if="view !== 'qr'">
                    <template #title>Scan tickets &amp; QR</template>
                    <div class="grid gap-3">
                        <div class="grid grid-cols-3 gap-1 rounded-control bg-surface-muted p-1 text-xs" role="radiogroup" aria-label="What a scan does">
                            <button v-for="m in [{ k: 'check', l: 'Check only' }, { k: 'in', l: 'Scan = clock in' }, { k: 'out', l: 'Scan = clock out' }] as const" :key="m.k" type="button" role="radio" :aria-checked="scanAction === m.k" class="rounded-control px-1 py-1.5" :class="scanAction === m.k ? 'bg-surface font-semibold shadow-card' : ''" @click="scanAction = m.k">{{ m.l }}</button>
                        </div>
                        <QrScanner v-if="scanning" @scan="onScan" />
                        <Button :variant="scanning ? 'secondary' : 'primary'" @click="scanning = !scanning"><Icon name="scan" :size="18" /> {{ scanning ? 'Stop camera' : 'Start scanning' }}</Button>
                        <form class="flex items-end gap-2" @submit.prevent="lookup">
                            <Input v-model="query" label="Or type a name, ticket ID or student ID" placeholder="e.g. Kavitha, GPS-CLUB_…_0003, QIU-202407-0001" class="flex-1" />
                            <Button type="submit" variant="secondary">Find</Button>
                        </form>
                        <p class="text-xs text-ink-muted">Works with event tickets and personal profile QRs.</p>
                    </div>
                </Card>

                <!-- Rotating QR for participants to scan -->
                <Card v-if="view !== 'scan'">
                    <template #title>Participants scan this</template>
                    <template #actions><button type="button" class="text-sm font-medium text-brand-blue" @click="fullscreen">Full screen</button></template>
                    <div v-if="qr" ref="qrBox" class="grid justify-items-center gap-3 bg-surface p-2">
                        <p class="text-lg font-bold" :class="action === 'in' ? 'text-success' : 'text-ink'">Clock {{ action }} · {{ event.name }}</p>
                        <div class="relative">
                            <div class="size-64 rounded-card bg-white p-3 sm:size-72 [&>svg]:size-full" role="img" :aria-label="`QR to clock ${action}`" v-html="qr.svg" />
                        </div>
                        <div class="grid w-64 gap-1 sm:w-72">
                            <div class="h-1.5 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full bg-gradient-to-r from-brand-cyan to-brand-magenta transition-[width] duration-1000 ease-linear" :style="{ width: `${(left / qr.seconds) * 100}%` }" /></div>
                            <p class="text-center text-xs text-ink-muted">New code in {{ left }} s · a shared photo stops working</p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-ink-muted">The QR appears once attendance opens.</p>
                    <div v-if="can.manage && qr" class="mt-4 grid gap-2 border-t border-line pt-3">
                        <p class="text-xs font-medium text-ink-muted">Change the code every</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="s in presets" :key="s" type="button" class="rounded-full border px-3 py-1 text-xs font-medium" :class="qr.seconds === s ? 'border-ink bg-ink text-white' : 'border-line hover:bg-surface-muted'" @click="savePace(s)">{{ s < 60 ? `${s} s` : `${s / 60} min` }}</button>
                            <form class="flex items-center gap-1" @submit.prevent="savePace(Number(pace.qr_rotate_seconds))">
                                <input v-model.number="pace.qr_rotate_seconds" type="number" :min="limits.min" :max="limits.max" class="w-16 rounded-full border border-line px-2 py-1 text-xs" aria-label="Seconds" />
                                <button type="submit" class="text-xs font-medium text-brand-blue">Set</button>
                            </form>
                        </div>
                        <p v-if="pace.errors.qr_rotate_seconds" class="text-xs text-danger">{{ pace.errors.qr_rotate_seconds }}</p>
                    </div>
                </Card>
            </div>

            <!-- Who was found -->
            <section v-if="find" class="grid gap-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Result for “{{ find }}”</h2>
                    <button type="button" class="text-sm text-ink-muted underline" @click="clearLookup">Clear</button>
                </div>
                <p v-if="!results.length" class="rounded-card border border-line bg-surface p-4 text-sm text-ink-muted">No ticket or student found.</p>
                <article v-for="p in results" :key="p.user_id" class="grid gap-3 rounded-card border-2 bg-surface p-4 shadow-card" :class="['paid', 'free', 'committee'].includes(p.status) ? 'border-success' : p.status === 'walk_in' && !p.registered ? 'border-line' : 'border-danger'">
                    <div class="flex items-center gap-3">
                        <Avatar :src="p.photo_url" :name="p.name" size="md" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-lg font-bold">{{ p.name }}</p>
                            <p class="text-xs text-ink-muted">{{ p.student_number }}<template v-if="p.order_id"> · <span class="font-mono">{{ p.order_id }}</span></template></p>
                        </div>
                        <Badge :tone="p.registered ? statusInfo[p.status].tone : 'neutral'">{{ p.registered ? statusInfo[p.status].label : 'Not registered' }}</Badge>
                    </div>
                    <p v-if="p.status === 'unpaid'" class="rounded-control bg-danger/10 px-3 py-2 text-sm text-danger">Fee {{ rm(event.fee_sen) }} not paid. They can pay in the app (online or cash with a committee member) — clock-in stays blocked until then.</p>
                    <p class="text-sm">In: <strong>{{ p.in ?? '—' }}</strong> · Out: <strong>{{ p.out ?? '—' }}</strong></p>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <Button v-if="!p.registered" variant="secondary" @click="act(p, 'register')">Register</Button>
                        <Button :disabled="!event.attendance_open || !!p.in" @click="act(p, 'in')">Clock in</Button>
                        <Button variant="secondary" :disabled="!event.attendance_open || !p.in || !!p.out" @click="act(p, 'out')">Clock out</Button>
                    </div>
                </article>
            </section>

            <!-- Everyone -->
            <Card>
                <template #title>People</template>
                <div class="grid gap-3">
                    <div class="flex flex-wrap gap-1.5">
                        <button v-for="f in [{ k: 'all', l: `All ${people.length}` }, { k: 'paid', l: `Paid ${counts.paid}` }, { k: 'unpaid', l: `Not paid ${counts.unpaid}` }, { k: 'in', l: `In ${counts.in}` }, { k: 'notin', l: 'Not in yet' }] as const" :key="f.k" type="button" class="rounded-full border px-3 py-1 text-xs font-medium" :class="filter === f.k ? 'border-ink bg-ink text-white' : 'border-line hover:bg-surface-muted'" @click="filter = f.k">{{ f.l }}</button>
                    </div>
                    <Input v-model="search" placeholder="Search name, student ID or ticket ID" aria-label="Search people" />
                    <ul class="divide-y divide-line">
                        <li v-for="p in filtered" :key="p.user_id">
                            <button type="button" class="flex w-full items-center gap-3 py-2.5 text-left" @click="query = p.student_number ?? p.name; lookup()">
                                <Avatar :src="p.photo_url" :name="p.name" size="sm" />
                                <span class="grid min-w-0 flex-1 text-sm">
                                    <strong class="truncate">{{ p.name }}</strong>
                                    <span class="truncate text-xs text-ink-muted">{{ p.student_number }}<template v-if="p.order_id"> · {{ p.order_id }}</template></span>
                                </span>
                                <span class="grid justify-items-end gap-1 text-xs">
                                    <Badge :tone="statusInfo[p.status].tone">{{ statusInfo[p.status].label }}</Badge>
                                    <span class="text-ink-muted">{{ p.in ? `In ${p.in}` : 'Not in' }}<template v-if="p.out"> · Out {{ p.out }}</template></span>
                                </span>
                            </button>
                        </li>
                        <li v-if="!filtered.length" class="py-6 text-center text-sm text-ink-muted">Nobody here.</li>
                    </ul>
                </div>
            </Card>

            <div v-if="can.manage" class="grid gap-5 lg:grid-cols-2">
                <Card>
                    <template #title>Student helpers</template>
                    <p class="mb-3 text-sm text-ink-muted">Helpers can scan tickets and clock people in and out for this event only.</p>
                    <ul class="mb-3 grid gap-2">
                        <li v-for="h in helpers" :key="h.id" class="flex items-center gap-3">
                            <Avatar :src="h.photo_url" :name="h.name" size="sm" />
                            <span class="grid min-w-0 flex-1 text-sm"><strong class="truncate">{{ h.name }}</strong><span class="text-xs text-ink-muted">{{ h.student_number }}</span></span>
                            <button type="button" class="text-xs text-danger" @click="removeHelper(h.id)">Remove</button>
                        </li>
                        <li v-if="!helpers.length" class="text-sm text-ink-muted">No helpers yet.</li>
                    </ul>
                    <form class="flex items-end gap-2" @submit.prevent="addHelper">
                        <Input v-model="helper.student_number" label="Add by student ID" placeholder="QIU-202407-0001" class="flex-1" :error="helper.errors.student_number" />
                        <Button type="submit" variant="secondary" :loading="helper.processing">Add</Button>
                    </form>
                </Card>
                <Card v-if="signOff">
                    <template #title>Attendance list sign-off</template>
                    <ApprovalTracker :progress="signOff.progress" :steps="signOff.steps" />
                </Card>
            </div>
        </div>

        <Modal v-model:open="signing" title="Sign the attendance list">
            <div class="grid gap-4">
                <p class="text-sm text-ink-muted">{{ counts.in }} present. Your signature goes on the official 03 Attendance List as “Prepared by”, then it goes to your Club Advisor.</p>
                <SignaturePad v-model="sign.signature" v-model:strokes="sign.signature_strokes" label="Your signature" />
                <Button :disabled="!sign.signature" :loading="sign.processing" @click="submitList">Sign &amp; submit</Button>
            </div>
        </Modal>
    </AppLayout>
</template>
