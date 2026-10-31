<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Button from '@/Components/ui/Button.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import Icon from '@/Components/ui/Icon.vue';
import QrCode from '@/Components/ui/QrCode.vue';
import { rm } from '@/lib/format';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

/** Pay online (link, payment QR codes, receipt upload) or in cash (QR handover) — club membership and paid events. */
const props = defineProps<{
    amountSen: number;
    accountName: string | null;
    paymentUrl: string | null;
    qrCodes: { kind: 'generated' | 'uploaded'; label: string; svg?: string; url?: string }[];
    payment: { order_id?: string | null; status: string; flags: string[]; review_note: string | null; handover_id: number | null } | null;
    committee: { id: number; name: string; position: string; photo_url: string | null }[];
    receiptUrl: string;
    cashUrl: string;
    /** What happens when it's paid, e.g. "Your membership starts at once". */
    outcome: string;
}>();

const method = ref<'online' | 'cash'>('online');
const receipt = useForm({ receipt: [] as File[] });
const sendReceipt = (): void => {
    receipt.transform((d) => ({ receipt: d.receipt[0] ?? null })).post(props.receiptUrl, { preserveScroll: true, onSuccess: () => receipt.reset() });
};
const cash = useForm({ committee_user_id: null as number | null });
const requestCash = (): void => cash.post(props.cashUrl);

const flagText: Record<string, string> = {
    amount_mismatch: 'Amount does not match the fee',
    payee_mismatch: 'Payee is not the right account',
    old_receipt: 'Receipt is older than 7 days',
    future_date: 'Receipt date is in the future',
    no_reference: 'No transaction reference found',
    no_date: 'No date found',
    low_confidence: 'Receipt is hard to read',
    unreadable: 'Receipt could not be read automatically',
    duplicate_reference: 'This reference was already used',
};
</script>

<template>
    <div class="grid gap-4">
        <div v-if="payment && ['reading', 'pending_review', 'cash_pending'].includes(payment.status)" class="grid gap-2 rounded-control bg-surface-muted p-4 text-sm">
            <p v-if="payment.status === 'reading'" class="flex items-center gap-2 font-medium"><span class="size-3 animate-ping rounded-full bg-brand-magenta" /> Checking your receipt…</p>
            <template v-else-if="payment.status === 'pending_review'">
                <p class="font-medium">Your receipt is with the committee for review.</p>
                <ul class="list-disc pl-5 text-ink-muted"><li v-for="f in payment.flags" :key="f">{{ flagText[f] ?? f }}</li></ul>
            </template>
            <template v-else-if="payment.handover_id">
                <p class="flex items-center gap-2 font-medium"><span class="size-2 animate-ping rounded-full bg-brand-magenta" /> Your cash payment is in progress</p>
                <p class="text-ink-muted">Open it to follow each step with the committee member.</p>
                <Link :href="`/handovers/${payment.handover_id}`"><Button block>Continue cash payment</Button></Link>
            </template>
            <p v-if="payment.order_id" class="text-xs text-ink-muted">Reference {{ payment.order_id }}</p>
        </div>
        <p v-if="payment?.status === 'rejected'" class="rounded-control bg-danger/10 p-3 text-sm text-danger">
            Previous payment not accepted: {{ payment.review_note ?? (payment.flags.map((f) => flagText[f] ?? f).join(', ') || 'please try again') }}
        </p>

        <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="How do you want to pay?">
            <button v-for="m in (['online', 'cash'] as const)" :key="m" type="button" role="radio" :aria-checked="method === m" class="grid gap-1 rounded-card border-2 p-3 text-left transition" :class="method === m ? 'border-ink bg-surface-muted' : 'border-line hover:border-ink/30'" @click="method = m">
                <span class="flex items-center gap-2 font-semibold"><Icon :name="m === 'online' ? 'send' : 'qr'" :size="18" />{{ m === 'online' ? 'Pay online' : 'Pay in cash' }}</span>
                <span class="text-xs text-ink-muted">{{ m === 'online' ? 'Transfer or scan, then upload the receipt' : 'Hand cash to a committee member' }}</span>
            </button>
        </div>

        <div v-if="method === 'online'" class="grid gap-4">
            <ol class="grid gap-2 text-sm">
                <li>1. Pay <strong>{{ rm(amountSen) }}</strong><template v-if="accountName"> to <strong>{{ accountName }}</strong></template><template v-if="paymentUrl"> using the <a :href="paymentUrl" target="_blank" rel="noopener" class="font-medium text-brand-blue">payment link ↗</a></template><template v-if="qrCodes.length"> or scan a QR code below</template>.</li>
                <li>2. Upload the receipt. It's checked automatically: amount, date (within 7 days), reference and payee.</li>
            </ol>
            <div v-if="qrCodes.length" class="grid gap-3" :class="qrCodes.length > 1 ? 'sm:grid-cols-2' : ''">
                <figure v-for="(q, i) in qrCodes" :key="i" class="grid justify-items-center gap-2 rounded-card border border-line p-3">
                    <QrCode v-if="q.svg" :svg="q.svg" :label="q.label" />
                    <template v-else>
                        <img :src="q.url" :alt="q.label" class="aspect-square w-full max-w-56 rounded-control object-contain" />
                        <figcaption class="text-xs text-ink-muted">{{ q.label }}</figcaption>
                    </template>
                </figure>
            </div>
            <FileUpload v-model="receipt.receipt" label="Payment receipt" accept="image/jpeg,image/png,image/webp,application/pdf" :error="receipt.errors.receipt" />
            <Button :disabled="!receipt.receipt.length" :loading="receipt.processing" @click="sendReceipt">Upload receipt</Button>
        </div>

        <div v-else class="grid gap-4">
            <div class="grid gap-2 rounded-control bg-surface-muted p-4 text-sm">
                <p class="font-semibold">How paying in cash works</p>
                <ol class="grid gap-1.5 text-ink-muted">
                    <li>1. Choose who you'll pay below. They accept on their phone.</li>
                    <li>2. Meet them with exactly <strong class="text-ink">{{ rm(amountSen) }}</strong>. You both tap <strong class="text-ink">Ready</strong>.</li>
                    <li>3. Your personal QR code appears. Hold it next to the cash; their camera takes the photo automatically.</li>
                    <li>4. Hand over the cash and both tap <strong class="text-ink">Transaction done</strong>. {{ outcome }}, and the club records it.</li>
                </ol>
            </div>
            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">Pay to</legend>
                <label v-for="c in committee" :key="c.id" class="flex cursor-pointer items-center gap-3 rounded-card border-2 p-3 transition" :class="cash.committee_user_id === c.id ? 'border-ink bg-surface-muted' : 'border-line hover:border-ink/30'">
                    <input v-model="cash.committee_user_id" type="radio" name="committee" :value="c.id" class="sr-only" />
                    <Avatar :src="c.photo_url" :name="c.name" size="sm" />
                    <span class="grid min-w-0 flex-1 text-sm"><strong class="truncate">{{ c.name }}</strong><span class="text-ink-muted">{{ c.position }}</span></span>
                    <span class="grid size-5 shrink-0 place-items-center rounded-full border-2" :class="cash.committee_user_id === c.id ? 'border-ink bg-ink text-white' : 'border-line'"><Icon v-if="cash.committee_user_id === c.id" name="check" :size="12" :stroke-width="3" /></span>
                </label>
            </fieldset>
            <Button size="lg" block :disabled="!cash.committee_user_id" :loading="cash.processing" @click="requestCash">Send cash payment request</Button>
        </div>
    </div>
</template>
