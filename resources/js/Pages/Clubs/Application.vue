<script setup lang="ts">
import ApplicationForm from '@/Components/clubs/ApplicationForm.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import SignaturePad from '@/Components/ui/SignaturePad.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    application: {
        id: number;
        status: string;
        status_label: string;
        data: { name: string; category_id: number | null; bio: string; objectives: string; officers: Record<string, string> };
        officers: { position: string; student_number: string; name: string | null }[];
        note: string | null;
        category: string | null;
        applicant: string;
        advisor: string;
        advisor_id: number;
        advisor_signed_at: string | null;
        created_at: string | null;
        club_slug: string | null;
    };
    can: { sign: boolean; decide: boolean; resubmit: boolean };
    categories: { id: number; name: string }[];
    advisors: { id: number; name: string }[];
    positions: string[];
}>();

const sign = useForm({ signature: null as string | null, signature_strokes: null as string | null });
const back = (): void => {
    const note = prompt('What needs to change? (sent to the applicant)');
    if (note) router.post(`/club-applications/${props.application.id}/return`, { note }, { preserveScroll: true });
};
const decide = (approve: boolean): void => {
    const note = approve ? null : prompt('Why is it not approved? (sent to the applicant)');
    if (!approve && !note) return;
    router.post(`/club-applications/${props.application.id}/decide`, { approve, note }, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Club application · ${application.data.name}`" />
    <AppLayout>
        <div class="grid gap-5">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">{{ application.data.name }}</h1>
                    <p class="text-sm text-ink-muted">New club application by {{ application.applicant }} · {{ application.created_at }}</p>
                </div>
                <Badge :tone="application.status === 'approved' ? 'success' : ['returned', 'rejected'].includes(application.status) ? 'danger' : 'warning'">{{
                    application.status_label
                }}</Badge>
            </header>
            <ol class="grid grid-cols-3 gap-1 text-center text-xs">
                <li class="rounded-control bg-success/10 p-2 text-success">✓ President signed</li>
                <li class="rounded-control p-2" :class="application.advisor_signed_at ? 'bg-success/10 text-success' : 'bg-surface-muted'">
                    {{ application.advisor_signed_at ? '✓' : '2.' }} Advisor ({{ application.advisor }})
                </li>
                <li class="rounded-control p-2" :class="application.status === 'approved' ? 'bg-success/10 text-success' : 'bg-surface-muted'">
                    {{ application.status === 'approved' ? '✓' : '3.' }} SLD approves
                </li>
            </ol>
            <p v-if="application.note" class="rounded-card bg-warning/10 px-4 py-3 text-sm">{{ application.note }}</p>
            <p v-if="application.club_slug" class="rounded-card bg-success/10 px-4 py-3 text-sm text-success">
                The club is open. <Link :href="`/clubs/${application.club_slug}`" class="font-semibold underline">Go to the club page</Link>
            </p>

            <section class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card text-sm">
                <p><strong>Category:</strong> {{ application.category ?? '—' }}</p>
                <p><strong>Description:</strong> {{ application.data.bio }}</p>
                <p class="whitespace-pre-line"><strong>Aims and activities:</strong> {{ application.data.objectives }}</p>
                <ul class="grid gap-1">
                    <li v-for="o in application.officers" :key="o.position">
                        <strong>{{ o.position }}:</strong> {{ o.name }} · {{ o.student_number }}
                    </li>
                </ul>
            </section>

            <section v-if="can.sign" class="grid gap-3 rounded-card border-2 border-brand-cyan/40 bg-brand-cyan/5 p-4">
                <h2 class="font-semibold">Your signature as advisor</h2>
                <SignaturePad v-model="sign.signature" v-model:strokes="sign.signature_strokes" label="Sign to support this club" />
                <div class="flex flex-wrap gap-2">
                    <Button :disabled="!sign.signature" :loading="sign.processing" @click="sign.post(`/club-applications/${application.id}/sign`, { preserveScroll: true })"
                        >Sign</Button
                    >
                    <Button variant="secondary" @click="back">Decline / ask for changes</Button>
                </div>
            </section>
            <section v-if="can.decide" class="flex flex-wrap gap-2 rounded-card border-2 border-brand-cyan/40 bg-brand-cyan/5 p-4">
                <Button @click="decide(true)">Approve &amp; open the club</Button>
                <Button variant="secondary" @click="back">Return for changes</Button>
                <Button variant="danger" @click="decide(false)">Reject</Button>
            </section>
            <template v-if="can.resubmit">
                <h2 class="font-semibold">Correct and sign again</h2>
                <ApplicationForm
                    :action="`/club-applications/${application.id}/resubmit`"
                    :categories="categories"
                    :advisors="advisors"
                    :positions="positions"
                    :initial="application.data"
                    :advisor-id="application.advisor_id"
                />
            </template>
        </div>
    </AppLayout>
</template>
