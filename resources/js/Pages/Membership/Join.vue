<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import Icon from '@/Components/ui/Icon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { rm } from '@/lib/format';
import { Head, Link, router, useForm, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    club: { slug: string; name: string; logo_url: string | null };
    fee: { amount_sen: number; payment_url: string | null; account_name: string | null } | null;
    drive: { title: string; ends_at: string } | null;
    membership: { status: string; expires_on: string | null; grace_ends_on: string | null } | null;
    payment: { method: string; status: string; flags: string[]; review_note: string | null; handover_id: number | null; handover_status: string | null } | null;
    committee: { id: number; name: string; position: string; photo_url: string | null }[];
}>();

const acknowledged = ref(false);
const join = (): void => router.post(`/clubs/${props.club.slug}/join`, { acknowledged: acknowledged.value }, { preserveScroll: true });
const isPaid = computed(() => (props.fee?.amount_sen ?? 0) > 0);
const awaitingPayment = computed(() => props.membership && ['pending', 'grace', 'terminated'].includes(props.membership.status) && isPaid.value);
const reading = computed(() => props.payment?.status === 'reading');
// While the AI reads the receipt, refresh until it finishes.
usePoll(3000, { only: ['payment', 'membership'] }, { autoStart: reading.value });

const method = ref<'online' | 'cash'>('online');
const receipt = useForm({ receipt: [] as File[] });
const sendReceipt = (): void => {
    receipt.transform((d) => ({ receipt: d.receipt[0] ?? null })).post(`/clubs/${props.club.slug}/join/receipt`, { preserveScroll: true, onSuccess: () => receipt.reset() });
};
const cash = useForm({ committee_user_id: null as number | null });
const requestCash = (): void => cash.post(`/clubs/${props.club.slug}/join/cash`);

