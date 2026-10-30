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
import type { ApprovalProgress } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    club: { slug: string; name: string };
    ready: boolean;
    opening: { amount_sen: number; as_of: string; note: string | null } | null;
    rows: { id: number | null; date: string; description: string; income_sen: number; expense_sen: number; balance_sen: number; has_receipt: boolean; receipt_id: number | null; receipt_url: string | null }[];
    balance_sen: number;
    payees: { id: number; kind: string; name: string; bank_name: string; account: string }[];
    requests: { id: number; event: string | null; purpose: string | null; total_sen: number; status: string; progress: ApprovalProgress }[];
    events: { id: number; name: string }[];
    can: { edit: boolean; sld: boolean };
}>();

const openingForm = useForm({ amount_rm: props.opening ? props.opening.amount_sen / 100 : 0, as_of: props.opening?.as_of ?? new Date().toISOString().slice(0, 10), note: props.opening?.note ?? '' });
const entry = useForm({ date: new Date().toISOString().slice(0, 10), description: '', kind: 'expense', amount_rm: '', receipt: [] as File[], original: false });
const addEntry = (): void => {
    entry.transform((d) => ({ ...d, receipt: d.receipt[0] ?? null })).post(`/clubs/${props.club.slug}/finance/entries`, { preserveScroll: true, onSuccess: () => entry.reset('description', 'amount_rm', 'receipt', 'original') });
};
const payee = useForm({ kind: 'vendor', name: '', email: '', contact_no: '', bank_name: '', account_holder: '', account_number: '' });
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
                <div class="flex flex-wrap gap-2">
                    <a :href="`/clubs/${club.slug}/finance/export/xlsx`"><Button variant="secondary" size="sm"><Icon name="file" :size="16" /> 02 Finance Record (Excel)</Button></a>
                    <a :href="`/clubs/${club.slug}/finance/export/pdf`"><Button variant="secondary" size="sm">PDF</Button></a>
                    <Link :href="`/clubs/${club.slug}/fund-requests/create`"><Button size="sm">＋ Fund withdrawal (07)</Button></Link>
                </div>

                <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-muted text-left text-xs uppercase text-ink-muted">
                            <tr><th class="px-3 py-3">Date</th><th class="px-3 py-3">Description</th><th class="px-3 py-3 text-right">Income</th><th class="px-3 py-3 text-right">Expenses</th><th class="px-3 py-3 text-right">Balance</th><th class="px-3 py-3">Receipt</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, i) in rows" :key="i" class="border-t border-line" :class="r.id === null ? 'bg-surface-muted/60 font-medium' : ''">
                                <td class="whitespace-nowrap px-3 py-2 tabular-nums">{{ date(r.date) }}</td>
                                <td class="px-3 py-2">{{ r.description }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-success">{{ r.income_sen ? rm(r.income_sen) : '' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-danger">{{ r.expense_sen ? rm(r.expense_sen) : '' }}</td>
                                <td class="px-3 py-2 text-right font-medium tabular-nums">{{ rm(r.balance_sen) }}</td>
                                <td class="px-3 py-2"><a v-if="r.receipt_url" :href="r.receipt_url" target="_blank" class="text-brand-blue">View</a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Card v-if="can.edit">
                    <template #title>Add an entry</template>
                    <form class="grid gap-3" @submit.prevent="addEntry">
                        <div class="grid gap-3 sm:grid-cols-[10rem_1fr_9rem_10rem]">
                            <Input v-model="entry.date" type="date" label="Date" :error="entry.errors.date" />
                            <Input v-model="entry.description" label="Description" :error="entry.errors.description" />
                            <Select v-model="entry.kind" label="Type" :options="[{ value: 'expense', label: 'Expense' }, { value: 'income', label: 'Income' }]" />
                            <Input v-model="entry.amount_rm" type="number" label="Amount (RM)" :error="entry.errors.amount_rm" />
                        </div>
                        <template v-if="entry.kind === 'expense'">
                            <FileUpload v-model="entry.receipt" label="Receipt (required for expenses)" accept="image/jpeg,image/png,image/webp,application/pdf" :error="entry.errors.receipt" />
                            <Checkbox v-model="entry.original" label="This is the original receipt (bank-transfer screenshots are not accepted)." />
                            <p v-if="entry.errors.original" class="text-xs text-danger">{{ entry.errors.original }}</p>
                        </template>
                        <Button type="submit" class="justify-self-start" :loading="entry.processing">Add entry</Button>
                    </form>
                </Card>
            </template>

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
    </AppLayout>
</template>
