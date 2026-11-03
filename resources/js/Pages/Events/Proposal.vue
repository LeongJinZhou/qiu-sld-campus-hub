<script setup lang="ts">
import PersonPicker from '@/Components/ui/PersonPicker.vue';
import PositionPicker from '@/Components/ui/PositionPicker.vue';
import { useIntervalFn, watchDebounced } from '@vueuse/core';
import { PROPOSAL_SECTIONS, sectionOfField, type ProposalSection } from '@/lib/proposal';
import ProgressGauge from '@/Components/forms/ProgressGauge.vue';
import PhotoField from '@/Components/forms/PhotoField.vue';
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
import Celebration from '@/Components/ui/Celebration.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { date, dateTime, hoursBetween, rm } from '@/lib/format';
import type { ApprovalProgress, ApprovalStep, ProposalBlock, ProposalData, ProposalSpeaker, ProposalStop, ProposalTrip } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

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
    leadTime: { earliest: string; days: number };
    lastEdit: { by: string | null; at: string | null };
    clubCommittee: { id: number; name: string }[];
    canDiscard: boolean;
    returnSections: { key: string; note: string }[];
    ccaLimit: number;
    imageBase: string;
    clubNames: string[];
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
const minStart = computed(() => props.leadTime.earliest);
// Inside the lead time or below break-even: allowed only with a reason, which SLD must approve.
const isLate = computed(() => !!form.starts_at && form.starts_at.slice(0, 10) < props.leadTime.earliest);
const expensesSen = computed(() => form.budget.expenses.reduce((s, i) => s + i.price_sen * i.quantity, 0));

const objectiveRows = computed({
    get: () => form.objectives.map((text) => ({ text })),
    set: (rows: { text: string }[]) => (form.objectives = rows.map((r) => r.text)),
});
const filled = (v: string | null | undefined): boolean => !!(v ?? '').toString().trim();
const writtenObjectives = computed(() => form.objectives.filter(filled).length);

// Pictures (speaker photos, pictures in added parts) upload straight away; the form keeps their stored path.
const imageUrl = `/events/${props.event.slug}/proposal/image`;

// "Seminars/Conference/Workshop": speakers are listed (each with a photo), and two persons in charge replace the four fixed offices.
const isWorkshop = computed(() => form.nature.includes('seminar'));
const blankSpeaker = (): ProposalSpeaker => ({ name: '', phone: '', designation: '', linkedin: '', photo: '' });
const speakerDone = (p: ProposalSpeaker): boolean => filled(p.name) && filled(p.phone) && filled(p.designation) && filled(p.photo);
const addSpeaker = (): void => void (form.speakers = [...form.speakers, blankSpeaker()]);
const removeSpeaker = (i: number): void => void (form.speakers = form.speakers.filter((_, j) => j !== i));
watch(isWorkshop, (on) => {
    if (on && !form.speakers.length) form.speakers = [blankSpeaker()];
});

// Collaboration: pick partner clubs from the list, or type any other partner and add it.
const partner = ref('');
function addPartner(): void {
    const name = partner.value.trim();
    if (name && !form.collaboration.some((c) => c.toLowerCase() === name.toLowerCase())) form.collaboration = [...form.collaboration, name];
    partner.value = '';
}
const removePartner = (i: number): void => void (form.collaboration = form.collaboration.filter((_, j) => j !== i));

