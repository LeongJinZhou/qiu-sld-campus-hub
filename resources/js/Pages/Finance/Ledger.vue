<script setup lang="ts">
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import Icon from '@/Components/ui/Icon.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { date, rm } from '@/lib/format';
import Badge from '@/Components/ui/Badge.vue';
import Modal from '@/Components/ui/Modal.vue';
import { copy } from '@/lib/clone';
import type { ApprovalProgress } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';

interface Row {
    id: number;
    date: string;
    description: string;
    income_sen: number;
    expense_sen: number;
    balance_sen: number;
    category: string;
    event_id: number | null;
    event: string | null;
    method: string | null;
    handled_by: string | null;
    receipt_url: string | null;
    payment_receipt_url: string | null;
    photo_url: string | null;
    document_url: string | null;
}
interface Filters {
    session?: string;
    category?: string;
    event_id?: number | string;
    method?: string;
    search?: string;
    from?: string;
    to?: string;
    sort?: string;
}

const props = defineProps<{
    club: { slug: string; name: string };
    ready: boolean;
    opening: { amount_sen: number; as_of: string; note: string | null } | null;
    report: {
        session: string;
        brought_forward_sen: number;
        closing_sen: number;
        totals: { income_sen: number; expense_sen: number };
        by_category: { key: string; label: string; income_sen: number; expense_sen: number; count: number }[];
        by_event: { event_id: number; event: string; income_sen: number; expense_sen: number; count: number }[];
        rows: Row[];
        filtered: boolean;
    };
    filters: Filters;
    sessions: string[];
    categories: Record<string, string>;
    methods: Record<string, string>;
    clubEvents: { value: number; label: string }[];
    balance_sen: number;
    payees: { id: number; kind: string; name: string; bank_name: string; account: string }[];
    requests: { id: number; event: string | null; purpose: string | null; total_sen: number; status: string; progress: ApprovalProgress }[];
    events: { id: number; name: string }[];
    fee: { amount_sen: number; payment_url: string | null; account_name: string | null; approved: boolean; qr: { path: string; url: string }[] } | null;
    can: { edit: boolean; sld: boolean };
}>();

const url = `/clubs/${props.club.slug}/finance`;
// Filters live in the address bar, so a filtered view can be shared and the export matches the screen.
const f = reactive<Filters>({ category: '', event_id: '', method: '', search: '', from: '', to: '', sort: 'date_asc', session: props.report.session, ...copy(props.filters) });
const apply = (changes: Filters = {}): void => {
    Object.assign(f, changes);
    const query = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== undefined && v !== null && v !== ''));
    router.get(url, query, { preserveScroll: true, preserveState: true, replace: true, only: ['report', 'filters'] });
};
watchDebounced(() => f.search, () => apply(), { debounce: 350 });
const clear = (): void => router.get(url, { session: f.session }, { preserveScroll: true });
const exportUrl = (format: string): string => {
    const query = new URLSearchParams(Object.entries(f).filter(([, v]) => v !== undefined && v !== null && v !== '').map(([k, v]) => [k, String(v)]));
    return `${url}/export/${format}?${query}`;
};

const tone: Record<string, 'info' | 'success' | 'warning' | 'neutral'> = { membership: 'success', event: 'info', withdrawal: 'warning', other: 'neutral' };
const net = (r: { income_sen: number; expense_sen: number }): number => r.income_sen - r.expense_sen;
const showBalance = computed(() => !props.report.filtered && (f.sort ?? 'date_asc') === 'date_asc');

const openingForm = useForm({ amount_rm: props.opening ? props.opening.amount_sen / 100 : 0, as_of: props.opening?.as_of ?? new Date().toISOString().slice(0, 10), note: props.opening?.note ?? '' });
const entry = useForm({ date: new Date().toISOString().slice(0, 10), description: '', kind: 'expense', amount_rm: '', event_id: '' as number | '', method: 'cash', receipt: [] as File[], original: false });
const addEntry = (): void => {
    entry.transform((d) => ({ ...d, receipt: d.receipt[0] ?? null })).post(`${url}/entries`, { preserveScroll: true, onSuccess: () => entry.reset('description', 'amount_rm', 'receipt', 'original') });
};
const attaching = ref<number | null>(null);
const attachForm = useForm({ receipt: [] as File[] });
const attach = (): void => {
    attachForm.transform((d) => ({ receipt: d.receipt[0] ?? null })).post(`${url}/entries/${attaching.value}/receipt`, { preserveScroll: true, forceFormData: true, onSuccess: () => ((attaching.value = null), attachForm.reset()) });
};
const payee = useForm({ kind: 'vendor', name: '', email: '', contact_no: '', bank_name: '', account_holder: '', account_number: '' });
// Membership fee for this session: any committee member sets it, SLD approves before students can join.
const feeForm = useForm({ amount_rm: (props.fee?.amount_sen ?? 0) / 100, payment_url: props.fee?.payment_url ?? '', account_name: props.fee?.account_name ?? '', qr: [] as File[], remove_qr: [] as string[] });
const toggleFeeQr = (path: string): void => {
    feeForm.remove_qr = feeForm.remove_qr.includes(path) ? feeForm.remove_qr.filter((p) => p !== path) : [...feeForm.remove_qr, path];
};
const saveFee = (): void => {
    feeForm.post(`/clubs/${props.club.slug}/fee`, { preserveScroll: true, forceFormData: true, onSuccess: () => feeForm.reset('qr', 'remove_qr') });
};
const options = (rec: Record<string, string>) => [{ value: '', label: 'All' }, ...Object.entries(rec).map(([value, label]) => ({ value, label }))];
</script>

