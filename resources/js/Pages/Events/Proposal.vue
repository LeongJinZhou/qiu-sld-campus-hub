<script setup lang="ts">
import { watchDebounced } from '@vueuse/core';
import { useLeaveReminder } from '@/composables/useLeaveReminder';
import BudgetTable from '@/Components/forms/BudgetTable.vue';
import CheckboxGroup from '@/Components/forms/CheckboxGroup.vue';
import DynamicTable from '@/Components/forms/DynamicTable.vue';
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import FormSection from '@/Components/forms/FormSection.vue';
import LockedField from '@/Components/forms/LockedField.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Input from '@/Components/ui/Input.vue';
import Modal from '@/Components/ui/Modal.vue';
import Select from '@/Components/ui/Select.vue';
import SignaturePad from '@/Components/ui/SignaturePad.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { date, dateTime, hoursBetween, rm } from '@/lib/format';
import type { ApprovalProgress, ApprovalStep, ProposalData } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    event: { slug: string; name: string };
    documentId: number;
    status: string;
    editable: boolean;
    approval: { progress: ApprovalProgress; steps: ApprovalStep[] } | null;
    data: ProposalData;
    locked: { to: string; from: string; submitted_on: string | null; prepared_by: string };
    committee: { id: number; position: string; is_compulsory: boolean; name: string; student_number: string | null; responsibility: string | null }[];
    missingCommittee: string[];
    missingClubCommittee: string[];
    returnComment: string | null;
    options: { natures: Record<string, string>; sdgs: Record<number, string>; fundSources: Record<string, string>; compulsoryPositions: string[] };
    leadTime: { threshold: number; small: string; large: string };
    lastEdit: { by: string | null; at: string | null };
    clubCommittee: { id: number; name: string }[];
    canDiscard: boolean;
}>();

const form = useForm<ProposalData & { signature: string | null; signature_strokes: string | null }>({ ...props.data, signature: null, signature_strokes: null });
const readOnly = computed(() => !props.editable);

// CCA follows the event hours (1 hour = 1 CCA) until the club types its own value.
const duration = computed(() => hoursBetween(form.starts_at, form.ends_at));
watch(duration, (hours, previous) => {
    if (form.cca_participant === previous) form.cca_participant = hours;
    if (form.cca_committee === previous) form.cca_committee = hours;
});
const minStart = computed(() => (form.expected_participants >= props.leadTime.threshold ? props.leadTime.large : props.leadTime.small));
const expensesSen = computed(() => form.budget.expenses.reduce((s, i) => s + i.price_sen * i.quantity, 0));

const objectiveRows = computed({
    get: () => form.objectives.map((text) => ({ text })),
    set: (rows: { text: string }[]) => (form.objectives = rows.map((r) => r.text)),
});

const save = (): void => {
    form.transform(({ signature: _signature, signature_strokes: _strokes, ...data }) => data).put(`/events/${props.event.slug}/proposal`, { preserveScroll: true });
};

// Shared draft: saves itself a moment after anyone stops typing, so another committee member can carry on later.
const saving = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
const autosave = (): void => {
    if (!props.editable || form.processing) return;
    const { signature: _s, signature_strokes: _t, ...data } = form.data();
    saving.value = 'saving';
    router.put(`/events/${props.event.slug}/proposal`, { ...data, autosave: 1 } as never, {
        preserveScroll: true,
        preserveState: true,
        only: ['lastEdit'],
        errorBag: 'autosave', // half-typed fields must not flash errors while autosaving
        onSuccess: () => (saving.value = 'saved'),
        onError: () => (saving.value = 'error'),
    });
};
watchDebounced(() => JSON.stringify({ ...form.data(), signature: null, signature_strokes: null }), autosave, { debounce: 2000 });
useLeaveReminder(() => `The proposal for ${props.event.name}`, () => props.editable);

const asking = ref(false);
const ask = useForm({ user_ids: [] as number[], note: '' });
const toggleAsk = (id: number): void => {
    ask.user_ids = ask.user_ids.includes(id) ? ask.user_ids.filter((x) => x !== id) : [...ask.user_ids, id];
};
const sendAsk = (): void => ask.post(`/events/${props.event.slug}/proposal/ask`, { preserveScroll: true, onSuccess: () => ((asking.value = false), ask.reset()) });
const discard = (): void => {
    if (confirm('Discard this draft? It cannot be undone.')) router.delete(`/events/${props.event.slug}/proposal`);
};

