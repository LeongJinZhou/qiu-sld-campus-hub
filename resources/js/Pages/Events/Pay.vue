<script setup lang="ts">
import PayPanel from '@/Components/payments/PayPanel.vue';
import Card from '@/Components/ui/Card.vue';
import Icon from '@/Components/ui/Icon.vue';
import Button from '@/Components/ui/Button.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useLeaveReminder } from '@/composables/useLeaveReminder';
import { dateTime, rm } from '@/lib/format';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    event: { slug: string; name: string; fee_sen: number; payment_url: string | null; payment_account_name: string | null; is_outdoor: boolean; club: { slug: string; name: string }; starts_at: string };
    registration: { status: string; paid_at: string | null; ticket_url: string | null };
    qrCodes: { kind: 'generated' | 'uploaded'; label: string; svg?: string; url?: string }[];
    payment: { order_id: string | null; method: string; status: string; flags: string[]; review_note: string | null; handover_id: number | null } | null;
    committee: { id: number; name: string; position: string; photo_url: string | null }[];
}>();

const paid = computed(() => props.registration.status === 'registered');
// While a receipt is being read, refresh until it's done.
usePoll(3000, { only: ['payment', 'registration'] }, { autoStart: props.payment?.status === 'reading' });
useLeaveReminder(() => `Paying for ${props.event.name}`, () => !paid.value);
</script>

<template>
    <Head :title="`Pay · ${event.name}`" />
    <AppLayout>
        <div class="mx-auto grid max-w-xl gap-5">
            <header class="grid gap-1">
                <Link :href="`/events/${event.slug}`" class="text-sm text-brand-blue">← {{ event.name }}</Link>
                <h1 class="text-xl font-bold">Event registration</h1>
                <p class="flex items-center gap-2 text-sm text-ink-muted"><Icon name="calendar" :size="16" /> {{ dateTime(event.starts_at) }} · {{ event.club.name }}</p>
            </header>

            <Card v-if="paid">
                <div class="grid justify-items-center gap-2 py-4 text-center">
                    <span class="grid size-14 place-items-center rounded-full bg-success text-white animate-tracker-pop"><Icon name="check" :size="28" :stroke-width="3" /></span>
                    <p class="text-lg font-semibold">You're registered</p>
                    <p class="text-sm text-ink-muted">Paid {{ registration.paid_at }}<template v-if="payment?.order_id"> · ref {{ payment.order_id }}</template></p>
                    <Link v-if="registration.ticket_url" :href="registration.ticket_url"><Button><Icon name="ticket" :size="18" /> View my ticket</Button></Link>
                    <Link v-if="event.is_outdoor" :href="`/events/${event.slug}/indemnity`"><Button variant="secondary">Sign the indemnity form</Button></Link>
                </div>
            </Card>

            <Card v-else>
                <template #title>Pay {{ rm(event.fee_sen) }} to confirm your place</template>
                <PayPanel
                    :amount-sen="event.fee_sen"
                    :account-name="event.payment_account_name"
                    :payment-url="event.payment_url"
                    :qr-codes="qrCodes"
                    :payment="payment"
                    :committee="committee"
                    :receipt-url="`/events/${event.slug}/pay/receipt`"
                    :cash-url="`/events/${event.slug}/pay/cash`"
                    outcome="Your registration is confirmed at once"
                />
            </Card>
        </div>
    </AppLayout>
</template>
