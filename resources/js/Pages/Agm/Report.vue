<script setup lang="ts">
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import DynamicTable from '@/Components/forms/DynamicTable.vue';
import FormSection from '@/Components/forms/FormSection.vue';
import LockedField from '@/Components/forms/LockedField.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import Modal from '@/Components/ui/Modal.vue';
import SignaturePad from '@/Components/ui/SignaturePad.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface ReportData {
    objectives: string[];
    speech: string[];
    agenda: { start: string; end: string; item: string }[];
    adjournment: string;
}
interface PersonRow {
    name: string;
    position: string;
    student_id: string;
    email: string;
    year?: string;
    programme: string;
    attendance?: 'advisor' | 'present' | 'absent';
}

const props = defineProps<{
    agm: { id: number; club: { slug: string; name: string } };
    document: { id: number; status: string; editable: boolean; approval: { progress: ApprovalProgress; steps: ApprovalStep[] } | null; downloads: string[] };
    data: ReportData;
    record: {
        session: string;
        next_session: string;
        club: string;
        date: string;
        time: string;
        venue: string;
        total_members: number;
        attended_members: number;
        absent_members: number;
        attendance: PersonRow[];
        committee: PersonRow[];
        signers: Record<string, string | null>;
        election: { election: string[]; appointments: string[] };
    };
    minObjectives: number;
    ai: string | null;
    aiAvailable: boolean;
}>();

const form = useForm<ReportData & { signature: string | null; signature_strokes: string | null }>({ ...props.data, signature: null, signature_strokes: null });
const url = `/agms/${props.agm.id}/report`;
const plain = (d: ReportData & { signature: unknown; signature_strokes: unknown }): ReportData => {
    const { signature: _s, signature_strokes: _t, ...rest } = d;
    return rest;
};
const save = (): void => {
    form.transform(plain).put(url, { preserveScroll: true });
};
const draftWithAi = (): void => {
    form.transform(plain).post(`${url}/draft`, { preserveScroll: true });
};
const drafting = computed(() => props.ai === 'pending');
usePoll(2500, { only: ['data', 'ai'], onSuccess: () => drafting.value || Object.assign(form, props.data) }, { autoStart: drafting.value });

const rows = (key: 'objectives' | 'speech') =>
    computed({
        get: () => form[key].map((text) => ({ text })),
        set: (r: { text: string }[]) => (form[key] = r.map((x) => x.text)),
    });
const objectiveRows = rows('objectives');
const speechRows = rows('speech');
const filledObjectives = computed(() => form.objectives.filter((o) => o.trim()).length);
const readOnly = computed(() => !props.document.editable);
const signing = ref(false);
const submit = (): void => form.post(`${url}/submit`, { preserveScroll: true, onFinish: () => (signing.value = false) });
const tone = (a?: string): 'success' | 'neutral' | 'info' => (a === 'present' ? 'success' : a === 'advisor' ? 'info' : 'neutral');
</script>

