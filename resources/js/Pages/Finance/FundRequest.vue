<script setup lang="ts">
import FormSection from '@/Components/forms/FormSection.vue';
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

type Person = { id: number; name: string };
const props = defineProps<{
    club: { slug: string; name: string };
    events: { id: number; name: string; date: string; lines: { description: string; unit_sen: number; quantity: number; amount_sen: number }[] }[];
    selectedEvent: number | null;
    earliestWeekly: string;
    clubTreasurer: Person | null;
    eventTreasurers: Record<number, Person>;
    me: number;
}>();

const form = useForm({
    scope: (props.selectedEvent || props.events.length ? 'event' : 'club') as 'event' | 'club',
    event_id: props.selectedEvent as number | null,
    purpose: '',
    needed_by: '',
    items: [] as { description: string; unit_sen: number; quantity: number; method: 'vendor' | 'self' }[],
    bank: { bank_name: '', account_name: '', account_number: '' },
    bank_confirmed: false,
    signature: null as string | null,
    signature_strokes: null as string | null,
});
const event = computed(() => (form.scope === 'event' ? (props.events.find((e) => e.id === form.event_id) ?? null) : null));
// Event: items come from the approved budget (all pre-filled). Club: type the items.
watch(
    () => [form.scope, event.value?.id],
    () => {
        form.items = event.value
            ? event.value.lines.map((l) => ({ description: l.description, unit_sen: l.unit_sen, quantity: l.quantity, method: 'vendor' as const }))
            : form.scope === 'club'
              ? [{ description: '', unit_sen: 0, quantity: 1, method: 'self' as const }]
              : [];
    },
    { immediate: true },
);
const total = computed(() => form.items.reduce((s, i) => s + i.unit_sen * Number(i.quantity || 0), 0));
const treasurer = computed<Person | null>(() => (form.scope === 'event' ? (form.event_id ? (props.eventTreasurers[form.event_id] ?? null) : null) : props.clubTreasurer));
const iAmTreasurer = computed(() => treasurer.value?.id === props.me);
const setUnit = (row: { unit_sen: number }, value: string): void => {
    row.unit_sen = Math.round(Number(value.replace(/[^\d.]/g, '') || 0) * 100);
};
const ready = computed(
    () =>
        total.value > 0 &&
        form.bank.bank_name.trim() !== '' &&
        form.bank.account_name.trim() !== '' &&
        /^\d[\d\s-]{5,28}\d$/.test(form.bank.account_number.trim()) &&
        (form.scope === 'event' ? !!form.event_id : form.purpose.trim() !== '' && !!form.needed_by),
);

// Bank details are checked twice: once on the form, then on a confirmation screen before anything is sent.
const confirming = ref(false);
const send = (): void => {
    form.bank_confirmed = true;
    form.post(`/clubs/${props.club.slug}/fund-requests`, {
        onFinish: () => (confirming.value = false),
        onError: () => (form.bank_confirmed = false),
    });
};
</script>

