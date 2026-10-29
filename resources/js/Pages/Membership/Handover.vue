<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Button from '@/Components/ui/Button.vue';
import CameraCapture from '@/Components/ui/CameraCapture.vue';
import Icon from '@/Components/ui/Icon.vue';
import QrCode from '@/Components/ui/QrCode.vue';
import StepIndicator from '@/Components/ui/StepIndicator.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { rm } from '@/lib/format';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    side: 'student' | 'committee';
    handover: {
        id: number;
        status: string;
        club: string;
        club_slug: string;
        amount_sen: number;
        student: { name: string; photo_url: string | null };
        committee: { name: string; photo_url: string | null };
        student_ready: boolean;
        committee_ready: boolean;
        student_done: boolean;
        committee_done: boolean;
        expires_at: string;
    };
    qrSvg: string | null;
}>();

// Both screens poll so each side sees the other's taps within seconds.
usePoll(2000, { only: ['handover', 'qrSvg'] });

const busy = ref(false);
const retaking = ref(false);
const act = (action: string, data: Record<string, unknown> = {}): void => {
    busy.value = true;
    router.post(`/handovers/${props.handover.id}`, { action, ...data }, { preserveScroll: true, forceFormData: 'photo' in data, onFinish: () => (busy.value = false) });
};
const onPhoto = (photo: Blob): void => {
    retaking.value = false;
    act('photo', { photo: new File([photo], 'handover.jpg', { type: 'image/jpeg' }) });
};

const h = computed(() => props.handover);
const me = computed(() => (props.side === 'student' ? { ready: h.value.student_ready, done: h.value.student_done } : { ready: h.value.committee_ready, done: h.value.committee_done }));
const other = computed(() => (props.side === 'student' ? h.value.committee : h.value.student));
const order = ['requested', 'accepted', 'both_ready', 'photo_taken', 'completed'];
const at = computed(() => {
    const s = ['student_ready', 'committee_ready'].includes(h.value.status) ? 'accepted' : h.value.status;
    return order.indexOf(s);
});
const steps = computed(() =>
    ['Request', 'Accepted', 'Both ready', 'Photo', 'Done'].map((label, i) => ({
        label,
        state: (h.value.status === 'declined' || h.value.status === 'expired') && i > at.value ? ('pending' as const) : i < at.value || h.value.status === 'completed' ? ('done' as const) : i === at.value ? ('current' as const) : ('pending' as const),
    })),
);
</script>

<template>
    <Head title="Cash handover" />
    <AppLayout>
        <div class="mx-auto grid max-w-md gap-5">
            <header class="grid gap-1 text-center">
                <p class="text-sm text-ink-muted">Cash membership payment · {{ h.club }}</p>
                <p class="text-3xl font-bold">{{ rm(h.amount_sen) }}</p>
                <div class="mt-2 flex items-center justify-center gap-3 text-sm">
                    <Avatar :src="h.student.photo_url" :name="h.student.name" size="sm" /><span>{{ h.student.name }}</span>
                    <Icon name="send" :size="16" class="text-ink-muted" />
                    <Avatar :src="h.committee.photo_url" :name="h.committee.name" size="sm" /><span>{{ h.committee.name }}</span>
                </div>
            </header>

            <StepIndicator :steps="steps" />

            <section class="grid gap-4 rounded-card border border-line bg-surface p-5 text-center shadow-card">
                <template v-if="h.status === 'requested'">
                    <template v-if="side === 'committee'">
                        <p class="font-medium">{{ h.student.name }} wants to pay you {{ rm(h.amount_sen) }} in cash.</p>
                        <div class="grid grid-cols-2 gap-2">
                            <Button variant="secondary" :loading="busy" @click="act('decline')">Decline</Button>
                            <Button :loading="busy" @click="act('accept')">Accept</Button>
                        </div>
                    </template>
                    <p v-else class="text-ink-muted">Waiting for {{ other.name }} to accept…</p>
                </template>

                <template v-else-if="['accepted', 'student_ready', 'committee_ready'].includes(h.status)">
                    <p class="font-medium">Meet {{ other.name }} in person.</p>
                    <Button v-if="!me.ready" size="lg" :loading="busy" @click="act('ready')">{{ side === 'student' ? 'Ready to pay' : 'Ready to receive' }}</Button>
                    <p v-else class="flex items-center justify-center gap-2 text-ink-muted"><span class="size-2 animate-ping rounded-full bg-brand-magenta" /> Waiting for {{ other.name }} to tap ready…</p>
                </template>

                <template v-else-if="h.status === 'both_ready' || h.status === 'photo_taken'">
                    <template v-if="side === 'student'">
                        <p class="font-medium">Show this QR next to the cash.</p>
                        <QrCode v-if="qrSvg" :svg="qrSvg" label="One-time payment code" />
                    </template>
                    <template v-else-if="h.status === 'both_ready' || retaking">
                        <p class="font-medium">Take one photo showing the student's QR code and the cash together.</p>
                        <CameraCapture @capture="onPhoto" />
                    </template>
                    <template v-if="h.status === 'photo_taken' && !retaking">
                        <p class="flex items-center justify-center gap-2 font-medium text-success"><Icon name="check" :size="18" /> Photo verified — the QR matches this payment.</p>
                        <Button v-if="!me.done" size="lg" :loading="busy" @click="act('done')">Transaction done</Button>
                        <p v-else class="text-ink-muted">Waiting for {{ other.name }} to confirm…</p>
                        <button v-if="side === 'committee' && !me.done" type="button" class="text-sm text-ink-muted underline" @click="retaking = true">Retake photo</button>
                    </template>
                </template>

                <template v-else-if="h.status === 'completed'">
                    <span class="mx-auto grid size-16 place-items-center rounded-full bg-success text-white animate-tracker-pop"><Icon name="check" :size="32" :stroke-width="3" /></span>
                    <p class="text-lg font-semibold">Payment complete</p>
                    <p class="text-sm text-ink-muted">Membership is active. A digital receipt was sent to both of you.</p>
                    <Link :href="`/clubs/${h.club_slug}`"><Button variant="secondary">Back to club</Button></Link>
                </template>

                <p v-else class="text-ink-muted">This request was {{ h.status }}.</p>
            </section>

            <p class="text-center text-xs text-ink-muted">Request expires {{ h.expires_at }}.</p>
        </div>
    </AppLayout>
</template>