<template>
    <Head title="AGM Report" />
    <AppLayout wide>
        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="grid content-start gap-6">
                <header class="grid gap-1">
                    <Link :href="`/clubs/${agm.club.slug}/agm`" class="text-sm text-brand-blue">{{ agm.club.name }} · AGM</Link>
                    <h1 class="text-2xl font-bold">Annual General Meeting Report</h1>
                    <p class="text-sm text-ink-muted">Session {{ record.session }} · locked fields come from records and cannot be changed.</p>
                </header>

                <div v-if="document.downloads.length" class="flex flex-wrap gap-2">
                    <a v-for="f in document.downloads" :key="f" :href="`/documents/${document.id}/download/${f}`"><Button variant="secondary" size="sm">Official 01 AGM Report ({{ f.toUpperCase() }})</Button></a>
                </div>

                <FormSection title="Header">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <LockedField label="Student organization" :value="record.club" />
                        <LockedField label="Date" :value="record.date" />
                        <LockedField label="Time" :value="record.time" />
                        <LockedField label="Venue" :value="record.venue" />
                        <LockedField label="Prepared by (Secretary)" :value="record.signers.Secretary" />
                        <LockedField label="Checked by (President)" :value="record.signers.President" />
                        <LockedField label="Reviewed by (Advisor)" :value="record.signers.Advisor" />
                    </div>
                </FormSection>

                <FormSection title="1.0 Objectives" :hint="`At least ${minObjectives}. AI drafts 3 from the whole session; edit freely or add more.`">
                    <Button v-if="document.editable && aiAvailable" variant="secondary" size="sm" class="justify-self-start" :loading="drafting" @click="draftWithAi">
                        <Icon name="sparkles" :size="16" /> {{ drafting ? 'Drafting…' : 'Draft objectives, speech and agenda with AI' }}
                    </Button>
                    <p v-if="ai === 'failed'" class="text-sm text-warning">AI couldn't draft this time — please write them yourself.</p>
                    <DynamicTable v-model="objectiveRows" :columns="[{ key: 'text', label: 'Objective', type: 'textarea' }]" :blank="{ text: '' }" :disabled="readOnly" :errors="form.errors" error-prefix="objectives" add-label="Add objective" />
                    <p class="text-xs" :class="filledObjectives < minObjectives ? 'text-warning' : 'text-ink-muted'">{{ filledObjectives }} written · minimum {{ minObjectives }}</p>
                </FormSection>

                <FormSection title="2.0 Attendance" hint="Computed from AGM attendance. Alumni are not counted.">
                    <p class="rounded-control bg-surface-muted p-3 text-sm">
                        Our club has a total of <strong>{{ record.total_members }}</strong> members, <strong>{{ record.attended_members }}</strong> members attended the AGM,
                        <strong>{{ record.absent_members }}</strong> members absent.
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs text-ink-muted"><tr><th class="p-2">No.</th><th class="p-2">Name</th><th class="p-2">Position</th><th class="p-2">Student ID</th><th class="p-2">Year</th><th class="p-2">Program</th><th class="p-2" /></tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in record.attendance" :key="i" class="border-t border-line">
                                    <td class="p-2">{{ i + 1 }}</td><td class="p-2">{{ r.name }}</td><td class="p-2">{{ r.position }}</td><td class="p-2">{{ r.student_id }}</td><td class="p-2">{{ r.year }}</td><td class="p-2">{{ r.programme }}</td>
                                    <td class="p-2"><Badge :tone="tone(r.attendance)">{{ r.attendance }}</Badge></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </FormSection>

                <FormSection title="3.0 Opening speech">
                    <DynamicTable v-model="speechRows" :columns="[{ key: 'text', label: 'Point', type: 'textarea' }]" :blank="{ text: '' }" :disabled="readOnly" :errors="form.errors" error-prefix="speech" add-label="Add point" />
                </FormSection>

                <FormSection title="4.0 Event agenda">
                    <DynamicTable v-model="form.agenda" :columns="[{ key: 'start', label: 'Time start' }, { key: 'end', label: 'Time end' }, { key: 'item', label: 'Agenda' }]" :blank="{ start: '', end: '', item: '' }" :disabled="readOnly" :errors="form.errors" error-prefix="agenda" add-label="Add item" />
                </FormSection>

                <FormSection :title="`5.0 Current committee members ${record.session}`" hint="From the club's roles; not editable.">
                    <ul class="grid gap-1 text-sm">
                        <li v-for="(r, i) in record.committee" :key="i">{{ i + 1 }}. <strong>{{ r.position }}</strong> — {{ r.name }} <span class="text-ink-muted">{{ r.student_id }} {{ r.programme }}</span></li>
                    </ul>
                </FormSection>

                <FormSection :title="`6.0 Election of new committee members ${record.next_session}`" hint="From the election module.">
                    <ul class="grid gap-1 text-sm"><li v-for="l in record.election.election" :key="l">{{ l }}</li></ul>
                </FormSection>
                <FormSection :title="`7.0 Appointment of new committee members ${record.next_session}`">
                    <ul class="grid gap-1 text-sm"><li v-for="l in record.election.appointments" :key="l">{{ l }}</li></ul>
                </FormSection>
                <FormSection title="8.0 Adjournment of the meeting">
                    <Textarea v-model="form.adjournment" :rows="2" :readonly="readOnly" placeholder="The meeting was adjourned at 6.00 PM by the President." />
                </FormSection>

                <div v-if="document.editable" class="flex flex-wrap justify-end gap-2">
                    <Button variant="secondary" :loading="form.processing" @click="save">Save draft</Button>
                    <Button :disabled="filledObjectives < minObjectives" @click="signing = true">Sign &amp; submit</Button>
                </div>
            </div>

            <aside class="grid content-start gap-4">
                <section v-if="document.approval" class="rounded-card border border-line bg-surface p-5 shadow-card">
                    <ApprovalTracker :progress="document.approval.progress" :steps="document.approval.steps" />
                </section>
            </aside>
        </div>

        <Modal v-model:open="signing" title="Prepared by — Secretary">
            <div class="grid gap-4">
                <p class="text-sm text-ink-muted">Then: President → Club Advisor → SLD. Attendance, committee and election sections are frozen as they are now.</p>
                <SignaturePad v-model="form.signature" v-model:strokes="form.signature_strokes" label="Signature" :signer="record.signers.Secretary ?? undefined" />
                <p v-if="Object.keys(form.errors).length" class="text-sm text-danger">{{ Object.values(form.errors)[0] }}</p>
                <Button :disabled="!form.signature" :loading="form.processing" @click="submit">Submit AGM Report</Button>
            </div>
        </Modal>
    </AppLayout>
</template>
