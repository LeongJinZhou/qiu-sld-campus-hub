<script setup lang="ts">
import PayPanel from '@/Components/payments/PayPanel.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Icon from '@/Components/ui/Icon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useLeaveReminder } from '@/composables/useLeaveReminder';
import { rm } from '@/lib/format';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    club: { slug: string; name: string; logo_url: string | null };
    fee: { amount_sen: number; payment_url: string | null; account_name: string | null } | null;
    drive: { title: string; ends_at: string } | null;
    membership: { status: string; expires_on: string | null; grace_ends_on: string | null } | null;
    payment: { method: string; status: string; flags: string[]; review_note: string | null; handover_id: number | null; handover_status: string | null } | null;
    committee: { id: number; name: string; position: string; photo_url: string | null }[];
    qrCodes: { kind: 'generated' | 'uploaded'; label: string; svg?: string; url?: string }[];
}>();

const acknowledged = ref(false);
const join = (): void => router.post(`/clubs/${props.club.slug}/join`, { acknowledged: acknowledged.value }, { preserveScroll: true });
const isPaid = computed(() => (props.fee?.amount_sen ?? 0) > 0);
const awaitingPayment = computed(() => props.membership && ['pending', 'grace', 'terminated'].includes(props.membership.status) && isPaid.value);
const reading = computed(() => props.payment?.status === 'reading');
// While the AI reads the receipt, refresh until it finishes.
usePoll(3000, { only: ['payment', 'membership'] }, { autoStart: reading.value });

// Started joining a paid club but left before paying: remind them where they stopped.
useLeaveReminder(() => `Paying for ${props.club.name}`, () => Boolean(awaitingPayment.value));


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
                    <PayPanel
                        :amount-sen="fee.amount_sen"
                        :account-name="fee.account_name"
                        :payment-url="fee.payment_url"
                        :qr-codes="qrCodes"
                        :payment="payment"
                        :committee="committee"
                        :receipt-url="`/clubs/${club.slug}/join/receipt`"
                        :cash-url="`/clubs/${club.slug}/join/cash`"
                        outcome="Your membership starts at once"
                    />
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