const flagText: Record<string, string> = {
    amount_mismatch: 'Amount does not match the fee',
    payee_mismatch: "Payee is not the club's account",
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
    <Head :title="`Join ${club.name}`" />
    <AppLayout>
        <div class="mx-auto grid max-w-xl gap-5">
            <header class="flex items-center gap-4">
                <Avatar :src="club.logo_url" :name="club.name" size="lg" ring />
                <div>
                    <p class="text-sm text-ink-muted">Membership</p>
                    <h1 class="text-xl font-bold">{{ club.name }}</h1>
                    <Link :href="`/clubs/${club.slug}`" class="text-sm text-brand-blue">Back to club</Link>
                </div>
            </header>

            <p v-if="drive" class="flex items-center gap-2 rounded-card bg-gradient-to-r from-brand-yellow/30 to-brand-magenta/20 px-4 py-3 text-sm font-medium">
                <Icon name="sparkles" :size="18" /> {{ drive.title }} — membership drive is on now!
            </p>

            <Card v-if="membership?.status === 'active' || membership?.status === 'alumni'">
                <div class="grid justify-items-center gap-2 py-4 text-center">
                    <span class="grid size-14 place-items-center rounded-full bg-success text-white animate-tracker-pop"><Icon name="check" :size="28" :stroke-width="3" /></span>
                    <p class="text-lg font-semibold">You're a {{ membership.status === 'alumni' ? 'alumni member' : 'member' }}</p>
                    <p v-if="membership.expires_on && membership.status === 'active'" class="text-sm text-ink-muted">Active until {{ membership.expires_on }}</p>
                </div>
            </Card>

            <template v-else>
                <Card v-if="!fee">
                    <p class="text-sm text-ink-muted">Joining opens once SLD approves this club's fee for the session.</p>
                </Card>

                <Card v-else-if="!membership || membership.status === 'terminated' || (membership.status === 'grace' && !payment)">
                    <template #title>{{ membership?.status === 'grace' ? 'Renew your membership' : 'Join' }}</template>
                    <div class="grid gap-4">
                        <p class="text-3xl font-bold">{{ isPaid ? rm(fee.amount_sen) : 'Free' }} <span class="text-sm font-normal text-ink-muted">· 365 days</span></p>
                        <p v-if="membership?.status === 'grace'" class="text-sm text-warning">Your membership expired. Renew by {{ membership.grace_ends_on }}.</p>
                        <div v-if="isPaid" class="rounded-control border border-warning/40 bg-warning/5 p-3 text-sm">
                            <Checkbox v-model="acknowledged" label="I understand the fee is non-refundable and members cannot leave the club." />
                        </div>
                        <Button size="lg" :disabled="isPaid && !acknowledged" @click="join">{{ isPaid ? 'Continue to payment' : 'Join now' }}</Button>
                    </div>
                </Card>

                <Card v-if="awaitingPayment && fee">
                    <template #title>Pay {{ rm(fee.amount_sen) }}</template>

                    <div v-if="payment && ['reading', 'pending_review', 'cash_pending'].includes(payment.status)" class="mb-4 grid gap-2 rounded-control bg-surface-muted p-4 text-sm">
                        <p v-if="payment.status === 'reading'" class="flex items-center gap-2 font-medium"><span class="size-3 animate-ping rounded-full bg-brand-magenta" /> Checking your receipt…</p>
                        <template v-else-if="payment.status === 'pending_review'">
                            <p class="font-medium">Your receipt is with the Treasurer for review.</p>
                            <ul class="list-disc pl-5 text-ink-muted"><li v-for="f in payment.flags" :key="f">{{ flagText[f] ?? f }}</li></ul>
                        </template>
                        <template v-else-if="payment.handover_id">
                            <p class="flex items-center gap-2 font-medium"><span class="size-2 animate-ping rounded-full bg-brand-magenta" /> Your cash payment is in progress</p>
                            <p class="text-ink-muted">Open it to follow each step with the committee member.</p>
                            <Link :href="`/handovers/${payment.handover_id}`"><Button block>Continue cash payment</Button></Link>
                        </template>
                    </div>
                    <p v-if="payment?.status === 'rejected'" class="mb-4 rounded-control bg-danger/10 p-3 text-sm text-danger">
                        Previous payment not accepted: {{ payment.review_note ?? (payment.flags.map((f) => flagText[f] ?? f).join(', ') || 'please try again') }}
                    </p>

                    <div class="mb-4 grid grid-cols-2 gap-2" role="radiogroup" aria-label="How do you want to pay?">
                        <button v-for="m in (['online', 'cash'] as const)" :key="m" type="button" role="radio" :aria-checked="method === m" class="grid gap-1 rounded-card border-2 p-3 text-left transition" :class="method === m ? 'border-ink bg-surface-muted' : 'border-line hover:border-ink/30'" @click="method = m">
                            <span class="flex items-center gap-2 font-semibold"><Icon :name="m === 'online' ? 'send' : 'qr'" :size="18" />{{ m === 'online' ? 'Pay online' : 'Pay in cash' }}</span>
                            <span class="text-xs text-ink-muted">{{ m === 'online' ? 'Transfer, then upload the receipt' : 'Hand cash to a committee member' }}</span>
                        </button>
                    </div>

                    <div v-if="method === 'online'" class="grid gap-4">
                        <ol class="grid gap-2 text-sm">
                            <li>1. Pay <strong>{{ rm(fee.amount_sen) }}</strong> to <strong>{{ fee.account_name }}</strong><template v-if="fee.payment_url"> using the <a :href="fee.payment_url" target="_blank" rel="noopener" class="font-medium text-brand-blue">club's payment link ↗</a></template>.</li>
                            <li>2. Upload the receipt. It's checked automatically: amount, date (within 7 days), reference and payee.</li>
                        </ol>
                        <FileUpload v-model="receipt.receipt" label="Payment receipt" accept="image/jpeg,image/png,image/webp,application/pdf" :error="receipt.errors.receipt" />
                        <Button :disabled="!receipt.receipt.length" :loading="receipt.processing" @click="sendReceipt">Upload receipt</Button>
                    </div>

                    <div v-else class="grid gap-4">
                        <div class="grid gap-2 rounded-control bg-surface-muted p-4 text-sm">
                            <p class="font-semibold">How paying in cash works</p>
                            <ol class="grid gap-1.5 text-ink-muted">
                                <li>1. Choose who you'll pay below. They accept on their phone.</li>
                                <li>2. Meet them with exactly <strong class="text-ink">{{ rm(fee.amount_sen) }}</strong>. You both tap <strong class="text-ink">Ready</strong>.</li>
                                <li>3. Your personal QR code appears. Hold it next to the cash; their camera takes the photo automatically.</li>
                                <li>4. Hand over the cash and both tap <strong class="text-ink">Transaction done</strong>. Your membership starts at once and the club records it.</li>
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
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
