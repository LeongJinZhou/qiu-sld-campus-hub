<script setup lang="ts">
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import Input from '@/Components/ui/Input.vue';
import Modal from '@/Components/ui/Modal.vue';
import SignaturePad from '@/Components/ui/SignaturePad.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime, rm } from '@/lib/format';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    withdrawal: {
        id: number;
        status: string;
        stage: string | null;
        stage_label: string | null;
        club: { slug: string; name: string };
        event: { slug: string; name: string } | null;
        scope: 'event' | 'club';
        purpose: string | null;
        needed_by: string | null;
        items: { description: string; unit_sen: number; quantity: number; amount_sen: number }[];
        total_sen: number;
        bank: { bank_name: string; account_name: string; account_number: string } | null;
        prepared_by: string | null;
        created_at: string | null;
        bankin_reference: string | null;
        note: string | null;
        order_id: string | null;
        completed_at: string | null;
        files: Partial<Record<'form' | 'finance' | 'bankin', string>>;
    };
    approval: { progress: ApprovalProgress; steps: ApprovalStep[] } | null;
    stages: Record<string, string>;
    can: { sign: boolean; sld: boolean; bankin: boolean };
}>();
const w = computed(() => props.withdrawal);

// The whole journey on one line: signatures first, then the money.
const journey = computed(() => {
    const signed = w.value.status === 'approved';
    const order = ['awaiting_finance', 'awaiting_bankin', 'bankin_uploaded', 'completed'];
    const at = w.value.stage ? order.indexOf(w.value.stage) : -1;
    return [
        { label: 'Filled in', done: true },
        { label: 'Treasurer, Advisor & SLD sign', done: signed, current: !signed },
        { label: 'Finance signs', done: at > 0, current: at === 0 },
        { label: 'Bank-in', done: at > 1, current: at === 1 },
        { label: 'SLD confirms', done: at > 2, current: at === 2 },
    ];
});