// Logistics: one card per trip: departure → places on the way (the last is the drop-off point) → return.
// Every time must fall within the event, each after the one before it.
const blankStop = (): ProposalStop => ({ name: '', at: '', point: '' });
const blankTrip = (): ProposalTrip => ({ arrangement: '', departure_at: '', departure_pickup: '', stops: [blankStop()], return_at: '', return_pickup: '' });
const addTrip = (): void => void (form.trips = [...form.trips, blankTrip()]);
const removeTrip = (i: number): void => void (form.trips = form.trips.filter((_, j) => j !== i));
const addStop = (trip: ProposalTrip): void => void trip.stops.push(blankStop());
const removeStop = (trip: ProposalTrip, s: number): void => void trip.stops.splice(s, 1);
const inEvent = (at: string): boolean => !!at && !!form.starts_at && !!form.ends_at && at >= form.starts_at && at <= form.ends_at;
/** Problem with one time on a trip, given the time before it (or null when fine or not filled yet). */
function timeProblem(at: string, previous: string | null): string | null {
    if (!at || !form.starts_at || !form.ends_at) return null;
    if (!inEvent(at)) return 'Must be within the event time.';
    return previous && at < previous ? 'Must be after the time before it.' : null;
}
const tripTimes = (trip: ProposalTrip): string[] => [trip.departure_at, ...trip.stops.map((st) => st.at), trip.return_at];
const tripDone = (trip: ProposalTrip): boolean => {
    const times = tripTimes(trip);
    return (
        filled(trip.arrangement) && filled(trip.departure_pickup) && filled(trip.return_pickup) && trip.stops.length > 0 &&
        trip.stops.every((st) => filled(st.name) && filled(st.point)) && times.every((t, i) => !!t && !timeProblem(t, times.slice(0, i).filter(Boolean).pop() ?? null))
    );
};
const tripError = (i: number, field: string, at: string, previous: string | null): string | undefined =>
    (form.errors as Record<string, string | undefined>)[`trips.${i}.${field}`] ?? timeProblem(at, previous) ?? undefined;

