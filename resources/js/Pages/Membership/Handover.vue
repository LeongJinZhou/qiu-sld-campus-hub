<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import LiveQrCapture from '@/Components/ui/LiveQrCapture.vue';
import QrCode from '@/Components/ui/QrCode.vue';
import StepIndicator from '@/Components/ui/StepIndicator.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useLeaveReminder } from '@/composables/useLeaveReminder';
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
        cash_check: 'ai' | 'committee' | 'needs_confirmation' | null;
        photo_url: string | null;
    };
    qrSvg: string | null;
    expectToken: string | null;
}>();

// Leaving mid-way (home button, app switch) leaves a reminder that brings the person back here.
useLeaveReminder(() => `Your cash payment for ${props.handover.club}`, () => !['completed', 'declined', 'expired'].includes(props.handover.status));

// Both screens poll so each side sees the other's taps within seconds.
usePoll(2000, { only: ['handover', 'qrSvg'] });

const busy = ref(false);
const act = (action: string, data: Record<string, unknown> = {}): void => {
    busy.value = true;
    router.post(`/handovers/${props.handover.id}`, { action, ...data }, { preserveScroll: true, forceFormData: 'photo' in data, onFinish: () => (busy.value = false) });
};
const onPhoto = (photo: Blob): void => act('photo', { photo: new File([photo], 'handover.jpg', { type: 'image/jpeg' }) });
// Retake goes back to the QR step on the server, so the student's phone shows their QR again too.
const retake = (): void => act('retake');

const h = computed(() => props.handover);
const amount = computed(() => rm(h.value.amount_sen));
const me = computed(() => (props.side === 'student' ? { ready: h.value.student_ready, done: h.value.student_done } : { ready: h.value.committee_ready, done: h.value.committee_done }));
const other = computed(() => (props.side === 'student' ? h.value.committee : h.value.student));
const cashOk = computed(() => h.value.cash_check === 'ai' || h.value.cash_check === 'committee');
const order = ['requested', 'accepted', 'both_ready', 'photo_taken', 'completed'];
const at = computed(() => order.indexOf(['student_ready', 'committee_ready'].includes(h.value.status) ? 'accepted' : h.value.status));
const steps = computed(() =>
    ['Request', 'Accept', 'Ready', 'Photo', 'Done'].map((label, i) => ({
        label,
        state: h.value.status === 'completed' || i < at.value ? ('done' as const) : i === at.value ? ('current' as const) : ('pending' as const),
    })),
);
</script>