const sign = useForm({ signature: null as string | null, signature_strokes: null as string | null, bank_confirmed: false });
const signing = ref(false);
const doSign = (): void => {
    sign.bank_confirmed = true;
    sign.post(`/withdrawals/${w.value.id}/sign`, { preserveScroll: true, onFinish: () => (signing.value = false) });
};
const upload = useForm({ file: null as File | null, reference: '' });
const send = (step: 'finance-signed' | 'bank-in', e: Event): void => {
    upload.file = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (upload.file) upload.post(`/withdrawals/${w.value.id}/${step}`, { preserveScroll: true, forceFormData: true, onSuccess: () => upload.reset() });
};
const complete = (): void => {
    if (confirm(`Confirm RM ${(w.value.total_sen / 100).toFixed(2)} arrived? It will be recorded in the club finance.`))
        router.post(`/withdrawals/${w.value.id}/complete`, {}, { preserveScroll: true });
};
const sendBack = (): void => {
    const note = prompt('What is wrong with the bank-in proof? (sent to the Treasurer)');
    if (note) router.post(`/withdrawals/${w.value.id}/reject-proof`, { note }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Fund withdrawal" />
    <AppLayout>
        <div class="grid gap-5">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <Link :href="`/clubs/${w.club.slug}/finance`" class="text-sm text-brand-blue">← {{ w.club.name }} finance</Link>
                    <h1 class="text-xl font-bold">Fund withdrawal · {{ rm(w.total_sen) }}</h1>
                    <p class="text-sm text-ink-muted">
                        <Badge :tone="w.scope === 'event' ? 'info' : 'neutral'">{{ w.scope === 'event' ? 'Event' : 'Club' }}</Badge>
                        <template v-if="w.event">
                            <Link :href="`/events/${w.event.slug}`" class="font-medium hover:underline">{{ w.event.name }}</Link></template
                        >
                        <template v-else> {{ w.purpose }}</template>
                        · filled in by {{ w.prepared_by }}<template v-if="w.created_at"> {{ dateTime(w.created_at) }}</template>
                    </p>
                </div>
                <Badge :tone="w.stage === 'completed' ? 'success' : w.status === 'rejected' ? 'danger' : 'warning'">{{ w.stage_label ?? w.status.replace('_', ' ') }}</Badge>
            </header>

            <ol class="grid grid-cols-5 gap-1 text-center text-[11px] sm:text-xs" aria-label="Progress">
                <li v-for="(s, i) in journey" :key="i" class="grid justify-items-center gap-1">
                    <span
                        class="grid size-7 place-items-center rounded-full text-xs font-bold transition"
                        :class="s.done ? 'bg-success text-white' : s.current ? 'animate-tracker-pulse bg-brand-magenta text-white' : 'bg-surface-muted text-ink-muted'"
                        >{{ s.done ? '✓' : i + 1 }}</span
                    >
                    <span :class="s.current ? 'font-semibold' : 'text-ink-muted'">{{ s.label }}</span>
                </li>
            </ol>

            <p v-if="w.note" class="rounded-card bg-danger/10 px-4 py-3 text-sm text-danger">{{ w.note }}</p>
            <p v-if="w.order_id" class="rounded-card bg-success/10 px-4 py-3 text-sm text-success">
                Recorded in the club finance {{ w.completed_at ? dateTime(w.completed_at) : '' }} · transaction <span class="font-mono font-semibold">{{ w.order_id }}</span>
            </p>

            <!-- What to do next, for whoever is looking -->
            <section v-if="can.sign" class="grid gap-3 rounded-card border-2 border-brand-cyan/40 bg-brand-cyan/5 p-4">
                <h2 class="font-semibold">Your signature as Treasurer</h2>
                <p class="text-sm">Check the items and bank details below, then sign. It goes to the Advisor next.</p>
                <Button class="justify-self-start" @click="signing = true">Check & sign</Button>
            </section>
            <section v-if="can.sld && w.stage === 'awaiting_finance'" class="grid gap-3 rounded-card border-2 border-brand-cyan/40 bg-brand-cyan/5 p-4">
                <h2 class="font-semibold">SLD: send to Finance</h2>
                <p class="text-sm">1. Download the 07 form and pass it to Finance to sign. 2. Upload Finance's signed copy here.</p>
                <div class="flex flex-wrap gap-2">
                    <a v-if="w.files.form" :href="w.files.form"
                        ><Button variant="secondary"><Icon name="file" :size="16" /> Download 07 form (PDF)</Button></a
                    >
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-control bg-ink px-4 text-sm font-medium text-white h-10">
                        {{ upload.processing ? 'Uploading…' : 'Upload Finance-signed copy'
                        }}<input type="file" accept="application/pdf,image/*" class="sr-only" @change="send('finance-signed', $event)" />
                    </label>
                </div>
            </section>
            <section v-if="can.bankin" class="grid gap-3 rounded-card border-2 border-brand-cyan/40 bg-brand-cyan/5 p-4">
                <h2 class="font-semibold">Treasurer: did the money arrive?</h2>
                <p class="text-sm">When {{ rm(w.total_sen) }} reaches {{ w.bank?.account_name }} ({{ w.bank?.bank_name }}), upload the bank-in receipt or statement.</p>
                <div class="flex flex-wrap items-end gap-2">
                    <Input v-model="upload.reference" label="Bank reference (optional)" class="w-56" />
                    <label class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-control bg-ink px-4 text-sm font-medium text-white">
                        {{ upload.processing ? 'Uploading…' : 'Upload bank-in proof'
                        }}<input type="file" accept="application/pdf,image/*" class="sr-only" @change="send('bank-in', $event)" />
                    </label>
                </div>
            </section>
            <section v-if="can.sld && w.stage === 'bankin_uploaded'" class="grid gap-3 rounded-card border-2 border-brand-cyan/40 bg-brand-cyan/5 p-4">
                <h2 class="font-semibold">SLD: confirm the bank-in</h2>
                <p class="text-sm">Open the proof below. Confirming records the withdrawal in the club finance; the balance changes only now.</p>
                <div class="flex flex-wrap gap-2">
                    <Button @click="complete">Confirm & record</Button>
                    <Button variant="secondary" @click="sendBack">Send back</Button>
                </div>
            </section>
            <p v-if="upload.errors.file" class="text-sm text-danger">{{ upload.errors.file }}</p>

            <section class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card">
                <h2 class="font-semibold">Items</h2>
                <ul class="grid gap-1 text-sm">
                    <li v-for="(i, n) in w.items" :key="n" class="flex justify-between gap-3">
                        <span
                            >{{ i.description }} <span class="text-ink-muted">× {{ i.quantity }}</span></span
                        ><span class="tabular-nums">{{ rm(i.amount_sen) }}</span>
                    </li>
                </ul>
                <p class="flex justify-between border-t border-line pt-2 font-semibold">
                    <span>Total</span><span class="tabular-nums">{{ rm(w.total_sen) }}</span>
                </p>
                <dl v-if="w.bank" class="grid gap-1 rounded-control bg-surface-muted p-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-ink-muted">Bank</dt>
                        <dd class="font-medium">{{ w.bank.bank_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-ink-muted">Account name</dt>
                        <dd class="font-medium">{{ w.bank.account_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-ink-muted">Account number</dt>
                        <dd class="font-mono font-medium">{{ w.bank.account_number }}</dd>
                    </div>
                </dl>
            </section>

            <section class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card">
                <h2 class="font-semibold">Evidence</h2>
                <div class="flex flex-wrap gap-2 text-sm">
                    <a v-if="w.files.form" :href="w.files.form" class="inline-flex items-center gap-1.5 rounded-control border border-line px-3 py-2 hover:bg-surface-muted"
                        ><Icon name="file" :size="16" /> 07 form</a
                    >
                    <a
                        v-if="w.files.finance"
                        :href="w.files.finance"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 rounded-control border border-line px-3 py-2 hover:bg-surface-muted"
                        ><Icon name="check" :size="16" /> Finance-signed copy</a
                    >
                    <a
                        v-if="w.files.bankin"
                        :href="w.files.bankin"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 rounded-control border border-line px-3 py-2 hover:bg-surface-muted"
                        ><Icon name="receipt" :size="16" /> Bank-in proof<template v-if="w.bankin_reference"> · {{ w.bankin_reference }}</template></a
                    >
                    <span v-if="!Object.keys(w.files).length" class="text-ink-muted">Files appear here as each step is done.</span>
                </div>
            </section>

            <section v-if="approval" class="rounded-card border border-line bg-surface p-4 shadow-card">
                <ApprovalTracker :progress="approval.progress" :steps="approval.steps" />
            </section>
        </div>

        <Modal v-model:open="signing" title="Check the bank details, then sign">
            <div class="grid gap-4 text-sm">
                <dl v-if="w.bank" class="grid gap-2 rounded-card border-2 border-warning/50 bg-warning/5 p-4">
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">Bank</dt>
                        <dd class="font-semibold">{{ w.bank.bank_name }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">Account name</dt>
                        <dd class="font-semibold">{{ w.bank.account_name }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">Account number</dt>
                        <dd class="font-mono text-base font-bold tracking-wider">{{ w.bank.account_number }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t border-line pt-2">
                        <dt class="text-ink-muted">Amount</dt>
                        <dd class="font-semibold">{{ rm(w.total_sen) }}</dd>
                    </div>
                </dl>
                <SignaturePad v-model="sign.signature" v-model:strokes="sign.signature_strokes" label="Your signature" />
                <p v-if="Object.keys(sign.errors).length" class="text-danger">{{ Object.values(sign.errors)[0] }}</p>
                <Button :disabled="!sign.signature" :loading="sign.processing" @click="doSign">The bank details are correct · sign</Button>
            </div>
        </Modal>
    </AppLayout>
</template>
