<script setup lang="ts">
import PersonPicker from '@/Components/ui/PersonPicker.vue';
import PositionPicker from '@/Components/ui/PositionPicker.vue';
import { useIntervalFn, watchDebounced } from '@vueuse/core';
import { PROPOSAL_SECTIONS, type ProposalSection } from '@/lib/proposal';
import { useLeaveReminder } from '@/composables/useLeaveReminder';
import BudgetTable from '@/Components/forms/BudgetTable.vue';
import CheckboxGroup from '@/Components/forms/CheckboxGroup.vue';
import DynamicTable from '@/Components/forms/DynamicTable.vue';
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import FormSection from '@/Components/forms/FormSection.vue';
import LockedField from '@/Components/forms/LockedField.vue';
import Avatar from '@/Components/ui/Avatar.vue';
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
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    event: { slug: string; name: string };
    documentId: number;
    status: string;
    editable: boolean;
    approval: { progress: ApprovalProgress; steps: ApprovalStep[] } | null;
    data: ProposalData;
    locked: {
        to: string;
        from: string;
        submitted_on: string | null;
        prepared_by: string;
    };
    committee: {
        id: number;
        position: string;
        is_compulsory: boolean;
        name: string;
        student_number: string | null;
        responsibility: string | null;
    }[];
    missingCommittee: string[];
    missingClubCommittee: string[];
    returnComment: string | null;
    options: {
        natures: Record<string, string>;
        sdgs: Record<number, string>;
        fundSources: Record<string, string>;
        compulsoryPositions: string[];
    };
    leadTime: { threshold: number; small: string; large: string };
    lastEdit: { by: string | null; at: string | null };
    clubCommittee: { id: number; name: string }[];
    canDiscard: boolean;
    returnSections: { key: string; note: string }[];
    ccaLimit: number;
}>();

const form = useForm<
    ProposalData & {
        signature: string | null;
        signature_strokes: string | null;
    }
>({ ...props.data, signature: null, signature_strokes: null });
const readOnly = computed(() => !props.editable);

// CCA follows the event hours (1 hour = 1 CCA, up to SLD's limit) until the club types its own value.
const duration = computed(() => hoursBetween(form.starts_at, form.ends_at));
const cappedCca = (hours: number): number => Math.min(hours, props.ccaLimit);
watch(duration, (hours, previous) => {
    if (form.cca_participant === cappedCca(previous)) form.cca_participant = cappedCca(hours);
    if (form.cca_committee === cappedCca(previous)) form.cca_committee = cappedCca(hours);
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

// Live co-editing. Only the parts this person changed are saved (a second after they stop typing), merged on the
// server into the shared draft; every few seconds the page picks up what the others changed in parts this person
// has not touched, and shows who is editing which part.
type Editor = {
    id: number;
    name: string;
    photo_url: string | null;
    section: string | null;
};
const liveUrl = `/events/${props.event.slug}/proposal/live`;
const dataOnly = (): Record<string, unknown> => {
    const { signature: _s, signature_strokes: _t, ...data } = form.data();
    return data;
};
const snapshot = (): Record<string, string> => Object.fromEntries(Object.entries(dataOnly()).map(([k, v]) => [k, JSON.stringify(v)]));
const base: Record<string, string> = snapshot();
const saving = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
const saveError = ref<string | null>(null);
const lastEditLive = ref(props.lastEdit);
const editors = ref<Editor[]>([]);
const committeeList = ref(props.committee);
const missingList = ref(props.missingCommittee);
watch(
    () => props.committee,
    (v) => (committeeList.value = v),
);
watch(
    () => props.missingCommittee,
    (v) => (missingList.value = v),
);
const xsrf = (): string => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
const jsonHeaders = (): HeadersInit => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-XSRF-TOKEN': xsrf(),
    'X-Requested-With': 'XMLHttpRequest',
});

async function autosave(): Promise<void> {
    if (!props.editable) return;
    const now = snapshot();
    const changed = Object.keys(now).filter((k) => now[k] !== base[k]);
    if (!changed.length) return;
    saving.value = 'saving';
    try {
        const res = await fetch(liveUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: jsonHeaders(),
            body: JSON.stringify({
                changes: Object.fromEntries(changed.map((k) => [k, JSON.parse(now[k] as string)])),
            }),
        });
        const body = (await res.json()) as {
            lastEdit?: typeof props.lastEdit;
            errors?: Record<string, string[]>;
        };
        if (!res.ok) {
            saving.value = 'error';
            saveError.value = body.errors ? (Object.values(body.errors)[0]?.[0] ?? null) : null;
            return;
        }
        changed.forEach((k) => (base[k] = now[k] as string));
        if (body.lastEdit) lastEditLive.value = body.lastEdit;
        saving.value = 'saved';
        saveError.value = null;
    } catch {
        saving.value = 'error';
    }
}
watchDebounced(
    () => JSON.stringify(dataOnly()),
    () => void autosave(),
    { debounce: 500, maxWait: 1500 },
);