<template>
    <Head title="Cash payment" />
    <AppLayout>
        <div class="mx-auto grid max-w-md gap-5">
            <header class="grid gap-1 rounded-card border border-line bg-surface p-5 text-center shadow-card">
                <p class="text-sm text-ink-muted">Cash membership payment · {{ h.club }}</p>
                <p class="text-3xl font-bold tabular-nums">{{ amount }}</p>
                <div class="mt-3 grid grid-cols-[1fr_auto_1fr] items-center gap-2 text-sm">
                    <span class="grid justify-items-center gap-1"><Avatar :src="h.student.photo_url" :name="h.student.name" size="sm" /><span class="line-clamp-2">{{ h.student.name }}</span><span class="text-xs text-ink-muted">pays</span></span>
                    <Icon name="send" :size="18" class="text-ink-muted" />
                    <span class="grid justify-items-center gap-1"><Avatar :src="h.committee.photo_url" :name="h.committee.name" size="sm" /><span class="line-clamp-2">{{ h.committee.name }}</span><span class="text-xs text-ink-muted">receives</span></span>
                </div>
            </header>

            <StepIndicator :steps="steps" />

            <section class="grid gap-4 rounded-card border border-line bg-surface p-5 shadow-card">
                <!-- 1. Request -->
                <template v-if="h.status === 'requested'">
                    <template v-if="side === 'committee'">
                        <p class="text-center font-medium">{{ h.student.name }} wants to pay you {{ amount }} in cash.</p>
                        <div class="grid grid-cols-2 gap-2">
                            <Button variant="secondary" :loading="busy" @click="act('decline')">Decline</Button>
                            <Button :loading="busy" @click="act('accept')">Accept</Button>
                        </div>
                    </template>
                    <p v-else class="text-center text-ink-muted">Waiting for {{ other.name }} to accept your request…</p>
                </template>

                <!-- 2. Meet and get ready: a short guide for each side -->
                <template v-else-if="['accepted', 'student_ready', 'committee_ready'].includes(h.status)">
                    <h2 class="font-semibold">{{ side === 'student' ? 'How to pay in cash' : 'How to collect the cash' }}</h2>
                    <ol v-if="side === 'student'" class="grid gap-3 text-sm">
                        <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-ink text-xs font-bold text-white">1</span><span class="pt-0.5">Bring exactly <strong>{{ amount }}</strong> in cash and meet {{ other.name }} in person.</span></li>
                        <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-ink text-xs font-bold text-white">2</span><span class="pt-0.5">Tap <strong>Ready to pay</strong>. Your personal QR code appears on this screen.</span></li>
                        <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-ink text-xs font-bold text-white">3</span><span class="pt-0.5">Hold your phone next to the cash. {{ other.name }}'s camera takes the photo by itself.</span></li>
                        <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-ink text-xs font-bold text-white">4</span><span class="pt-0.5">Hand over the cash, then both tap <strong>Transaction done</strong>.</span></li>
                    </ol>
                    <ol v-else class="grid gap-3 text-sm">
                        <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-ink text-xs font-bold text-white">1</span><span class="pt-0.5">Meet {{ other.name }} in person. They pay <strong>{{ amount }}</strong>.</span></li>
                        <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-ink text-xs font-bold text-white">2</span><span class="pt-0.5">Tap <strong>Ready to receive</strong>. Your camera opens.</span></li>
                        <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-ink text-xs font-bold text-white">3</span><span class="pt-0.5">Point it at the student's QR code with the cash in the same picture. It captures automatically when it finds <strong>their</strong> QR.</span></li>
                        <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-ink text-xs font-bold text-white">4</span><span class="pt-0.5">Check the photo (retake if needed), take the cash, then both tap <strong>Transaction done</strong>.</span></li>
                    </ol>
                    <Button v-if="!me.ready" size="lg" block :loading="busy" @click="act('ready')">{{ side === 'student' ? 'Ready to pay' : 'Ready to receive' }}</Button>
                    <p v-else class="flex items-center justify-center gap-2 text-sm text-ink-muted"><span class="size-2 animate-ping rounded-full bg-brand-magenta" /> Waiting for {{ other.name }} to tap ready…</p>
                </template>

                <!-- 3. Photo: the student shows their QR; the member's camera captures it with the cash -->
                <template v-else-if="h.status === 'both_ready' || h.status === 'photo_taken'">
                    <template v-if="side === 'student'">
                        <template v-if="h.status === 'both_ready'">
                            <p class="text-center font-medium">Show this QR to {{ other.name }}, next to your {{ amount }} cash.</p>
                            <QrCode v-if="qrSvg" :svg="qrSvg" label="Your personal QR — the same one as on your profile" />
                            <p class="text-center text-xs text-ink-muted">Keep the screen bright and still. The photo is taken automatically.</p>
                        </template>
                        <template v-else>
                            <img v-if="h.photo_url" :src="h.photo_url" alt="Cash payment photo" class="w-full rounded-card border border-line" />
                            <p class="text-center text-sm">{{ cashOk ? 'Photo checked.' : `${other.name} is checking the photo…` }} Hand over the cash, then confirm.</p>
                            <Button v-if="!me.done" size="lg" block :disabled="!cashOk" :loading="busy" @click="act('done')">Transaction done</Button>
                            <p v-else class="text-center text-sm text-ink-muted">Waiting for {{ other.name }} to confirm…</p>
                        </template>
                    </template>

                    <template v-else>
                        <LiveQrCapture v-if="h.status === 'both_ready' && expectToken" :expect-token="expectToken" :person-name="other.name" @capture="onPhoto" />
                        <template v-else>
                            <img v-if="h.photo_url" :src="h.photo_url" alt="Cash payment photo" class="w-full rounded-card border border-line" />
                            <ul class="grid gap-1 text-sm">
                                <li class="flex items-center gap-2 text-success"><Icon name="check" :size="16" /> {{ other.name }}'s QR code matches</li>
                                <li v-if="h.cash_check === 'ai'" class="flex items-center gap-2 text-success"><Icon name="check" :size="16" /> Cash detected in the photo</li>
                                <li v-else-if="h.cash_check === 'committee'" class="flex items-center gap-2 text-success"><Icon name="check" :size="16" /> You confirmed the cash</li>
                            </ul>
                            <div v-if="h.cash_check === 'needs_confirmation'" class="grid gap-2 rounded-control bg-warning/10 p-3 text-sm">
                                <p class="font-medium">Is {{ amount }} in cash clearly visible next to the QR?</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <Button variant="secondary" :loading="busy" @click="retake">Retake</Button>
                                    <Button :loading="busy" @click="act('confirm_cash')">Cash is correct</Button>
                                </div>
                            </div>
                            <template v-else>
                                <Button v-if="!me.done" size="lg" block :loading="busy" @click="act('done')">Transaction done</Button>
                                <p v-else class="text-center text-sm text-ink-muted">Waiting for {{ other.name }} to confirm…</p>
                                <button v-if="!me.done" type="button" class="justify-self-center text-sm text-ink-muted underline" @click="retake">Retake photo</button>
                            </template>
                        </template>
                    </template>
                </template>

                <!-- 4. Done: receipt -->
                <template v-else-if="h.status === 'completed'">
                    <span class="mx-auto grid size-16 place-items-center rounded-full bg-success text-white animate-tracker-pop"><Icon name="check" :size="32" :stroke-width="3" /></span>
                    <p class="text-center text-lg font-semibold">Payment complete</p>
                    <dl class="grid grid-cols-[8rem_1fr] gap-y-1 text-sm">
                        <dt class="text-ink-muted">Amount</dt><dd class="font-medium">{{ amount }} (cash)</dd>
                        <dt class="text-ink-muted">Paid by</dt><dd>{{ h.student.name }}</dd>
                        <dt class="text-ink-muted">Received by</dt><dd>{{ h.committee.name }}</dd>
                        <dt class="text-ink-muted">Club</dt><dd>{{ h.club }}</dd>
                    </dl>
                    <a v-if="h.photo_url" :href="h.photo_url" target="_blank" class="text-center text-sm font-medium text-brand-blue">View the payment photo</a>
                    <p class="text-center text-sm text-ink-muted">Membership is active and the club's finance has recorded it. A receipt was sent to both of you.</p>
                    <Link :href="`/clubs/${h.club_slug}`" class="justify-self-center"><Button variant="secondary">Back to club</Button></Link>
                </template>

                <p v-else class="text-center text-ink-muted">This request was {{ h.status }}.</p>
            </section>

            <p class="text-center text-xs text-ink-muted">Request expires {{ h.expires_at }}.</p>
        </div>
    </AppLayout>
</template>