<template>
    <Head :title="`Finance · ${club.name}`" />
    <AppLayout wide>
        <div class="grid gap-6">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold">Finance · {{ club.name }}</h1>
                    <Link :href="`/clubs/${club.slug}`" class="text-sm text-brand-blue">Back to club</Link>
                </div>
                <div class="text-right">
                    <p class="text-xs uppercase tracking-wide text-ink-muted">Balance</p>
                    <p class="text-3xl font-bold tabular-nums" :class="balance_sen < 0 ? 'text-danger' : ''">{{ rm(balance_sen) }}</p>
                </div>
            </header>

            <Card v-if="can.sld">
                <template #title>Opening balance (SLD only)</template>
                <form class="grid gap-3 sm:grid-cols-[10rem_12rem_1fr_auto] sm:items-end" @submit.prevent="openingForm.post(`/clubs/${club.slug}/finance/opening`, { preserveScroll: true })">
                    <Input v-model="openingForm.amount_rm" type="number" label="Amount (RM)" :error="openingForm.errors.amount_rm" />
                    <Input v-model="openingForm.as_of" type="date" label="As of" :error="openingForm.errors.as_of" />
                    <Input v-model="openingForm.note" label="Note" />
                    <Button type="submit" :loading="openingForm.processing">{{ props.opening ? 'Update' : 'Open ledger' }}</Button>
                </form>
                <p class="mt-2 text-xs text-ink-muted">Every change is recorded in the audit log.</p>
            </Card>

            <EmptyState v-if="!ready" title="Finance not opened yet" description="SLD must enter this club's opening balance before the ledger can be used." />

            <template v-else>
                <!-- Year at a glance: built automatically from every transaction. -->
                <section class="grid gap-3">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div class="flex items-end gap-2">
                            <Select v-model="f.session" label="Session" :options="sessions.map((s) => ({ value: s, label: s }))" @update:model-value="apply()" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a :href="exportUrl('xlsx')"><Button variant="secondary"><Icon name="file" :size="16" /> 02 Finance Record (Excel)</Button></a>
                            <a :href="exportUrl('pdf')"><Button variant="secondary">PDF</Button></a>
                            <Link :href="`/clubs/${club.slug}/fund-requests/create`"><Button>＋ Fund withdrawal (07)</Button></Link>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <div class="rounded-card border border-line bg-surface p-4 shadow-card"><p class="text-xs text-ink-muted">Brought forward (1 Jul)</p><p class="text-xl font-bold tabular-nums">{{ rm(report.brought_forward_sen) }}</p></div>
                        <div class="rounded-card border border-line bg-surface p-4 shadow-card"><p class="text-xs text-ink-muted">Income {{ report.session }}</p><p class="text-xl font-bold tabular-nums text-success">{{ rm(report.totals.income_sen) }}</p></div>
                        <div class="rounded-card border border-line bg-surface p-4 shadow-card"><p class="text-xs text-ink-muted">Expenses {{ report.session }}</p><p class="text-xl font-bold tabular-nums text-danger">{{ rm(report.totals.expense_sen) }}</p></div>
                        <div class="rounded-card border border-line bg-surface p-4 shadow-card"><p class="text-xs text-ink-muted">Closing balance</p><p class="text-xl font-bold tabular-nums" :class="report.closing_sen < 0 ? 'text-danger' : ''">{{ rm(report.closing_sen) }}</p></div>
                    </div>
                    <div class="grid gap-3 lg:grid-cols-2">
                        <Card>
                            <template #title>By type</template>
                            <ul class="grid gap-1 text-sm">
                                <li v-for="c in report.by_category" :key="c.key">
                                    <button type="button" class="grid w-full grid-cols-[1fr_auto_auto] items-center gap-3 rounded-control px-2 py-2 text-left hover:bg-surface-muted" :class="f.category === c.key ? 'bg-surface-muted' : ''" @click="apply({ category: f.category === c.key ? '' : c.key, event_id: '' })">
                                        <span class="flex items-center gap-2"><Badge :tone="tone[c.key] ?? 'neutral'">{{ c.label }}</Badge><span class="text-xs text-ink-muted">{{ c.count }} {{ c.count === 1 ? 'item' : 'items' }}</span></span>
                                        <span class="text-right tabular-nums text-success">{{ c.income_sen ? rm(c.income_sen) : '' }}</span>
                                        <span class="w-24 text-right tabular-nums text-danger">{{ c.expense_sen ? `− ${rm(c.expense_sen)}` : '' }}</span>
                                    </button>
                                </li>
                            </ul>
                        </Card>
                        <Card>
                            <template #title>By event</template>
                            <ul v-if="report.by_event.length" class="grid gap-1 text-sm">
                                <li v-for="e in report.by_event" :key="e.event_id">
                                    <button type="button" class="grid w-full grid-cols-[1fr_auto] items-center gap-3 rounded-control px-2 py-2 text-left hover:bg-surface-muted" :class="Number(f.event_id) === e.event_id ? 'bg-surface-muted' : ''" @click="apply({ event_id: Number(f.event_id) === e.event_id ? '' : e.event_id, category: '' })">
                                        <span class="min-w-0"><span class="block truncate font-medium">{{ e.event }}</span><span class="text-xs text-ink-muted">{{ e.count }} {{ e.count === 1 ? 'item' : 'items' }} · in {{ rm(e.income_sen) }} · out {{ rm(e.expense_sen) }}</span></span>
                                        <span class="text-right font-semibold tabular-nums" :class="net(e) < 0 ? 'text-danger' : 'text-success'">{{ rm(net(e)) }}</span>
                                    </button>
                                </li>
                            </ul>
                            <p v-else class="text-sm text-ink-muted">No event transactions this session yet. Fund withdrawals and event expenses appear here automatically.</p>
                        </Card>
                    </div>
                </section>

                <section class="grid gap-3">
                    <div class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card sm:grid-cols-2 lg:grid-cols-4">
                        <div class="sm:col-span-2"><Input v-model="f.search" label="Search" placeholder="Description, event or name" /></div>
                        <Select v-model="f.category" label="Type" :options="options(categories)" @update:model-value="apply()" />
                        <Select v-model="f.event_id" label="Event" :options="[{ value: '', label: 'All' }, ...clubEvents]" @update:model-value="apply()" />
                        <Select v-model="f.method" label="Paid by" :options="options(methods)" @update:model-value="apply()" />
                        <Input v-model="f.from" type="date" label="From" @change="apply()" />
                        <Input v-model="f.to" type="date" label="To" @change="apply()" />
                        <Select v-model="f.sort" label="Sort" :options="[{ value: 'date_asc', label: 'Oldest first' }, { value: 'date_desc', label: 'Newest first' }, { value: 'amount_desc', label: 'Largest first' }, { value: 'amount_asc', label: 'Smallest first' }]" @update:model-value="apply()" />
                    </div>
                    <p class="flex flex-wrap items-center gap-2 text-sm text-ink-muted">
                        {{ report.rows.length }} {{ report.rows.length === 1 ? 'transaction' : 'transactions' }}<template v-if="report.filtered"> match · <button type="button" class="font-medium text-brand-blue" @click="clear">Clear filters</button></template>
                        · the Excel/PDF export prints exactly this view.
                    </p>

                    <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-muted text-left text-xs uppercase tracking-wide text-ink-muted">
                                <tr>
                                    <th class="px-3 py-3 font-medium">Date</th><th class="px-3 py-3 font-medium">Description</th><th class="px-3 py-3 font-medium">For</th>
                                    <th class="px-3 py-3 font-medium">Paid by · received by</th><th class="px-3 py-3 text-right font-medium">Income</th><th class="px-3 py-3 text-right font-medium">Expense</th>
                                    <th v-if="showBalance" class="px-3 py-3 text-right font-medium">Balance</th><th class="px-3 py-3 font-medium">Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in report.rows" :key="r.id" class="border-t border-line align-top">
                                    <td class="whitespace-nowrap px-3 py-2.5 tabular-nums">{{ date(r.date) }}</td>
                                    <td class="min-w-44 px-3 py-2.5">{{ r.description }}</td>
                                    <td class="px-3 py-2.5"><Badge :tone="tone[r.category] ?? 'neutral'">{{ categories[r.category] ?? r.category }}</Badge><span v-if="r.event" class="mt-1 block text-xs text-ink-muted">{{ r.event }}</span></td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-xs">{{ r.method ? methods[r.method] ?? r.method : '—' }}<span v-if="r.handled_by" class="block text-ink-muted">{{ r.handled_by }}</span></td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-success">{{ r.income_sen ? rm(r.income_sen) : '' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-danger">{{ r.expense_sen ? rm(r.expense_sen) : '' }}</td>
                                    <td v-if="showBalance" class="whitespace-nowrap px-3 py-2.5 text-right font-medium tabular-nums">{{ rm(r.balance_sen) }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-xs">
                                        <div class="grid gap-1">
                                            <a v-if="r.photo_url" :href="r.photo_url" target="_blank" class="inline-flex items-center gap-1 font-medium text-brand-blue" title="Photo taken at the cash handover"><Icon name="camera" :size="14" /> Photo</a>
                                            <a v-if="r.payment_receipt_url" :href="r.payment_receipt_url" target="_blank" class="inline-flex items-center gap-1 font-medium text-brand-blue" title="The student's online payment receipt"><Icon name="file" :size="14" /> Receipt</a>
                                            <a v-if="r.receipt_url" :href="r.receipt_url" target="_blank" class="inline-flex items-center gap-1 font-medium text-brand-blue"><Icon name="file" :size="14" /> Receipt</a>
                                            <Link v-if="r.document_url" :href="r.document_url" class="inline-flex items-center gap-1 font-medium text-brand-blue"><Icon name="check" :size="14" /> 07 form</Link>
                                            <button v-if="can.edit && !r.receipt_url && r.expense_sen" type="button" class="text-left font-medium text-warning" @click="attaching = r.id">＋ Receipt</button>
                                            <span v-if="!r.photo_url && !r.payment_receipt_url && !r.receipt_url && !r.document_url && !(can.edit && r.expense_sen)" class="text-ink-muted">—</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!report.rows.length"><td colspan="8" class="px-3 py-8 text-center text-ink-muted">No transactions match.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <Card v-if="can.edit">
                    <template #title>Add a transaction</template>
                    <p class="mb-3 text-sm text-ink-muted">Membership fees and approved fund withdrawals are recorded automatically. Add anything else here, under the event it belongs to.</p>
                    <form class="grid gap-3" @submit.prevent="addEntry">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[10rem_1fr_9rem_10rem]">
                            <Input v-model="entry.date" type="date" label="Date" :error="entry.errors.date" />
                            <Input v-model="entry.description" label="Description" :error="entry.errors.description" />
                            <Select v-model="entry.kind" label="Type" :options="[{ value: 'expense', label: 'Expense' }, { value: 'income', label: 'Income' }]" />
                            <Input v-model="entry.amount_rm" type="number" label="Amount (RM)" :error="entry.errors.amount_rm" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <Select v-model="entry.event_id" label="For event (optional)" :options="[{ value: '', label: 'Not for an event' }, ...clubEvents]" :error="entry.errors.event_id" />
                            <Select v-model="entry.method" label="Paid by" :options="Object.entries(methods).map(([value, label]) => ({ value, label }))" />
                        </div>
                        <template v-if="entry.kind === 'expense'">
                            <FileUpload v-model="entry.receipt" label="Receipt (required for expenses)" accept="image/jpeg,image/png,image/webp,application/pdf" :error="entry.errors.receipt" />
                            <Checkbox v-model="entry.original" label="This is the original receipt (bank-transfer screenshots are not accepted)." />
                            <p v-if="entry.errors.original" class="text-xs text-danger">{{ entry.errors.original }}</p>
                        </template>
                        <div><Button type="submit" :loading="entry.processing">Add transaction</Button></div>
                    </form>
                </Card>
            </template>

            <Card v-if="can.edit">
                <template #title>Membership fee &amp; payment</template>
                <p class="mb-3 text-sm text-ink-muted">
                    <template v-if="!fee">No fee set for this session yet. Students can join once SLD approves it.</template>
                    <template v-else>Current fee {{ rm(fee.amount_sen) }} · <Badge :tone="fee.approved ? 'success' : 'warning'">{{ fee.approved ? 'Approved by SLD' : 'Waiting for SLD' }}</Badge></template>
                    Changing it sends it back to SLD for approval.
                </p>
                <form class="grid gap-3" @submit.prevent="saveFee">
                    <div class="grid gap-3 sm:grid-cols-[10rem_1fr_1fr]">
                        <Input v-model="feeForm.amount_rm" type="number" label="Fee (RM, 0 = free)" :error="feeForm.errors.amount_rm" />
                        <Input v-model="feeForm.account_name" label="Pay to (account name)" :error="feeForm.errors.account_name" />
                        <Input v-model="feeForm.payment_url" label="Payment link" placeholder="https://…" :error="feeForm.errors.payment_url" />
                    </div>
                    <div v-if="fee?.qr.length" class="flex flex-wrap gap-2">
                        <span v-for="q in fee.qr" :key="q.path" class="relative">
                            <img :src="q.url" alt="Payment QR" class="size-20 rounded-control border border-line object-cover" :class="feeForm.remove_qr.includes(q.path) ? 'opacity-30' : ''" />
                            <button type="button" class="absolute -right-1 -top-1 grid size-5 place-items-center rounded-full bg-ink text-xs text-white" :aria-label="feeForm.remove_qr.includes(q.path) ? 'Keep this QR' : 'Remove this QR'" @click="toggleFeeQr(q.path)">{{ feeForm.remove_qr.includes(q.path) ? '↺' : '×' }}</button>
                        </span>
                    </div>
                    <FileUpload v-if="(fee?.qr.length ?? 0) - feeForm.remove_qr.length < 2" v-model="feeForm.qr" multiple label="Your own payment QR codes (up to 2, optional — a QR of the link is generated anyway)" accept="image/png,image/jpeg,image/webp" :error="feeForm.errors.qr" />
                    <div><Button type="submit" :loading="feeForm.processing">Save and send to SLD</Button></div>
                </form>
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <template #title>Fund withdrawals (07)</template>
                    <EmptyState v-if="!requests.length" title="No withdrawal requests yet" />
                    <ul class="grid gap-3">
                        <li v-for="r in requests" :key="r.id">
                            <Link :href="`/documents/${r.id}`" class="grid gap-2 rounded-control border border-line p-3 text-sm hover:shadow-card">
                                <span class="flex justify-between gap-3"><strong>{{ r.event ?? r.purpose }}</strong><span class="tabular-nums">{{ rm(r.total_sen) }}</span></span>
                                <ApprovalTracker :progress="r.progress" compact />
                            </Link>
                        </li>
                    </ul>
                </Card>
                <Card>
                    <template #title>Payees</template>
                    <ul class="mb-4 grid gap-1 text-sm">
                        <li v-for="p in payees" :key="p.id" class="flex justify-between gap-3"><span><strong>{{ p.name }}</strong> <span class="text-ink-muted">({{ p.kind }})</span></span><span class="text-ink-muted">{{ p.bank_name }} {{ p.account }}</span></li>
                        <li v-if="!payees.length" class="text-ink-muted">No payees yet.</li>
                    </ul>
                    <form v-if="can.edit" class="grid gap-3 sm:grid-cols-2" @submit.prevent="payee.post(`/clubs/${club.slug}/finance/payees`, { preserveScroll: true, onSuccess: () => payee.reset() })">
                        <Select v-model="payee.kind" label="Type" :options="[{ value: 'vendor', label: 'Vendor' }, { value: 'treasurer', label: 'Treasurer account' }]" />
                        <Input v-model="payee.name" label="Individual/Company name" :error="payee.errors.name" />
                        <Input v-model="payee.email" type="email" label="Email" :error="payee.errors.email" />
                        <Input v-model="payee.contact_no" label="Contact no." :error="payee.errors.contact_no" />
                        <Input v-model="payee.bank_name" label="Bank" :error="payee.errors.bank_name" />
                        <Input v-model="payee.account_holder" label="Account holder" :error="payee.errors.account_holder" />
                        <Input v-model="payee.account_number" label="Account number" :error="payee.errors.account_number" />
                        <Button type="submit" class="self-end" :loading="payee.processing">Save payee</Button>
                    </form>
                    <p class="mt-2 text-xs text-ink-muted">Bank details are encrypted and only printed on the official form.</p>
                </Card>
            </div>
        </div>
        <Modal :open="attaching !== null" title="Add the original receipt" @update:open="(v: boolean) => v || (attaching = null)">
            <form class="grid gap-4" @submit.prevent="attach">
                <FileUpload v-model="attachForm.receipt" label="Receipt" accept="image/jpeg,image/png,image/webp,application/pdf" :error="attachForm.errors.receipt" />
                <Button type="submit" :loading="attachForm.processing" :disabled="!attachForm.receipt.length">Attach receipt</Button>
            </form>
        </Modal>
    </AppLayout>
</template>
