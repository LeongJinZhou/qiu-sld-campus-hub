<script setup lang="ts">
import FormSection from '@/Components/forms/FormSection.vue';
import LockedField from '@/Components/forms/LockedField.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Modal from '@/Components/ui/Modal.vue';
import Select from '@/Components/ui/Select.vue';
import SignaturePad from '@/Components/ui/SignaturePad.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { date, rm } from '@/lib/format';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    club: { slug: string; name: string };
    events: { id: number; name: string; date: string; lines: { description: string; unit_sen: number; quantity: number; amount_sen: number }[] }[];
    payees: { id: number; label: string }[];
    selectedEvent: number | null;
    earliestWeekly: string;
    secretary: string | null;
}>();

const form = useForm({
    event_id: props.selectedEvent as number | null,
    purpose: '',
    needed_by: '',
    items: [] as { description: string; unit_sen: number; quantity: number; method: 'vendor' | 'self' }[],
    payees: [] as { payee_id: number | null; amount_sen: number }[],
    signature: null as string | null,
    signature_strokes: null as string | null,
});
const event = computed(() => props.events.find((e) => e.id === form.event_id) ?? null);
// Items come from the approved budget; pre-fill all of them when an event is picked.
watch(event, (e) => {
    form.items = e ? e.lines.map((l) => ({ description: l.description, unit_sen: l.unit_sen, quantity: l.quantity, method: 'vendor' as const })) : [];
    if (e) form.purpose = `the ${e.name} event`;
}, { immediate: true });
const total = computed(() => form.items.reduce((s, i) => s + i.unit_sen * Number(i.quantity || 0), 0));
const payeeTotal = computed(() => form.payees.reduce((s, p) => s + Number(p.amount_sen || 0), 0));
const signing = ref(false);
const submit = (): void => form.post(`/clubs/${props.club.slug}/fund-requests`, { onFinish: () => (signing.value = false) });
const setUnit = (row: { unit_sen: number }, value: string): void => {
    row.unit_sen = Math.round(Number(value || 0) * 100);
};
const setPayee = (row: { amount_sen: number }, value: string): void => {
    row.amount_sen = Math.round(Number(value || 0) * 100);
};
</script>

<template>
    <Head title="Fund withdrawal" />
    <AppLayout>
        <div class="grid gap-6">
            <header>
                <h1 class="text-xl font-bold">Fund Withdrawal / Transfer</h1>
                <p class="text-sm text-ink-muted">07 template · {{ club.name }} · Prepared by you → President → Advisor → Assistant Registrar → Bursar (endorse) → COO</p>
            </header>

            <FormSection title="Request">
                <div class="grid gap-4 sm:grid-cols-3">
                    <LockedField label="To" value="Student Life Division" />
                    <LockedField label="From" :value="club.name" />
                    <LockedField label="CC" value="Accounts and Finance Division" />
                </div>
                <Select v-model="form.event_id" label="For" :options="[{ value: '', label: 'Weekly activity (no event)' }, ...events.map((e) => ({ value: e.id, label: `${e.name} · ${e.date}` }))]" />
                <template v-if="!event">
                    <Input v-model="form.purpose" label="To cover" placeholder="weekly training equipment" :error="form.errors.purpose" />
                    <Input v-model="form.needed_by" type="date" label="Money needed by" :hint="`At least 2 weeks ahead: ${date(earliestWeekly)} or later`" :error="form.errors.needed_by" />
                </template>
            </FormSection>

            <FormSection title="Items" :hint="event ? 'From the approved budget. Lower an amount or remove lines you do not need now.' : undefined">
                <div v-for="(item, i) in form.items" :key="i" class="grid gap-2 sm:grid-cols-[1fr_8rem_6rem_7rem_11rem_auto] sm:items-end">
                    <Input v-model="item.description" label="Item" :readonly="!!event" />
                    <Input :model-value="(item.unit_sen / 100).toFixed(2)" type="number" label="Unit price (RM)" :readonly="!!event" @update:model-value="setUnit(item, String($event))" />
                    <Input v-model="item.quantity" type="number" label="Quantity" />
                    <LockedField label="Total (RM)" :value="((item.unit_sen * Number(item.quantity || 0)) / 100).toFixed(2)" />
                    <Select v-model="item.method" label="Payment method" :options="[{ value: 'vendor', label: 'QIU to vendor' }, { value: 'self', label: 'Student self purchase' }]" />
                    <button type="button" class="pb-2 text-ink-muted hover:text-danger" aria-label="Remove" @click="form.items.splice(i, 1)">✕</button>
                </div>
                <button v-if="!event" type="button" class="justify-self-start text-sm font-medium text-brand-blue" @click="form.items.push({ description: '', unit_sen: 0, quantity: 1, method: 'vendor' })">＋ Add item</button>
                <p class="text-right font-semibold">Total {{ rm(total) }}</p>
            </FormSection>

            <FormSection title="Transfer to" :hint="`Vendor accounts for “QIU to vendor”, the treasurer account for “Student self purchase”. Amounts must add up to ${rm(total)}.`">
                <div v-for="(p, i) in form.payees" :key="i" class="grid gap-2 sm:grid-cols-[1fr_10rem_auto] sm:items-end">
                    <Select v-model="p.payee_id" label="Payee" :options="payees.map((x) => ({ value: x.id, label: x.label }))" placeholder="Choose" />
                    <Input :model-value="(p.amount_sen / 100).toFixed(2)" type="number" label="Amount (RM)" @update:model-value="setPayee(p, String($event))" />
                    <button type="button" class="pb-2 text-ink-muted hover:text-danger" aria-label="Remove" @click="form.payees.splice(i, 1)">✕</button>
                </div>
                <button type="button" class="justify-self-start text-sm font-medium text-brand-blue" @click="form.payees.push({ payee_id: null, amount_sen: 0 })">＋ Add payee</button>
                <p class="text-sm" :class="payeeTotal === total ? 'text-success' : 'text-warning'">Payees {{ rm(payeeTotal) }} / total {{ rm(total) }}</p>
                <p class="text-xs text-ink-muted">Save new treasurer or vendor accounts on the club's Finance page first.</p>
            </FormSection>

            <div class="flex justify-end">
                <Button :disabled="!form.items.length || payeeTotal !== total" @click="signing = true">Sign &amp; submit</Button>
            </div>
        </div>

        <Modal v-model:open="signing" title="Prepared by">
            <div class="grid gap-4">
                <p class="text-sm text-ink-muted">Any committee member can prepare and submit. Your name and position go on the form as "Prepared by". Date and time come from the server.</p>
                <SignaturePad v-model="form.signature" v-model:strokes="form.signature_strokes" label="Signature" />
                <p v-if="Object.keys(form.errors).length" class="text-sm text-danger">{{ Object.values(form.errors)[0] }}</p>
                <Button :disabled="!form.signature" :loading="form.processing" @click="submit">Submit</Button>
            </div>
        </Modal>
    </AppLayout>
</template>
