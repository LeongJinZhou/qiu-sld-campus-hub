<script setup lang="ts">
import IndemnityPrompt from '@/Components/events/IndemnityPrompt.vue';
import TicketCard from '@/Components/tickets/TicketCard.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Ticket } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { useWakeLock } from '@vueuse/core';
import { onMounted } from 'vue';

const props = defineProps<{ ticket: Ticket; qrSvg: string | null; mine: boolean }>();

// Keep the screen on while the ticket is shown at the door.
const { request } = useWakeLock();
onMounted(() => {
    if (props.mine && !props.ticket.event.past) void request('screen').catch(() => undefined);
});
</script>

<template>
    <Head :title="`Ticket · ${ticket.event.name}`" />
    <AppLayout>
        <div class="grid gap-5 pb-6">
            <Link href="/activities" class="flex items-center gap-1 text-sm text-brand-blue"><Icon name="ticket" :size="16" /> All my activities</Link>

            <IndemnityPrompt v-if="mine && ticket.event.needs_indemnity && ['paid', 'free'].includes(ticket.status)" :slug="ticket.event.slug" :name="ticket.event.name" class="mx-auto w-full max-w-sm" />
            <TicketCard :ticket="ticket" :qr-svg="qrSvg" />

            <div class="mx-auto grid w-full max-w-sm gap-2">
                <Link v-if="mine && ticket.status === 'unpaid'" :href="`/events/${ticket.event.slug}/pay`"><Button block>Pay now</Button></Link>
                <Link :href="`/events/${ticket.event.slug}`"><Button block variant="ghost">Event details</Button></Link>
            </div>
        </div>
    </AppLayout>
</template>