const mySection = ref('');
const onFocus = (e: FocusEvent): void => {
    mySection.value = (e.target as HTMLElement).closest<HTMLElement>('[data-section]')?.dataset.section ?? mySection.value;
};
async function pull(): Promise<void> {
    if (document.hidden) return;
    const res = await fetch(`${liveUrl}?section=${encodeURIComponent(mySection.value)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).catch(() => null);
    if (!res?.ok) return;
    const live = (await res.json()) as {
        status: string;
        data: Record<string, unknown>;
        lastEdit: typeof props.lastEdit;
        editors: Editor[];
        committee: typeof props.committee;
        missingCommittee: string[];
    };
    editors.value = live.editors;
    committeeList.value = live.committee;
    missingList.value = live.missingCommittee;
    if (live.status !== props.status) {
        router.reload();
        return;
    }
    const local = snapshot();
    const target = form as unknown as Record<string, unknown>;
    for (const [key, value] of Object.entries(live.data)) {
        const remote = JSON.stringify(value);
        // Take the others' change only where this person has nothing unsaved.
        if (key in local && local[key] === base[key] && remote !== base[key]) {
            target[key] = value;
            base[key] = remote;
        }
    }
    lastEditLive.value = live.lastEdit;
}
const { pause: stopPulling } = useIntervalFn(() => void pull(), 1500, {
    immediate: props.editable,
    immediateCallback: props.editable,
});
if (!props.editable) stopPulling();
const editorsIn = (section: ProposalSection): Editor[] => editors.value.filter((e) => e.section === section);
const editorsTitle = computed(() => editors.value.map((e) => (e.section ? `${e.name} · ${PROPOSAL_SECTIONS[e.section as ProposalSection] ?? e.section}` : e.name)).join('\n'));
const jumpTo = (key: string): void => document.querySelector(`[data-section='${key}']`)?.scrollIntoView({ behavior: 'smooth' });
const flag = (section: ProposalSection): string | null => props.returnSections.find((r) => r.key === section)?.note ?? null;

useLeaveReminder(
    () => `The proposal for ${props.event.name}`,
    () => props.editable,
);

const asking = ref(false);
const ask = useForm({ user_ids: [] as number[], note: '' });
const toggleAsk = (id: number): void => {
    ask.user_ids = ask.user_ids.includes(id) ? ask.user_ids.filter((x) => x !== id) : [...ask.user_ids, id];
};
const sendAsk = (): void =>
    ask.post(`/events/${props.event.slug}/proposal/ask`, {
        preserveScroll: true,
        onSuccess: () => ((asking.value = false), ask.reset()),
    });
const discard = (): void => {
    if (confirm('Discard this draft? It cannot be undone.')) router.delete(`/events/${props.event.slug}/proposal`);
};

const signing = ref(false);
const breakEven = computed(() => form.budget.revenue.reduce((s, i) => s + i.price_sen * i.quantity, 0) >= expensesSen.value);
const blockers = computed(() => [
    ...props.missingClubCommittee.map((p) => `Club committee: ${p}`),
    ...missingList.value.map((p) => `Event committee: ${p}`),
    ...(form.objectives.filter((o) => o.trim()).length < 2 ? ['At least 2 objectives'] : []),
    ...(breakEven.value ? [] : ['Revenue must cover expenses']),
]);
const submit = (): void => {
    form.transform((data) => data).post(`/events/${props.event.slug}/proposal/submit`, {
        preserveScroll: true,
        onSuccess: () => (signing.value = false),
        onError: () => (signing.value = false),
    });
};

// The four compulsory offices are fixed slots: pick the person (name or student ID) and add.
const slots = reactive<
    Record<
        string,
        {
            student_number: string;
            responsibility: string;
            error: string | null;
            busy: boolean;
        }
    >
>(
    Object.fromEntries(
        props.options.compulsoryPositions.map((p) => [
            p,
            {
                student_number: '',
                responsibility: '',
                error: null,
                busy: false,
            },
        ]),
    ),
);
const slotPickers = ref<Record<string, InstanceType<typeof PersonPicker> | null>>({});
function fillSlot(position: string): void {
    const slot = slots[position];
    if (!slot?.student_number) return;
    slot.busy = true;
    router.post(
        `/events/${props.event.slug}/committee`,
        {
            student_number: slot.student_number,
            position,
            responsibility: slot.responsibility,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                slot.student_number = '';
                slot.responsibility = '';
                slot.error = null;
                slotPickers.value[position]?.clear();
            },
            onError: (errors) => (slot.error = errors.student_number ?? errors.position ?? null),
            onFinish: () => (slot.busy = false),
        },
    );
}
const holderOf = (position: string) => committeeList.value.find((c) => c.position === position);
const otherMembers = computed(() => committeeList.value.filter((c) => !props.options.compulsoryPositions.includes(c.position)));
const member = useForm({
    student_number: '',
    position: '',
    responsibility: '',
});
const memberPicker = ref<InstanceType<typeof PersonPicker> | null>(null);
const addMember = (): void =>
    member.post(`/events/${props.event.slug}/committee`, {
        preserveScroll: true,
        onSuccess: () => {
            member.reset();
            memberPicker.value?.clear();
        },
    });
const removeMember = (id: number): void =>
    router.delete(`/events/${props.event.slug}/committee/${id}`, {
        preserveScroll: true,
    });
</script>

<template>
    <Head :title="`Proposal · ${event.name}`" />
    <AppLayout>
        <form class="grid gap-6 pb-24" @submit.prevent="save" @focusin="onFocus">
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

            <div v-if="status === 'returned' && (returnComment || returnSections.length)" class="grid gap-2 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-sm">
                <p class="font-semibold text-danger">Returned for changes. Fix the highlighted parts, then sign and submit again.</p>
                <p v-if="returnComment && !returnSections.length">“{{ returnComment }}”</p>
                <ul v-if="returnSections.length" class="grid gap-1">
                    <li v-for="r in returnSections" :key="r.key">
                        <a :href="`#`" class="font-medium text-danger underline" @click.prevent="jumpTo(r.key)">{{ PROPOSAL_SECTIONS[r.key as ProposalSection] ?? r.key }}</a
                        >: {{ r.note }}
                    </li>
                </ul>
            </div>

            <FormSection title="Student Organization Concept Paper" section="concept" :flag="flag('concept')" :editors="editorsIn('concept')">
                <div class="grid gap-4 sm:grid-cols-3">
                    <LockedField label="To" :value="locked.to" />
                    <LockedField label="From" :value="locked.from" />
                    <LockedField label="Date of Submission" :value="locked.submitted_on ?? 'Set by the server on submit'" />
                </div>
                <Input v-model="form.name" label="Event Name" required :readonly="readOnly" :error="form.errors.name" />
                <Textarea v-model="form.summary" label="Event Summary" required :rows="3" :readonly="readOnly" :error="form.errors.summary" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <Input
                        v-model="form.expected_participants"
                        type="number"
                        label="Expected participants"
                        required
                        :readonly="readOnly"
                        :error="form.errors.expected_participants"
                    />
                    <Input
                        v-model="form.starts_at"
                        type="datetime-local"
                        label="Event starts"
                        required
                        :readonly="readOnly"
                        :error="form.errors.starts_at"
                        :hint="`Earliest: ${date(minStart)}`"
                    />
                    <Input v-model="form.ends_at" type="datetime-local" label="Event ends" required :readonly="readOnly" :error="form.errors.ends_at" />
                </div>
                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">Nature of event<span class="text-danger"> *</span></legend>
                    <CheckboxGroup v-model="form.nature" :options="options.natures" :columns="3" :disabled="readOnly" />
                    <Input v-if="form.nature.includes('others')" v-model="form.nature_other" label="Please specify" :readonly="readOnly" :error="form.errors.nature_other" />
                    <p v-if="form.errors.nature" class="text-xs text-danger">
                        {{ form.errors.nature }}
                    </p>
                </fieldset>
                <div class="grid gap-4 sm:grid-cols-3">
                    <Input
                        v-model="form.cca_participant"
                        type="number"
                        label="CCA Hours · Participants"
                        required
                        :readonly="readOnly"
                        :hint="`Default ${Math.min(duration, ccaLimit)} (1 hour = 1 CCA) · SLD limit ${ccaLimit}`"
                        :error="form.errors.cca_participant"
                    />
                    <Input
                        v-model="form.cca_committee"
                        type="number"
                        label="CCA Hours · Event Committee"
                        required
                        :readonly="readOnly"
                        :hint="`Up to ${ccaLimit} (set by SLD)`"
                        :error="form.errors.cca_committee"
                    />
                    <LockedField label="Project Duration" :value="`${duration} hours`" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="form.venue" label="Event Venue" required :readonly="readOnly" :error="form.errors.venue" />
                    <Select
                        v-model="form.mode"
                        label="Mode"
                        :options="[
                            { value: 'physical', label: 'Physical' },
                            { value: 'virtual', label: 'Virtual' },
                        ]"
                        :disabled="readOnly"
                    />
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
                        <legend class="mb-1 text-sm font-medium">Source of fund<span class="text-danger"> *</span></legend>
                        <CheckboxGroup v-model="form.source_of_fund" :options="options.fundSources" :disabled="readOnly" />
                        <p v-if="form.errors.source_of_fund" class="text-xs text-danger">
                            {{ form.errors.source_of_fund }}
                        </p>
                    </fieldset>
                </div>
            </FormSection>

            <FormSection
                title="Introduction"
                section="introduction"
                :flag="flag('introduction')"
                :editors="editorsIn('introduction')"
                required
                hint="Brief introduction of the event, including the relevant SDGs."
            >
                <Textarea v-model="form.introduction" :rows="5" required :readonly="readOnly" :error="form.errors.introduction" />
            </FormSection>

            <FormSection title="Objectives" section="objectives" :flag="flag('objectives')" :editors="editorsIn('objectives')" required>
                <DynamicTable
                    v-model="objectiveRows"
                    :columns="[{ key: 'text', label: 'Objective' }]"
                    :blank="{ text: '' }"
                    :disabled="readOnly"
                    :errors="form.errors"
                    error-prefix="objectives"
                    add-label="Add objective"
                />
                <p class="text-xs" :class="form.objectives.filter((o) => o.trim()).length < 2 ? 'text-warning' : 'text-ink-muted'">
                    At least 2 objectives ({{ form.objectives.filter((o) => o.trim()).length }}
                    written).
                </p>
                <p v-if="form.errors.objectives" class="text-xs text-danger">
                    {{ form.errors.objectives }}
                </p>
            </FormSection>

            <FormSection title="Target Participants" section="participants" :flag="flag('participants')" :editors="editorsIn('participants')" required>
                <Input v-model="form.eligibility" label="Eligibility" required :readonly="readOnly" :error="form.errors.eligibility" />
            </FormSection>

            <FormSection title="Proposed Activities" section="activities" :flag="flag('activities')" :editors="editorsIn('activities')" required>
                <DynamicTable
                    v-model="form.activities"
                    :columns="[
                        { key: 'name', label: 'Name of the Activity' },
                        {
                            key: 'description',
                            label: 'Description of Activity',
                            type: 'textarea',
                        },
                    ]"
                    :blank="{ name: '', description: '' }"
                    :disabled="readOnly"
                    :errors="form.errors"
                    error-prefix="activities"
                    add-label="Add activity"
                />
            </FormSection>

            <FormSection
                title="Tentative / Event Itinerary"
                hint="Optional. List what you know so far; times can be left blank."
                section="itinerary"
                :flag="flag('itinerary')"
                :editors="editorsIn('itinerary')"
            >
                <DynamicTable
                    v-model="form.itinerary"
                    :columns="[
                        { key: 'start', label: 'Time Start', type: 'time' },
                        { key: 'end', label: 'Time End', type: 'time' },
                        { key: 'agenda', label: 'Agenda' },
                    ]"
                    :blank="{ start: '', end: '', agenda: '' }"
                    :disabled="readOnly"
                    :errors="form.errors"
                    error-prefix="itinerary"
                />
            </FormSection>

            <FormSection title="ICT & Maintenance Requirement" section="ict" :flag="flag('ict')" :editors="editorsIn('ict')">
                <Checkbox v-model="form.ict_required" label="ICT or maintenance support required (projector, sound, Wi-Fi, room setup…)" :disabled="readOnly" />
                <Transition name="fade-up">
                    <Textarea
                        v-if="form.ict_required"
                        v-model="form.ict_requirements"
                        label="What is needed"
                        required
                        :rows="3"
                        :readonly="readOnly"
                        :error="form.errors.ict_requirements"
                    />
                </Transition>
                <p v-if="!form.ict_required" class="text-sm text-ink-muted">Not ticked: the form will say <strong>Not required</strong>.</p>
            </FormSection>

            <FormSection title="Logistic Arrangements" section="logistics" :flag="flag('logistics')" :editors="editorsIn('logistics')">
                <Checkbox v-model="form.transport_required" label="Transportation required" :disabled="readOnly" />
                <p v-if="!form.transport_required" class="text-sm text-ink-muted">Not ticked: the form will say <strong>Not required</strong>.</p>
                <Input
                    v-if="form.transport_required"
                    v-model="form.transport_arrangement"
                    label="Arrangement"
                    required
                    placeholder="By participants' own transport / university transport"
                    :readonly="readOnly"
                    :error="form.errors.transport_arrangement"
                />
                <div v-if="form.transport_required" class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="form.departure_time" type="time" label="Departure Time" :readonly="readOnly" />
                    <Input v-model="form.departure_pickup" label="Pickup Point" :readonly="readOnly" />
                    <Input v-model="form.return_time" type="time" label="Estimated Return Time" :readonly="readOnly" />
                    <Input v-model="form.return_pickup" label="Pickup Point" :readonly="readOnly" />
                </div>
            </FormSection>

            <FormSection
                title="Event Committee & Responsibilities"
                section="committee"
                :flag="flag('committee')"
                :editors="editorsIn('committee')"
                required
                hint="President, Vice President, Secretary and Treasurer are compulsory. Separate from the club committee."
            >
                <ol class="grid gap-2">
                    <li
                        v-for="(pos, i) in options.compulsoryPositions"
                        :key="pos"
                        class="grid gap-3 rounded-control border p-3 sm:grid-cols-[9rem_1fr] sm:items-start"
                        :class="holderOf(pos) ? 'border-success/40 bg-success/5' : 'border-line'"
                    >
                        <p class="flex items-center gap-2 pt-2 text-sm font-semibold">
                            <span class="grid size-6 place-items-center rounded-full text-xs text-white" :class="holderOf(pos) ? 'bg-success' : 'bg-ink'">{{ i + 1 }}</span
                            >{{ pos }}<span class="text-danger">*</span>
                        </p>
                        <div v-if="holderOf(pos)" class="flex flex-wrap items-center justify-between gap-2 pt-1 text-sm">
                            <span
                                ><strong>{{ holderOf(pos)!.name }}</strong> <span class="text-ink-muted">{{ holderOf(pos)!.student_number }}</span
                                ><template v-if="holderOf(pos)!.responsibility">
                                    —
                                    {{ holderOf(pos)!.responsibility }}</template
                                ></span
                            >
                            <button v-if="!readOnly" type="button" class="text-ink-muted hover:text-danger" @click="removeMember(holderOf(pos)!.id)">Change</button>
                        </div>
                        <div v-else-if="!readOnly && slots[pos]" class="grid gap-2 sm:grid-cols-[1.4fr_1fr_auto] sm:items-end">
                            <PersonPicker
                                :ref="(el) => (slotPickers[pos] = el as InstanceType<typeof PersonPicker> | null)"
                                v-model="slots[pos]!.student_number"
                                label="Student ID or name"
                                placeholder="Type a name (e.g. tan) or student ID"
                                :error="slots[pos]!.error ?? undefined"
                            />
                            <Input v-model="slots[pos]!.responsibility" label="Responsibility" />
                            <Button variant="secondary" :disabled="!slots[pos]!.student_number" :loading="slots[pos]!.busy" @click="fillSlot(pos)">Add</Button>
                        </div>
                        <p v-else class="pt-2 text-sm text-warning">Not filled</p>
                    </li>
                </ol>

                <div class="grid gap-2">
                    <h3 class="text-sm font-semibold">Other roles</h3>
                    <ul v-if="otherMembers.length" class="grid gap-2">
                        <li v-for="m in otherMembers" :key="m.id" class="flex flex-wrap items-center justify-between gap-2 rounded-control border border-line px-3 py-2 text-sm">
                            <span
                                ><strong>{{ m.position }}</strong> ·
                                {{ m.name }}
                                <span class="text-ink-muted">{{ m.student_number }}</span
                                ><template v-if="m.responsibility"> — {{ m.responsibility }}</template></span
                            >
                            <button v-if="!readOnly" type="button" class="text-ink-muted hover:text-danger" @click="removeMember(m.id)">Remove</button>
                        </li>
                    </ul>
                    <div v-if="!readOnly" class="grid gap-3 rounded-control bg-surface-muted p-4 sm:grid-cols-[1.4fr_1fr_1.2fr_auto] sm:items-end">
                        <PersonPicker
                            ref="memberPicker"
                            v-model="member.student_number"
                            label="Student ID or name"
                            placeholder="Type a name or student ID"
                            :error="member.errors.student_number"
                        />
                        <PositionPicker v-model="member.position" :required="[]" :taken="committeeList.map((c) => c.position)" :error="member.errors.position" />
                        <Input v-model="member.responsibility" label="Responsibility" :error="member.errors.responsibility" />
                        <Button variant="secondary" :loading="member.processing" @click="addMember">Add</Button>
                    </div>
                </div>
            </FormSection>

            <FormSection title="Estimated Budget" section="budget" :flag="flag('budget')" :editors="editorsIn('budget')" required>
                <BudgetTable v-model="form.budget" :disabled="readOnly" :errors="form.errors" />
            </FormSection>

            <FormSection title="Conclusion" section="conclusion" :flag="flag('conclusion')" :editors="editorsIn('conclusion')" required>
                <Textarea v-model="form.conclusion" :rows="4" required :readonly="readOnly" :error="form.errors.conclusion" />
            </FormSection>

            <FormSection title="Approval & Verification">
                <div class="grid gap-4 sm:grid-cols-2">
                    <LockedField label="Prepared by" :value="locked.prepared_by" />
                    <LockedField label="Date" value="Server date when you sign" />
                </div>
                <p class="text-sm text-ink-muted">
                    Then: Club Advisor → SLD Officer → Assistant Registrar → Registrar → Bursar → COO<template v-if="form.is_faculty_event"> (Dean after the advisor)</template>.
                </p>
            </FormSection>

            <div v-if="editable" class="fixed inset-x-0 bottom-16 z-20 border-t border-line bg-surface/95 px-4 py-3 backdrop-blur md:bottom-0">
                <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-end gap-3">
                    <div v-if="editors.length" class="flex items-center gap-2 text-xs" :title="editorsTitle">
                        <span class="flex -space-x-2"
                            ><Avatar v-for="e in editors.slice(0, 4)" :key="e.id" :src="e.photo_url" :name="e.name" size="xs" class="ring-2 ring-surface"
                        /></span>
                        <span class="hidden text-ink-muted sm:inline">{{
                            editors.length === 1 ? `${editors[0]!.name.split(' ')[0]} is editing with you` : `${editors.length} others editing`
                        }}</span>
                    </div>
                    <div class="mr-auto grid text-xs">
                        <span class="text-ink-muted">
                            <template v-if="saving === 'saving'">Saving…</template>
                            <template v-else-if="saving === 'error'">Not saved yet: {{ saveError ?? 'finish the field you are typing in' }}</template>
                            <template v-else-if="lastEditLive.at">Saved · last edited by {{ lastEditLive.by }} {{ dateTime(lastEditLive.at) }}</template>
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
                <p class="text-sm text-ink-muted">
                    They get a notification with a link to this proposal. Whoever signs and submits is printed as "Prepared by" with their position.
                </p>
                <div class="grid gap-2">
                    <Checkbox v-for="c in clubCommittee" :key="c.id" :model-value="ask.user_ids.includes(c.id)" :label="c.name" @update:model-value="toggleAsk(c.id)" />
                </div>
                <Textarea v-model="ask.note" label="Note (optional)" :rows="2" placeholder="Budget is done, please check the itinerary and submit." />
                <p v-if="ask.errors.user_ids" class="text-xs text-danger">
                    {{ ask.errors.user_ids }}
                </p>
                <Button :disabled="!ask.user_ids.length" :loading="ask.processing" @click="sendAsk">Notify</Button>
            </div>
        </Modal>

        <Modal v-model:open="signing" title="Sign as preparer">
            <div class="grid gap-4">
                <p class="text-sm text-ink-muted">
                    Signed as <strong>{{ locked.prepared_by }}</strong
                    >. The date and time come from the server.
                </p>
                <SignaturePad v-model="form.signature" v-model:strokes="form.signature_strokes" label="Your signature" :signer="locked.prepared_by" />
                <p v-if="Object.keys(form.errors).length" class="text-sm text-danger">Some fields need attention. Close this and check the highlighted fields.</p>
                <Button :disabled="!form.signature" :loading="form.processing" @click="submit">Submit proposal</Button>
            </div>
        </Modal>
    </AppLayout>
</template>