// Parts the club adds after the conclusion. Anything added must be written (or deleted) before submitting.
const BLOCK_LABELS: Record<ProposalBlock['kind'], string> = { heading: 'Heading', subheading: 'Sub-heading', paragraph: 'Paragraph', bullets: 'Bullet list', table: 'Table', image: 'Picture' };
const tableSize = reactive({ rows: 3, columns: 3 });
function addBlock(kind: ProposalBlock['kind']): void {
    const rows = kind === 'table' ? Array.from({ length: Math.min(30, Math.max(1, tableSize.rows)) }, () => Array.from({ length: Math.min(8, Math.max(1, tableSize.columns)) }, () => '')) : [];
    form.extra_sections = [...form.extra_sections, { kind, text: '', rows, image: '' }];
}
const removeBlock = (i: number): void => void (form.extra_sections = form.extra_sections.filter((_, j) => j !== i));
function moveBlock(i: number, by: -1 | 1): void {
    const list = [...form.extra_sections];
    const j = i + by;
    if (j < 0 || j >= list.length) return;
    [list[i], list[j]] = [list[j]!, list[i]!];
    form.extra_sections = list;
}
const setCell = (b: number, r: number, c: number, value: string): void => {
    form.extra_sections = form.extra_sections.map((block, i) => (i === b ? { ...block, rows: block.rows.map((row, ri) => (ri === r ? row.map((cell, ci) => (ci === c ? value : cell)) : row)) } : block));
};
const resizeTable = (b: number, what: 'row' | 'column', by: 1 | -1): void => {
    form.extra_sections = form.extra_sections.map((block, i) => {
        if (i !== b) return block;
        const cols = block.rows[0]?.length ?? 1;
        if (what === 'row') return { ...block, rows: by > 0 ? [...block.rows, Array.from({ length: cols }, () => '')] : block.rows.length > 1 ? block.rows.slice(0, -1) : block.rows };
        return { ...block, rows: block.rows.map((row) => (by > 0 ? [...row, ''] : row.length > 1 ? row.slice(0, -1) : row)) };
    });
};
const blockDone = (b: ProposalBlock): boolean =>
    b.kind === 'table' ? b.rows.length > 0 && b.rows.every((row) => row.every(filled)) : b.kind === 'image' ? filled(b.image) : filled(b.text);

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
    stopBlink(e);
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
const jumpTo = (key: string): void => document.querySelector(`[data-section='${key}']`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

// Every compulsory part, checked as people type: drives the gauge and the "take me to the problem" guide.
const checks = computed<{ key: ProposalSection; label: string; done: boolean }[]>(() => {
    const list: { key: ProposalSection; label: string; done: boolean }[] = [
        {
            key: 'concept',
            label: 'Concept paper',
            done:
                filled(form.name) && filled(form.summary) && filled(form.venue) && !!form.starts_at && !!form.ends_at && form.ends_at > form.starts_at &&
                Number(form.expected_participants) >= 1 && form.nature.length > 0 && (!form.nature.includes('others') || filled(form.nature_other)) &&
                form.source_of_fund.length > 0 && (!isLate.value || filled(form.late_reason)),
        },
        { key: 'introduction', label: 'Introduction', done: filled(form.introduction) },
        { key: 'objectives', label: `Objectives (${writtenObjectives.value}/2)`, done: writtenObjectives.value >= 2 },
        { key: 'participants', label: 'Target participants', done: filled(form.eligibility) },
    ];
    if (isWorkshop.value) list.push({ key: 'speakers', label: 'Speakers', done: form.speakers.length > 0 && form.speakers.every(speakerDone) });
    list.push({ key: 'activities', label: 'Activities', done: form.activities.length > 0 && form.activities.every((a) => filled(a.name) && filled(a.description)) });
    if (form.ict_required) list.push({ key: 'ict', label: 'ICT needs', done: filled(form.ict_requirements) });
    if (form.transport_required) list.push({ key: 'logistics', label: 'Transport', done: form.trips.length > 0 && form.trips.every(tripDone) });
    list.push(
        { key: 'committee', label: isWorkshop.value ? 'Persons in charge' : 'Committee', done: !missingList.value.length && !props.missingClubCommittee.length },
        {
            key: 'budget',
            label: 'Budget',
            done: [...form.budget.revenue, ...form.budget.expenses].every((i) => filled(i.description) && i.quantity >= 1) && (breakEven.value || filled(form.deficit_reason)),
        },
        { key: 'conclusion', label: 'Conclusion', done: filled(form.conclusion) },
    );
    if (form.extra_sections.length) list.push({ key: 'extra', label: 'Other parts', done: form.extra_sections.every(blockDone) });
    return list;
});
const blockers = computed(() => [
    ...props.missingClubCommittee.map((p) => `Club committee: ${p}`),
    ...missingList.value.map((p) => `Event committee: ${p}`),
    ...checks.value.filter((c) => !c.done && c.key !== 'committee').map((c) => c.label),
]);

// The guide: the first problem flashes red three times; once it is fixed, the next one blinks until it is touched.
const ORDER = Object.keys(PROPOSAL_SECTIONS) as ProposalSection[];
const problems = computed<ProposalSection[]>(() => {
    const bad = new Set<ProposalSection>([...checks.value.filter((c) => !c.done).map((c) => c.key), ...Object.keys(form.errors).map(sectionOfField)]);
    return ORDER.filter((k) => bad.has(k));
});
const guiding = ref(false);
const target = ref<ProposalSection | null>(null);
const alertOf = ref<{ key: ProposalSection; mode: 'flash' | 'blink' } | null>(null);
const alertFor = (key: ProposalSection): 'flash' | 'blink' | null => (alertOf.value?.key === key ? alertOf.value.mode : null);
function guideTo(key: ProposalSection, mode: 'flash' | 'blink'): void {
    target.value = key;
    alertOf.value = null;
    void nextTick(() => {
        alertOf.value = { key, mode };
        jumpTo(key);
        if (mode === 'flash') setTimeout(() => alertOf.value?.key === key && alertOf.value.mode === 'flash' && (alertOf.value = null), 2200);
    });
}
function startGuide(): boolean {
    const first = problems.value[0];
    if (!first) return false;
    guiding.value = true;
    guideTo(first, 'flash');
    return true;
}
let nextTimer: ReturnType<typeof setTimeout> | undefined;
watch(problems, (list) => {
    if (!guiding.value || !target.value || list.includes(target.value)) return;
    // Fixed: after a short pause (so typing is not interrupted), move on to the next problem, top to bottom.
    clearTimeout(nextTimer);
    nextTimer = setTimeout(() => {
        const next = problems.value[0];
        if (!next) {
            guiding.value = false;
            target.value = alertOf.value = null;
            return;
        }
        if (next !== target.value) guideTo(next, 'blink');
    }, 900);
});
const stopBlink = (e: Event): void => {
    const section = (e.target as HTMLElement).closest<HTMLElement>('[data-section]')?.dataset.section;
    if (alertOf.value?.mode === 'blink' && section === alertOf.value.key) alertOf.value = null;
};
const trySubmit = (): void => {
    if (!startGuide()) signing.value = true;
};
const submit = (): void => {
    form.transform((data) => data).post(`/events/${props.event.slug}/proposal/submit`, {
        preserveScroll: true,
        onSuccess: () => (signing.value = false),
        onError: () => {
            signing.value = false;
            void nextTick(startGuide);
        },
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

// The first time the committee opens its approved proposal on this device, celebrate it.
const celebrate = ref<string | null>(null);
onMounted(() => {
    if (props.status !== 'approved') return;
    const key = `celebrated:proposal:${props.event.slug}`;
    try {
        if (localStorage.getItem(key)) return;
        localStorage.setItem(key, '1');
    } catch {
        return;
    }
    celebrate.value = 'Proposal approved!';
});
</script>

<template>
    <Head :title="`Proposal · ${event.name}`" />
    <AppLayout>
        <form class="grid gap-6 pb-8" @submit.prevent="save" @focusin="onFocus" @pointerdown="stopBlink">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold">Event Proposal</h1>
                    <p class="text-sm text-ink-muted">06 Event Proposal Template · {{ event.name }}</p>
                </div>
                <Badge :tone="status === 'returned' ? 'warning' : status === 'approved' ? 'success' : 'neutral'">{{ status.replace('_', ' ') }}</Badge>
            </header>

            <section v-if="status === 'approved'" class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-success/40 bg-success/5 px-4 py-3">
                <p class="text-sm"><strong class="text-success">Approved.</strong> Download the signed proposal to keep a copy.</p>
                <div class="flex gap-2">
                    <a :href="`/documents/${documentId}/download/pdf`" class="rounded-control bg-ink px-4 py-2 text-sm font-semibold text-white hover:bg-ink/85">Download PDF</a>
                    <a :href="`/documents/${documentId}/download/docx`" class="rounded-control border border-line bg-surface px-4 py-2 text-sm font-medium hover:bg-surface-muted">Word</a>
                </div>
            </section>

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

            <ProgressGauge v-if="editable" :items="checks" title="Compulsory parts: tap one to go there" @jump="jumpTo" />

            <FormSection title="Student Organization Concept Paper" section="concept" :alert="alertFor('concept')" :flag="flag('concept')" :editors="editorsIn('concept')">
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
                        :hint="`Submit ${leadTime.days} days ahead: earliest ${date(minStart)}`"
                    />
                    <Input v-model="form.ends_at" type="datetime-local" label="Event ends" required :readonly="readOnly" :error="form.errors.ends_at" />
                </div>
                <Transition name="fade-up">
                    <Textarea
                        v-if="isLate"
                        v-model="form.late_reason"
                        label="Why it is submitted late"
                        required
                        :rows="2"
                        :readonly="readOnly"
                        :placeholder="`This is inside the ${leadTime.days}-day lead time. Explain why; SLD decides.`"
                        :error="form.errors.late_reason"
                    />
                </Transition>
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
                <div class="grid gap-2">
                    <span class="text-sm font-medium">Collaboration <span class="font-normal text-ink-muted">(optional)</span></span>
                    <ul v-if="form.collaboration.length" class="flex flex-wrap gap-1.5">
                        <li v-for="(c, i) in form.collaboration" :key="c" class="flex items-center gap-1 rounded-full border border-line bg-surface-muted py-1 pr-1.5 pl-3 text-sm">
                            {{ c }}
                            <button v-if="!readOnly" type="button" class="grid size-5 place-items-center rounded-full text-ink-muted hover:bg-danger/10 hover:text-danger" :aria-label="`Remove ${c}`" @click="removePartner(i)">✕</button>
                        </li>
                    </ul>
                    <div v-if="!readOnly" class="flex gap-2">
                        <input
                            v-model="partner"
                            list="partner-clubs"
                            placeholder="Pick a club, or type another partner"
                            aria-label="Add a partner"
                            class="h-10 min-w-0 flex-1 rounded-control border border-line bg-surface px-3 text-sm"
                            @keydown.enter.prevent="addPartner"
                            @change="clubNames.includes(partner) && addPartner()"
                        />
                        <Button size="sm" variant="secondary" :disabled="!partner.trim()" @click="addPartner">Add</Button>
                        <datalist id="partner-clubs">
                            <option v-for="name in clubNames" :key="name" :value="name" />
                        </datalist>
                    </div>
                    <p v-else-if="!form.collaboration.length" class="text-sm text-ink-muted">None</p>
                </div>
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
                section="introduction" :alert="alertFor('introduction')"
                :flag="flag('introduction')"
                :editors="editorsIn('introduction')"
                required
                hint="Brief introduction of the event, including the relevant SDGs."
            >
                <Textarea v-model="form.introduction" :rows="5" required :readonly="readOnly" :error="form.errors.introduction" />
            </FormSection>

            <FormSection title="Objectives" section="objectives" :alert="alertFor('objectives')" :flag="flag('objectives')" :editors="editorsIn('objectives')" required>
                <DynamicTable
                    v-model="objectiveRows"
                    :columns="[{ key: 'text', label: 'Objective' }]"
                    :blank="{ text: '' }"
                    :disabled="readOnly"
                    :errors="form.errors"
                    error-prefix="objectives"
                    add-label="Add another objective"
                    :min="2"
                />
                <p class="text-xs" :class="writtenObjectives < 2 ? 'text-warning' : 'text-success'">
                    At least 2 objectives; add more if you need them ({{ writtenObjectives }} written).
                </p>
                <p v-if="form.errors.objectives" class="text-xs text-danger">
                    {{ form.errors.objectives }}
                </p>
            </FormSection>

            <FormSection title="Target Participants" section="participants" :alert="alertFor('participants')" :flag="flag('participants')" :editors="editorsIn('participants')" required>
                <Input v-model="form.eligibility" label="Eligibility" required :readonly="readOnly" :error="form.errors.eligibility" />
            </FormSection>

            <FormSection
                v-if="isWorkshop"
                title="Speaker / External Profile"
                section="speakers"
                :alert="alertFor('speakers')"
                :flag="flag('speakers')"
                :editors="editorsIn('speakers')"
                required
                hint="Workshop: every speaker or external person, with a photo, phone and designation. LinkedIn is optional."
            >
                <TransitionGroup name="list" tag="ol" class="relative grid gap-3">
                    <li v-for="(p, i) in form.speakers" :key="i" class="grid gap-3 rounded-control border p-4 sm:grid-cols-[auto_1fr]" :class="speakerDone(p) ? 'border-line' : 'border-warning/60'">
                        <PhotoField
                            v-model="p.photo"
                            :upload-url="imageUrl"
                            :image-base="imageBase"
                            label="Photo"
                            required
                            :readonly="readOnly"
                            :error="form.errors[`speakers.${i}.photo`]"
                        />
                        <div class="grid content-start gap-3">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold">Person {{ i + 1 }}</h3>
                                <button v-if="!readOnly && form.speakers.length > 1" type="button" class="text-sm text-ink-muted hover:text-danger" @click="removeSpeaker(i)">Remove</button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <Input v-model="p.name" label="Name" required :readonly="readOnly" :error="form.errors[`speakers.${i}.name`]" />
                                <Input v-model="p.designation" label="Designation" required :readonly="readOnly" :error="form.errors[`speakers.${i}.designation`]" />
                                <Input v-model="p.phone" type="tel" label="Phone number" required :readonly="readOnly" :error="form.errors[`speakers.${i}.phone`]" />
                                <Input v-model="p.linkedin" label="LinkedIn profile (optional)" :readonly="readOnly" :error="form.errors[`speakers.${i}.linkedin`]" />
                            </div>
                        </div>
                    </li>
                </TransitionGroup>
                <button v-if="!readOnly" type="button" class="justify-self-start text-sm font-medium text-brand-blue" @click="addSpeaker">＋ Add another person</button>
                <p v-if="form.errors.speakers" class="text-xs text-danger">{{ form.errors.speakers }}</p>
            </FormSection>

            <FormSection title="Proposed Activities" section="activities" :alert="alertFor('activities')" :flag="flag('activities')" :editors="editorsIn('activities')" required>
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
                section="itinerary" :alert="alertFor('itinerary')"
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

            <FormSection title="ICT & Maintenance Requirement" section="ict" :alert="alertFor('ict')" :flag="flag('ict')" :editors="editorsIn('ict')">
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

            <FormSection title="Logistic Arrangements" section="logistics" :alert="alertFor('logistics')" :flag="flag('logistics')" :editors="editorsIn('logistics')">
                <Checkbox v-model="form.transport_required" label="Transportation required" :disabled="readOnly" />
                <p v-if="!form.transport_required" class="text-sm text-ink-muted">Not ticked: the form will say <strong>Not required</strong>.</p>
                <template v-else>
                    <TransitionGroup name="list" tag="ol" class="relative grid gap-3">
                        <li v-for="(trip, i) in form.trips" :key="i" class="grid gap-3 rounded-control border border-line p-4">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold">Trip {{ i + 1 }}</h3>
                                <button v-if="!readOnly && form.trips.length > 1" type="button" class="text-sm text-ink-muted hover:text-danger" @click="removeTrip(i)">Remove</button>
                            </div>
                            <Input
                                v-model="trip.arrangement"
                                label="Arrangement"
                                required
                                placeholder="By participants' own transport / university transport"
                                :readonly="readOnly"
                                :error="form.errors[`trips.${i}.arrangement`]"
                            />
                            <div class="grid gap-3 sm:grid-cols-2">
                                <Input
                                    v-model="trip.departure_at"
                                    type="datetime-local"
                                    label="Departure date & time"
                                    required
                                    :min="form.starts_at"
                                    :max="form.ends_at"
                                    :readonly="readOnly"
                                    :error="tripError(i, 'departure_at', trip.departure_at, null)"
                                />
                                <Input v-model="trip.departure_pickup" label="Pickup point" required :readonly="readOnly" :error="form.errors[`trips.${i}.departure_pickup`]" />
                            </div>
                            <div class="grid gap-2 border-l-2 border-brand-cyan/50 pl-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Destinations in order · the last one is the drop-off point</p>
                                <div v-for="(st, s) in trip.stops" :key="s" class="grid gap-2 rounded-control bg-surface-muted p-3">
                                    <div class="flex items-center justify-between gap-2 text-sm font-medium">
                                        <span>{{ s === trip.stops.length - 1 ? 'Drop-off point (last destination)' : `Stop ${s + 1}` }}</span>
                                        <button v-if="!readOnly && trip.stops.length > 1" type="button" class="text-xs text-ink-muted hover:text-danger" @click="removeStop(trip, s)">Remove</button>
                                    </div>
                                    <div class="grid gap-2 sm:grid-cols-3">
                                        <Input v-model="st.name" label="Location name" required :readonly="readOnly" :error="form.errors[`trips.${i}.stops.${s}.name`]" />
                                        <Input
                                            v-model="st.at"
                                            type="datetime-local"
                                            label="Date & time"
                                            required
                                            :min="form.starts_at"
                                            :max="form.ends_at"
                                            :readonly="readOnly"
                                            :error="tripError(i, `stops.${s}.at`, st.at, [trip.departure_at, ...trip.stops.slice(0, s).map((x) => x.at)].filter(Boolean).pop() ?? null)"
                                        />
                                        <Input v-model="st.point" label="Point" placeholder="Main gate, lobby…" required :readonly="readOnly" :error="form.errors[`trips.${i}.stops.${s}.point`]" />
                                    </div>
                                </div>
                                <p v-if="form.errors[`trips.${i}.stops`]" class="text-xs text-danger">{{ form.errors[`trips.${i}.stops`] }}</p>
                                <button v-if="!readOnly" type="button" class="justify-self-start text-xs font-medium text-brand-blue" @click="addStop(trip)">＋ Add a place on the way</button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <Input
                                    v-model="trip.return_at"
                                    type="datetime-local"
                                    label="Estimated return date & time"
                                    required
                                    :min="form.starts_at"
                                    :max="form.ends_at"
                                    :readonly="readOnly"
                                    :error="tripError(i, 'return_at', trip.return_at, tripTimes(trip).slice(0, -1).filter(Boolean).pop() ?? null)"
                                />
                                <Input v-model="trip.return_pickup" label="Return pickup point" required :readonly="readOnly" :error="form.errors[`trips.${i}.return_pickup`]" />
                            </div>
                            <p class="text-xs text-ink-muted">All times must be within the event: {{ form.starts_at.replace('T', ' ') }} – {{ form.ends_at.replace('T', ' ') }}.</p>
                        </li>
                    </TransitionGroup>
                    <button v-if="!readOnly" type="button" class="justify-self-start text-sm font-medium text-brand-blue" @click="addTrip">＋ Add another trip (different place or time)</button>
                </template>
            </FormSection>

            <FormSection
                title="Event Committee & Responsibilities"
                section="committee" :alert="alertFor('committee')"
                :flag="flag('committee')"
                :editors="editorsIn('committee')"
                required
                :hint="
                    isWorkshop
                        ? 'Workshop: the four offices are optional, but at least 2 persons in charge are needed. Add them below (e.g. role: Person in Charge).'
                        : 'President, Vice President, Secretary and Treasurer are compulsory. Separate from the club committee.'
                "
            >
                <p v-if="missingList.length" class="rounded-control bg-warning/10 px-3 py-2 text-sm text-warning">Still needed: {{ missingList.join(', ') }}</p>
                <ol class="grid gap-2">
                    <li
                        v-for="(pos, i) in options.compulsoryPositions"
                        :key="pos"
                        class="grid gap-3 rounded-control border p-3 sm:grid-cols-[9rem_1fr] sm:items-start"
                        :class="holderOf(pos) ? 'border-success/40 bg-success/5' : 'border-line'"
                    >
                        <p class="flex items-center gap-2 pt-2 text-sm font-semibold">
                            <span class="grid size-6 place-items-center rounded-full text-xs text-white" :class="holderOf(pos) ? 'bg-success' : 'bg-ink'">{{ i + 1 }}</span
                            >{{ pos }}<span v-if="!isWorkshop" class="text-danger">*</span>
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
                        <p v-else class="pt-2 text-sm" :class="isWorkshop ? 'text-ink-muted' : 'text-warning'">{{ isWorkshop ? 'Optional' : 'Not filled' }}</p>
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

            <FormSection title="Estimated Budget" section="budget" :alert="alertFor('budget')" :flag="flag('budget')" :editors="editorsIn('budget')" required>
                <BudgetTable v-model="form.budget" :disabled="readOnly" :errors="form.errors" />
                <Transition name="fade-up">
                    <Textarea
                        v-if="!breakEven"
                        v-model="form.deficit_reason"
                        label="Why the event runs at a deficit"
                        required
                        :rows="2"
                        :readonly="readOnly"
                        placeholder="e.g. A community service event funded from the club account; SLD decides."
                        :error="form.errors.deficit_reason"
                    />
                </Transition>
            </FormSection>

            <FormSection title="Conclusion" section="conclusion" :alert="alertFor('conclusion')" :flag="flag('conclusion')" :editors="editorsIn('conclusion')" required>
                <Textarea v-model="form.conclusion" :rows="4" required :readonly="readOnly" :error="form.errors.conclusion" />
            </FormSection>

            <FormSection
                v-if="form.extra_sections.length || !readOnly"
                title="Other parts"
                section="extra"
                :alert="alertFor('extra')"
                :flag="flag('extra')"
                :editors="editorsIn('extra')"
                hint="Optional. Add headings, paragraphs, bullet lists, pictures or tables; they print after the conclusion. Anything added must be filled in or deleted before submitting."
            >
                <TransitionGroup name="list" tag="ol" class="relative grid gap-3">
                    <li v-for="(block, b) in form.extra_sections" :key="b" class="grid gap-2 rounded-control border p-3" :class="blockDone(block) ? 'border-line' : 'border-warning/60'">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                            <Badge>{{ BLOCK_LABELS[block.kind] }}</Badge>
                            <div v-if="!readOnly" class="flex gap-3 text-ink-muted">
                                <button type="button" class="hover:text-ink disabled:opacity-30" :disabled="b === 0" aria-label="Move up" @click="moveBlock(b, -1)">↑</button>
                                <button type="button" class="hover:text-ink disabled:opacity-30" :disabled="b === form.extra_sections.length - 1" aria-label="Move down" @click="moveBlock(b, 1)">↓</button>
                                <button type="button" class="hover:text-danger" @click="removeBlock(b)">Delete</button>
                            </div>
                        </div>
                        <Input v-if="block.kind === 'heading' || block.kind === 'subheading'" v-model="block.text" :label="BLOCK_LABELS[block.kind]" :readonly="readOnly" :error="form.errors[`extra_sections.${b}.text`]" />
                        <Textarea
                            v-else-if="block.kind === 'paragraph' || block.kind === 'bullets'"
                            v-model="block.text"
                            :label="block.kind === 'bullets' ? 'Bullet points (one per line)' : 'Paragraph'"
                            :rows="block.kind === 'bullets' ? 4 : 3"
                            :readonly="readOnly"
                            :error="form.errors[`extra_sections.${b}.text`]"
                        />
                        <div v-else-if="block.kind === 'image'" class="grid gap-2">
                            <PhotoField
                                v-model="block.image"
                                :upload-url="imageUrl"
                                :image-base="imageBase"
                                label="Picture"
                                shape="wide"
                                required
                                :readonly="readOnly"
                                :error="form.errors[`extra_sections.${b}.image`]"
                            />
                            <Input v-model="block.text" label="Caption (optional)" :readonly="readOnly" />
                            <p class="text-xs text-ink-muted">Printed centred, sized to read clearly on A4 (up to 12 × 9 cm).</p>
                        </div>
                        <div v-else class="grid gap-2">
                            <div class="overflow-x-auto rounded-control border border-line">
                                <table class="w-full text-sm">
                                    <tbody>
                                        <tr v-for="(row, r) in block.rows" :key="r" class="border-t border-line first:border-t-0" :class="r === 0 ? 'bg-surface-muted font-medium' : ''">
                                            <td v-for="(cell, c) in row" :key="c" class="min-w-28 border-l border-line p-1 first:border-l-0">
                                                <input
                                                    :value="cell"
                                                    :readonly="readOnly"
                                                    :placeholder="r === 0 ? `Header ${c + 1}` : ''"
                                                    :aria-label="`Row ${r + 1}, column ${c + 1}`"
                                                    class="h-9 w-full rounded-control border px-2"
                                                    :class="filled(cell) ? 'border-line' : 'border-warning/60'"
                                                    @input="setCell(b, r, c, ($event.target as HTMLInputElement).value)"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="!readOnly" class="flex flex-wrap gap-3 text-xs font-medium text-brand-blue">
                                <button type="button" @click="resizeTable(b, 'row', 1)">＋ Row</button>
                                <button type="button" @click="resizeTable(b, 'row', -1)">− Row</button>
                                <button type="button" @click="resizeTable(b, 'column', 1)">＋ Column</button>
                                <button type="button" @click="resizeTable(b, 'column', -1)">− Column</button>
                            </div>
                            <p v-if="form.errors[`extra_sections.${b}.rows`]" class="text-xs text-danger">{{ form.errors[`extra_sections.${b}.rows`] }}</p>
                        </div>
                    </li>
                </TransitionGroup>
                <div v-if="!readOnly" class="grid gap-3 rounded-control bg-surface-muted p-3">
                    <p class="text-sm font-medium">＋ Add others</p>
                    <div class="flex flex-wrap gap-2">
                        <Button v-for="kind in ['heading', 'subheading', 'paragraph', 'bullets', 'image'] as const" :key="kind" size="sm" variant="secondary" @click="addBlock(kind)">{{ BLOCK_LABELS[kind] }}</Button>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <Input v-model.number="tableSize.rows" type="number" label="Rows" class="w-24" min="1" max="30" />
                        <Input v-model.number="tableSize.columns" type="number" label="Columns" class="w-24" min="1" max="8" />
                        <Button size="sm" variant="secondary" @click="addBlock('table')">Add table</Button>
                    </div>
                </div>
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

            <!-- At the end of the form (not docked), so it never covers the form on a phone or a laptop. -->
            <div v-if="editable" class="rounded-card border border-line bg-surface p-4 shadow-card">
                <div class="flex flex-wrap items-center justify-end gap-3">
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
                        <span v-if="blockers.length" class="text-warning">Still to do: {{ blockers.join(' · ') }}. Press Sign &amp; submit to be taken there.</span>
                    </div>
                    <Button v-if="canDiscard" variant="ghost" @click="discard">Discard</Button>
                    <Button v-if="clubCommittee.length" variant="secondary" @click="asking = true">Ask someone to submit</Button>
                    <Button @click="trySubmit">Sign &amp; submit</Button>
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
        <Celebration :text="celebrate" detail="Every signature is in. Time to announce it on the feed." />
    </AppLayout>
</template>