<template>
    <Head title="Fund withdrawal" />
    <AppLayout>
        <div class="grid gap-6">
            <header>
                <h1 class="text-xl font-bold">Fund Withdrawal / Transfer</h1>
                <p class="text-sm text-ink-muted">
                    07 template · {{ club.name }} · Treasurer signs → Advisor → SLD → Finance signs → bank-in → Treasurer uploads proof → SLD confirms. You are told at every step.
                </p>
            </header>

            <FormSection title="What is it for?" required>
                <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Withdrawal for">
                    <button
                        v-for="o in [
                            { k: 'event', l: 'An event', d: 'Filed under the event; items from its approved budget' },
                            { k: 'club', l: 'The club', d: 'Weekly activities and other club costs; state the purpose' },
                        ] as const"
                        :key="o.k"
                        type="button"
                        role="radio"
                        :aria-checked="form.scope === o.k"
                        class="grid gap-1 rounded-card border p-3 text-left text-sm transition"
                        :class="form.scope === o.k ? 'border-brand-cyan bg-brand-cyan/10 ring-2 ring-brand-cyan/30' : 'border-line hover:bg-surface-muted'"
                        @click="form.scope = o.k"
                    >
                        <strong>{{ o.l }}</strong
                        ><span class="text-xs text-ink-muted">{{ o.d }}</span>
                    </button>
                </div>
                <template v-if="form.scope === 'event'">
                    <Select
                        v-model="form.event_id"
                        label="Event"
                        required
                        placeholder="Choose an approved event"
                        :options="events.map((e) => ({ value: e.id, label: `${e.name} · ${e.date}` }))"
                        :error="form.errors.event_id"
                    />
                    <p v-if="!events.length" class="text-sm text-warning">No approved events yet. A withdrawal for an event opens once its proposal is approved.</p>
                </template>
                <template v-else>
                    <Textarea v-model="form.purpose" label="Purpose" required :rows="2" placeholder="e.g. Weekly training balls and cones, November" :error="form.errors.purpose" />
                    <Input
                        v-model="form.needed_by"
                        type="date"
                        label="Money needed by"
                        required
                        :hint="`At least 2 weeks ahead: ${date(earliestWeekly)} or later`"
                        :error="form.errors.needed_by"
                    />
                </template>
            </FormSection>

            <FormSection title="Items" required :hint="form.scope === 'event' ? 'From the approved budget. Lower the amount if you need less.' : undefined">
                <div v-for="(item, i) in form.items" :key="i" class="grid grid-cols-[1fr_7rem_5rem_auto] items-end gap-2">
                    <Input v-model="item.description" :label="i === 0 ? 'Item' : undefined" :readonly="form.scope === 'event'" />
                    <Input
                        :model-value="item.unit_sen ? (item.unit_sen / 100).toFixed(2) : ''"
                        :label="i === 0 ? 'Price (RM)' : undefined"
                        @update:model-value="(v) => setUnit(item, String(v ?? ''))"
                    />
                    <Input v-model="item.quantity" type="number" :label="i === 0 ? 'Qty' : undefined" />
                    <button
                        v-if="form.scope === 'club' && form.items.length > 1"
                        type="button"
                        class="h-10 px-1 text-ink-muted hover:text-danger"
                        aria-label="Remove item"
                        @click="form.items.splice(i, 1)"
                    >
                        ✕
                    </button>
                    <span v-else />
                </div>
                <button
                    v-if="form.scope === 'club'"
                    type="button"
                    class="justify-self-start text-sm font-medium text-brand-blue"
                    @click="form.items.push({ description: '', unit_sen: 0, quantity: 1, method: 'self' })"
                >
                    ＋ Add item
                </button>
                <p class="flex justify-between border-t border-line pt-2 font-semibold">
                    <span>Total</span><span class="tabular-nums">{{ rm(total) }}</span>
                </p>
                <p v-if="form.errors.items" class="text-xs text-danger">{{ form.errors.items }}</p>
            </FormSection>

            <FormSection title="Bank account the money goes to" required hint="Finance banks the money into this account. Check every digit.">
                <div class="grid gap-3 sm:grid-cols-3">
                    <Input v-model="form.bank.bank_name" label="Bank" required placeholder="e.g. Maybank" :error="form.errors['bank.bank_name']" />
                    <Input v-model="form.bank.account_name" label="Account name" required placeholder="As printed by the bank" :error="form.errors['bank.account_name']" />
                    <Input v-model="form.bank.account_number" label="Account number" required placeholder="Digits only" :error="form.errors['bank.account_number']" />
                </div>
            </FormSection>

            <FormSection title="Treasurer's signature">
                <p v-if="!treasurer" class="text-sm text-warning">
                    {{ form.scope === 'event' ? 'This event has no Treasurer on its committee yet. Add one in the proposal first.' : 'The club has no Treasurer yet.' }}
                </p>
                <p v-else-if="iAmTreasurer" class="text-sm">
                    You are the {{ form.scope === 'event' ? 'event' : 'club' }} Treasurer: sign below and it goes straight to your Advisor.
                </p>
                <p v-else class="text-sm">
                    Only the {{ form.scope === 'event' ? 'event' : 'club' }} Treasurer, <strong>{{ treasurer.name }}</strong
                    >, can sign. When you send it, they are asked to check and sign.
                </p>
                <SignaturePad v-if="iAmTreasurer" v-model="form.signature" v-model:strokes="form.signature_strokes" label="Your signature" />
            </FormSection>

            <div class="flex justify-end">
                <Button size="lg" :disabled="!ready || !treasurer || (iAmTreasurer && !form.signature)" @click="confirming = true">
                    {{ iAmTreasurer ? 'Check bank details & sign' : `Check bank details & send to ${treasurer?.name ?? 'the Treasurer'}` }}
                </Button>
            </div>
        </div>

        <Modal v-model:open="confirming" title="Are these bank details correct?">
            <div class="grid gap-4 text-sm">
                <p class="text-ink-muted">Money sent to a wrong account is very hard to get back. Read each line once more.</p>
                <dl class="grid gap-2 rounded-card border-2 border-warning/50 bg-warning/5 p-4">
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">Bank</dt>
                        <dd class="text-right font-semibold">{{ form.bank.bank_name }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">Account name</dt>
                        <dd class="text-right font-semibold">{{ form.bank.account_name }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">Account number</dt>
                        <dd class="text-right font-mono text-base font-bold tracking-wider">{{ form.bank.account_number }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t border-line pt-2">
                        <dt class="text-ink-muted">Amount</dt>
                        <dd class="text-right font-semibold">{{ rm(total) }}</dd>
                    </div>
                </dl>
                <p v-if="Object.keys(form.errors).length" class="text-danger">{{ Object.values(form.errors)[0] }}</p>
                <div class="grid grid-cols-2 gap-2">
                    <Button variant="secondary" @click="confirming = false">No, let me fix it</Button>
                    <Button :loading="form.processing" @click="send">Yes, correct · {{ iAmTreasurer ? 'sign' : 'send' }}</Button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