const signing = ref(false);
const blockers = computed(() => [
    ...props.missingClubCommittee.map((p) => `Club committee: ${p}`),
    ...props.missingCommittee.map((p) => `Event committee: ${p}`),
]);
const submit = (): void => {
    form.transform((data) => data).post(`/events/${props.event.slug}/proposal/submit`, {
        preserveScroll: true,
        onSuccess: () => (signing.value = false),
        onError: () => (signing.value = false),
    });
};

const member = useForm({ student_number: '', position: '', responsibility: '' });
const addMember = (): void => member.post(`/events/${props.event.slug}/committee`, { preserveScroll: true, onSuccess: () => member.reset() });
const removeMember = (id: number): void => router.delete(`/events/${props.event.slug}/committee/${id}`, { preserveScroll: true });
const positionOptions = computed(() => props.options.compulsoryPositions.filter((p) => !props.committee.some((c) => c.position === p)));
</script>

<template>
    <Head :title="`Proposal · ${event.name}`" />
    <AppLayout>
        <form class="grid gap-6 pb-24" @submit.prevent="save">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold">Event Proposal</h1>
                    <p class="text-sm text-ink-muted">06 Event Proposal Template · {{ event.name }}</p>
                </div>
                <Badge :tone="status === 'returned' ? 'warning' : status === 'approved' ? 'success' : 'neutral'">{{ status.replace('_', ' ') }}</Badge>
            </header>

            <section v-if="approval" class="rounded-card border border-line bg-surface p-5 shadow-card">
                <ApprovalTracker :progress="approval.progress" :steps="approval.steps" />
            </section>

            <p v-if="returnComment && status === 'returned'" class="rounded-card bg-warning/10 px-4 py-3 text-sm text-warning">Returned: “{{ returnComment }}” Edit and resubmit.</p>

            <FormSection title="Student Organization Concept Paper">
                <div class="grid gap-4 sm:grid-cols-3">
                    <LockedField label="To" :value="locked.to" />
                    <LockedField label="From" :value="locked.from" />
                    <LockedField label="Date of Submission" :value="locked.submitted_on ?? 'Set by the server on submit'" />
                </div>
                <Input v-model="form.name" label="Event Name" required :readonly="readOnly" :error="form.errors.name" />
                <Textarea v-model="form.summary" label="Event Summary" :rows="3" :readonly="readOnly" :error="form.errors.summary" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <Input v-model="form.expected_participants" type="number" label="Expected participants" :readonly="readOnly" :error="form.errors.expected_participants" />
                    <Input v-model="form.starts_at" type="datetime-local" label="Event starts" :readonly="readOnly" :error="form.errors.starts_at" :hint="`Earliest: ${date(minStart)}`" />
                    <Input v-model="form.ends_at" type="datetime-local" label="Event ends" :readonly="readOnly" :error="form.errors.ends_at" />
                </div>
                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">Nature of event</legend>
                    <CheckboxGroup v-model="form.nature" :options="options.natures" :columns="3" :disabled="readOnly" />
                    <Input v-if="form.nature.includes('others')" v-model="form.nature_other" label="Please specify" :readonly="readOnly" :error="form.errors.nature_other" />
                    <p v-if="form.errors.nature" class="text-xs text-danger">{{ form.errors.nature }}</p>
                </fieldset>
                <div class="grid gap-4 sm:grid-cols-3">
                    <Input v-model="form.cca_participant" type="number" label="CCA Hours · Participants" :readonly="readOnly" :hint="`Default ${duration} (1 hour = 1 CCA)`" :error="form.errors.cca_participant" />
                    <Input v-model="form.cca_committee" type="number" label="CCA Hours · Event Committee" :readonly="readOnly" :error="form.errors.cca_committee" />
                    <LockedField label="Project Duration" :value="`${duration} hours`" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="form.venue" label="Event Venue" :readonly="readOnly" :error="form.errors.venue" />
                    <Select v-model="form.mode" label="Mode" :options="[{ value: 'physical', label: 'Physical' }, { value: 'virtual', label: 'Virtual' }]" :disabled="readOnly" />
                </div>
                <div class="flex flex-wrap gap-6">
                    <Checkbox v-model="form.is_outdoor" label="Outdoor / off-campus (indemnity required from everyone)" :disabled="readOnly" />
                    <Checkbox v-model="form.is_faculty_event" label="Faculty event (adds the Dean to approvals)" :disabled="readOnly" />
                </div>
                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">SDG Goals</legend>
                    <CheckboxGroup v-model="form.sdgs" :options="options.sdgs" :columns="2" numbered :disabled="readOnly" />
                </fieldset>
                <Input v-model="form.collaboration" label="Collaboration" :readonly="readOnly" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <LockedField label="Total Budget" :value="rm(expensesSen)" />
                    <fieldset class="grid gap-2">
                        <legend class="mb-1 text-sm font-medium">Source of fund</legend>
                        <CheckboxGroup v-model="form.source_of_fund" :options="options.fundSources" :disabled="readOnly" />
                        <p v-if="form.errors.source_of_fund" class="text-xs text-danger">{{ form.errors.source_of_fund }}</p>
                    </fieldset>
                </div>
            </FormSection>

            <FormSection title="Introduction" hint="Brief introduction of the event, including the relevant SDGs.">
                <Textarea v-model="form.introduction" :rows="5" :readonly="readOnly" :error="form.errors.introduction" />
            </FormSection>

            <FormSection title="Objectives">
                <DynamicTable v-model="objectiveRows" :columns="[{ key: 'text', label: 'Objective' }]" :blank="{ text: '' }" :disabled="readOnly" :errors="form.errors" error-prefix="objectives" add-label="Add objective" />
            </FormSection>

            <FormSection title="Target Participants">
                <Input v-model="form.eligibility" label="Eligibility" :readonly="readOnly" :error="form.errors.eligibility" />
            </FormSection>

            <FormSection title="Proposed Activities">
                <DynamicTable
                    v-model="form.activities"
                    :columns="[{ key: 'name', label: 'Name of the Activity' }, { key: 'description', label: 'Description of Activity', type: 'textarea' }]"
                    :blank="{ name: '', description: '' }"
                    :disabled="readOnly"
                    :errors="form.errors"
                    error-prefix="activities"
                    add-label="Add activity"
                />
            </FormSection>

            <FormSection title="Tentative / Event Itinerary">
                <DynamicTable
                    v-model="form.itinerary"
                    :columns="[{ key: 'start', label: 'Time Start', type: 'time' }, { key: 'end', label: 'Time End', type: 'time' }, { key: 'agenda', label: 'Agenda' }]"
                    :blank="{ start: '', end: '', agenda: '' }"
                    :disabled="readOnly"
                    :errors="form.errors"
                    error-prefix="itinerary"
                />
            </FormSection>

            <FormSection title="ICT & Maintenance Requirement">
                <Textarea v-model="form.ict_requirements" :rows="3" :readonly="readOnly" />
            </FormSection>

            <FormSection title="Logistic Arrangements">
                <Checkbox v-model="form.transport_required" label="Transportation required" :disabled="readOnly" />
                <Input v-model="form.transport_arrangement" label="Arrangement" placeholder="By participants' own transport / university transport" :readonly="readOnly" />
                <div v-if="form.transport_required" class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="form.departure_time" type="time" label="Departure Time" :readonly="readOnly" />
                    <Input v-model="form.departure_pickup" label="Pickup Point" :readonly="readOnly" />
                    <Input v-model="form.return_time" type="time" label="Estimated Return Time" :readonly="readOnly" />
                    <Input v-model="form.return_pickup" label="Pickup Point" :readonly="readOnly" />
                </div>
            </FormSection>

            <FormSection title="Event Committee & Responsibilities" hint="President, Vice President, Secretary and Treasurer are compulsory. Separate from the club committee.">
                <ul class="grid gap-2">
                    <li v-for="m in committee" :key="m.id" class="flex flex-wrap items-center justify-between gap-2 rounded-control border border-line px-3 py-2 text-sm">
                        <span><strong>{{ m.position }}</strong> · {{ m.name }} <span class="text-ink-muted">{{ m.student_number }}</span><template v-if="m.responsibility"> — {{ m.responsibility }}</template></span>
                        <button v-if="!readOnly" type="button" class="text-ink-muted hover:text-danger" @click="removeMember(m.id)">Remove</button>
                    </li>
                </ul>
                <p v-if="missingCommittee.length" class="text-sm text-warning">Still needed: {{ missingCommittee.join(', ') }}</p>
                <div v-if="!readOnly" class="grid gap-3 rounded-control bg-surface-muted p-4 sm:grid-cols-[1fr_1fr_1.5fr_auto] sm:items-end">
                    <Input v-model="member.position" label="Position" :placeholder="positionOptions[0] ?? 'e.g. Logistics Head'" :error="member.errors.position" />
                    <Input v-model="member.student_number" label="Student ID" placeholder="QIU-202407-0001" :error="member.errors.student_number" />
                    <Input v-model="member.responsibility" label="Responsibility" :error="member.errors.responsibility" />
                    <Button variant="secondary" :loading="member.processing" @click="addMember">Add</Button>
                </div>
            </FormSection>

            <FormSection title="Estimated Budget">
                <BudgetTable v-model="form.budget" :disabled="readOnly" :errors="form.errors" />
            </FormSection>

            <FormSection title="Conclusion">
                <Textarea v-model="form.conclusion" :rows="4" :readonly="readOnly" :error="form.errors.conclusion" />
            </FormSection>

            <FormSection title="Approval & Verification">
                <div class="grid gap-4 sm:grid-cols-2">
                    <LockedField label="Prepared by" :value="locked.prepared_by" />
                    <LockedField label="Date" value="Server date when you sign" />
                </div>
                <p class="text-sm text-ink-muted">Then: Club Advisor → SLD Officer → Assistant Registrar → Registrar → Bursar → COO<template v-if="form.is_faculty_event"> (Dean after the advisor)</template>.</p>
            </FormSection>

            <div v-if="editable" class="fixed inset-x-0 bottom-16 z-20 border-t border-line bg-surface/95 px-4 py-3 backdrop-blur md:bottom-0">
                <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-end gap-3">
                    <div class="mr-auto grid text-xs">
                        <span class="text-ink-muted">
                            <template v-if="saving === 'saving'">Saving…</template>
                            <template v-else-if="saving === 'error'">Not saved yet — finish the highlighted field</template>
                            <template v-else-if="lastEdit.at">Saved · last edited by {{ lastEdit.by }} {{ dateTime(lastEdit.at) }}</template>
                            <template v-else>Saves automatically</template>
                        </span>
                        <span v-if="blockers.length" class="text-warning">Before submitting: {{ blockers.join(' · ') }}</span>
                    </div>
                    <Button v-if="canDiscard" variant="ghost" @click="discard">Discard</Button>
                    <Button v-if="clubCommittee.length" variant="secondary" @click="asking = true">Ask someone to submit</Button>
                    <Button :disabled="blockers.length > 0" @click="signing = true">Sign &amp; submit</Button>
                </div>
            </div>
        </form>

        <Modal v-model:open="asking" title="Ask someone to submit">
            <div class="grid gap-4">
                <p class="text-sm text-ink-muted">They get a notification with a link to this proposal. Whoever signs and submits is printed as "Prepared by" with their position.</p>
                <div class="grid gap-2">
                    <Checkbox v-for="c in clubCommittee" :key="c.id" :model-value="ask.user_ids.includes(c.id)" :label="c.name" @update:model-value="toggleAsk(c.id)" />
                </div>
                <Textarea v-model="ask.note" label="Note (optional)" :rows="2" placeholder="Budget is done, please check the itinerary and submit." />
                <p v-if="ask.errors.user_ids" class="text-xs text-danger">{{ ask.errors.user_ids }}</p>
                <Button :disabled="!ask.user_ids.length" :loading="ask.processing" @click="sendAsk">Notify</Button>
            </div>
        </Modal>

        <Modal v-model:open="signing" title="Sign as preparer">
            <div class="grid gap-4">
                <p class="text-sm text-ink-muted">Signed as <strong>{{ locked.prepared_by }}</strong>. The date and time come from the server.</p>
                <SignaturePad v-model="form.signature" v-model:strokes="form.signature_strokes" label="Your signature" :signer="locked.prepared_by" />
                <p v-if="Object.keys(form.errors).length" class="text-sm text-danger">Some fields need attention. Close this and check the highlighted fields.</p>
                <Button :disabled="!form.signature" :loading="form.processing" @click="submit">Submit proposal</Button>
            </div>
        </Modal>
    </AppLayout>
</template>
