import BudgetTable from '@react/Components/forms/BudgetTable';
import CheckboxGroup from '@react/Components/forms/CheckboxGroup';
import DynamicTable from '@react/Components/forms/DynamicTable';
import ApprovalTracker from '@react/Components/forms/ApprovalTracker';
import FormSection from '@react/Components/forms/FormSection';
import LockedField from '@react/Components/forms/LockedField';
import PhotoField from '@react/Components/forms/PhotoField';
import ProgressGauge from '@react/Components/forms/ProgressGauge';
import Avatar from '@react/Components/ui/Avatar';
import Badge from '@react/Components/ui/Badge';
import Button from '@react/Components/ui/Button';
import Celebration from '@react/Components/ui/Celebration';
import Checkbox from '@react/Components/ui/Checkbox';
import Input from '@react/Components/ui/Input';
import Modal from '@react/Components/ui/Modal';
import PersonPicker, { type PersonPickerHandle } from '@react/Components/ui/PersonPicker';
import PositionPicker from '@react/Components/ui/PositionPicker';
import Select from '@react/Components/ui/Select';
import SignaturePad from '@react/Components/ui/SignaturePad';
import Textarea from '@react/Components/ui/Textarea';
import { useLeaveReminder } from '@react/hooks/useLeaveReminder';
import AppLayout from '@react/Layouts/AppLayout';
import { date, dateTime, hoursBetween, rm } from '@/lib/format';
import { PROPOSAL_SECTIONS, sectionOfField, type ProposalSection } from '@/lib/proposal';
import type { ApprovalProgress, ApprovalStep, ProposalBlock, ProposalData, ProposalSpeaker, ProposalStop, ProposalTrip } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FocusEvent, type FormEvent, type SyntheticEvent } from 'react';

interface CommitteeRow {
    id: number;
    position: string;
    is_compulsory: boolean;
    name: string;
    student_number: string | null;
    responsibility: string | null;
}
type LastEdit = { by: string | null; at: string | null };
type Editor = { id: number; name: string; photo_url: string | null; section: string | null };
interface ProposalProps {
    event: { slug: string; name: string };
    documentId: number;
    status: string;
    editable: boolean;
    approval: { progress: ApprovalProgress; steps: ApprovalStep[] } | null;
    data: ProposalData;
    locked: { to: string; from: string; submitted_on: string | null; prepared_by: string };
    committee: CommitteeRow[];
    missingCommittee: string[];
    missingClubCommittee: string[];
    returnComment: string | null;
    options: { natures: Record<string, string>; sdgs: Record<number, string>; fundSources: Record<string, string>; compulsoryPositions: string[] };
    leadTime: { earliest: string; days: number };
    lastEdit: LastEdit;
    clubCommittee: { id: number; name: string }[];
    canDiscard: boolean;
    returnSections: { key: string; note: string }[];
    ccaLimit: number;
    imageBase: string;
    clubNames: string[];
}
type FormData = ProposalData & { signature: string | null; signature_strokes: string | null };
type Slot = { student_number: string; responsibility: string; error: string | null; busy: boolean };

const BLOCK_LABELS: Record<ProposalBlock['kind'], string> = { heading: 'Heading', subheading: 'Sub-heading', paragraph: 'Paragraph', bullets: 'Bullet list', table: 'Table', image: 'Picture' };
const ORDER = Object.keys(PROPOSAL_SECTIONS) as ProposalSection[];
const filled = (v: string | number | null | undefined): boolean => !!(v ?? '').toString().trim();
const blankSpeaker = (): ProposalSpeaker => ({ name: '', phone: '', designation: '', linkedin: '', photo: '' });
const speakerDone = (p: ProposalSpeaker): boolean => filled(p.name) && filled(p.phone) && filled(p.designation) && filled(p.photo);
const blankStop = (): ProposalStop => ({ name: '', at: '', point: '' });
const blankTrip = (): ProposalTrip => ({ arrangement: '', departure_at: '', departure_pickup: '', stops: [blankStop()], return_at: '', return_pickup: '' });
const blockDone = (b: ProposalBlock): boolean => (b.kind === 'table' ? b.rows.length > 0 && b.rows.every((row) => row.every((c) => filled(c))) : b.kind === 'image' ? filled(b.image) : filled(b.text));
const tripTimes = (trip: ProposalTrip): string[] => [trip.departure_at, ...trip.stops.map((st) => st.at), trip.return_at];
const strip = (d: FormData): ProposalData => {
    const { signature: _s, signature_strokes: _t, ...rest } = d;
    return rest;
};
const snapshotOf = (d: ProposalData): Record<string, string> => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, JSON.stringify(v)]));
const xsrf = (): string => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
const jsonHeaders = (): HeadersInit => ({ Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' });

/** The 06 Event Proposal: live co-editing, a gauge and guide to what is missing, committee slots, budget, and sign & submit. */
export default function Proposal(props: ProposalProps) {
    const { event, documentId, status, editable, approval, locked, returnComment, options, leadTime, clubCommittee, canDiscard, returnSections, ccaLimit, imageBase, clubNames } = props;
    const form = useForm<FormData>({ ...props.data, signature: null, signature_strokes: null });
    const d = form.data;
    const errors = form.errors as Record<string, string | undefined>;
    const set = <K extends keyof FormData>(key: K, value: FormData[K]): void => form.setData((current) => ({ ...current, [key]: value }));
    const readOnly = !editable;

    // CCA follows the event hours (1 hour = 1 CCA, up to SLD's limit) until the club types its own value.
    const duration = hoursBetween(d.starts_at, d.ends_at);
    const capped = (hours: number): number => Math.min(hours, ccaLimit);
    const previousDuration = useRef(duration);
    useEffect(() => {
        const previous = previousDuration.current;
        previousDuration.current = duration;
        if (previous === duration) return;
        form.setData((cur) => ({
            ...cur,
            cca_participant: Number(cur.cca_participant) === capped(previous) ? capped(duration) : cur.cca_participant,
            cca_committee: Number(cur.cca_committee) === capped(previous) ? capped(duration) : cur.cca_committee,
        }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [duration]);
    // Inside the lead time or below break-even: allowed only with a reason, which SLD must approve.
    const isLate = !!d.starts_at && d.starts_at.slice(0, 10) < leadTime.earliest;
    const expensesSen = d.budget.expenses.reduce((s, i) => s + i.price_sen * i.quantity, 0);
    const breakEven = d.budget.revenue.reduce((s, i) => s + i.price_sen * i.quantity, 0) >= expensesSen;
    const writtenObjectives = d.objectives.filter((o) => filled(o)).length;
    // Pictures (speaker photos, pictures in added parts) upload straight away; the form keeps their stored path.
    const imageUrl = `/events/${event.slug}/proposal/image`;

    // "Seminars/Conference/Workshop": speakers are listed (each with a photo), and two persons in charge replace the four fixed offices.
    const isWorkshop = d.nature.includes('seminar');
    useEffect(() => {
        if (isWorkshop && !d.speakers.length) set('speakers', [blankSpeaker()]);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isWorkshop]);
    const setSpeaker = (i: number, patch: Partial<ProposalSpeaker>): void => set('speakers', d.speakers.map((p, j) => (j === i ? { ...p, ...patch } : p)));

    // Collaboration: pick partner clubs from the list, or type any other partner and add it.
    const [partner, setPartner] = useState('');
    const addPartner = (name = partner): void => {
        const clean = name.trim();
        if (clean && !d.collaboration.some((c) => c.toLowerCase() === clean.toLowerCase())) set('collaboration', [...d.collaboration, clean]);
        setPartner('');
    };

    // Logistics: one card per trip: departure → places on the way (the last is the drop-off point) → return.
    // Every time must fall within the event, each after the one before it.
    const inEvent = (at: string): boolean => !!at && !!d.starts_at && !!d.ends_at && at >= d.starts_at && at <= d.ends_at;
    const timeProblem = (at: string, previous: string | null): string | null => {
        if (!at || !d.starts_at || !d.ends_at) return null;
        if (!inEvent(at)) return 'Must be within the event time.';
        return previous && at < previous ? 'Must be after the time before it.' : null;
    };
    const tripDone = (trip: ProposalTrip): boolean => {
        const times = tripTimes(trip);
        return (
            filled(trip.arrangement) &&
            filled(trip.departure_pickup) &&
            filled(trip.return_pickup) &&
            trip.stops.length > 0 &&
            trip.stops.every((st) => filled(st.name) && filled(st.point)) &&
            times.every((t, i) => !!t && !timeProblem(t, times.slice(0, i).filter(Boolean).pop() ?? null))
        );
    };
    const tripError = (i: number, field: string, at: string, previous: string | null): string | undefined => errors[`trips.${i}.${field}`] ?? timeProblem(at, previous) ?? undefined;
    const setTrip = (i: number, patch: Partial<ProposalTrip>): void => set('trips', d.trips.map((t, j) => (j === i ? { ...t, ...patch } : t)));
    const setStop = (i: number, s: number, patch: Partial<ProposalStop>): void => setTrip(i, { stops: (d.trips[i] as ProposalTrip).stops.map((st, k) => (k === s ? { ...st, ...patch } : st)) });

    // Parts the club adds after the conclusion. Anything added must be written (or deleted) before submitting.
    const [tableSize, setTableSize] = useState({ rows: '3', columns: '3' });
    const addBlock = (kind: ProposalBlock['kind']): void => {
        const rows = kind === 'table' ? Array.from({ length: Math.min(30, Math.max(1, Number(tableSize.rows) || 1)) }, () => Array.from({ length: Math.min(8, Math.max(1, Number(tableSize.columns) || 1)) }, () => '')) : [];
        set('extra_sections', [...d.extra_sections, { kind, text: '', rows, image: '' }]);
    };
    const setBlock = (b: number, patch: Partial<ProposalBlock>): void => set('extra_sections', d.extra_sections.map((block, i) => (i === b ? { ...block, ...patch } : block)));
    const moveBlock = (i: number, by: -1 | 1): void => {
        const list = [...d.extra_sections];
        const j = i + by;
        if (j < 0 || j >= list.length) return;
        [list[i], list[j]] = [list[j]!, list[i]!];
        set('extra_sections', list);
    };
    const setCell = (b: number, r: number, c: number, value: string): void =>
        setBlock(b, { rows: (d.extra_sections[b] as ProposalBlock).rows.map((row, ri) => (ri === r ? row.map((cell, ci) => (ci === c ? value : cell)) : row)) });
    const resizeTable = (b: number, what: 'row' | 'column', by: 1 | -1): void => {
        const block = d.extra_sections[b] as ProposalBlock;
        const cols = block.rows[0]?.length ?? 1;
        const rows =
            what === 'row'
                ? by > 0
                    ? [...block.rows, Array.from({ length: cols }, () => '')]
                    : block.rows.length > 1
                      ? block.rows.slice(0, -1)
                      : block.rows
                : block.rows.map((row) => (by > 0 ? [...row, ''] : row.length > 1 ? row.slice(0, -1) : row));
        setBlock(b, { rows });
    };

    // Live co-editing. Only the parts this person changed are saved (half a second after they stop typing, at most 1.5 s
    // apart), merged on the server into the shared draft; every 1.5 s the page picks up what the others changed in parts
    // this person has not touched, and shows who is editing which part.
    const liveUrl = `/events/${event.slug}/proposal/live`;
    const base = useRef<Record<string, string>>(snapshotOf(strip({ ...props.data, signature: null, signature_strokes: null })));
    const latest = useRef(d);
    latest.current = d;
    const [saving, setSaving] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
    const [saveError, setSaveError] = useState<string | null>(null);
    const [lastEdit, setLastEdit] = useState<LastEdit>(props.lastEdit);
    const [editors, setEditors] = useState<Editor[]>([]);
    const [committeeList, setCommitteeList] = useState(props.committee);
    const [missingList, setMissingList] = useState(props.missingCommittee);
    useEffect(() => setCommitteeList(props.committee), [props.committee]);
    useEffect(() => setMissingList(props.missingCommittee), [props.missingCommittee]);

    const autosave = async (): Promise<void> => {
        if (!editable) return;
        const now = snapshotOf(strip(latest.current));
        const changed = Object.keys(now).filter((k) => now[k] !== base.current[k]);
        if (!changed.length) return;
        setSaving('saving');
        try {
            const res = await fetch(liveUrl, { method: 'PATCH', credentials: 'same-origin', headers: jsonHeaders(), body: JSON.stringify({ changes: Object.fromEntries(changed.map((k) => [k, JSON.parse(now[k] as string)])) }) });
            const body = (await res.json()) as { lastEdit?: LastEdit; errors?: Record<string, string[]> };
            if (!res.ok) {
                setSaving('error');
                setSaveError(body.errors ? (Object.values(body.errors)[0]?.[0] ?? null) : null);
                return;
            }
            changed.forEach((k) => (base.current[k] = now[k] as string));
            if (body.lastEdit) setLastEdit(body.lastEdit);
            setSaving('saved');
            setSaveError(null);
        } catch {
            setSaving('error');
        }
    };
    const dataJson = JSON.stringify(strip(d));
    const firstUnsaved = useRef<number | null>(null);
    useEffect(() => {
        if (!editable) return;
        firstUnsaved.current ??= Date.now();
        const wait = Math.max(0, Math.min(500, 1500 - (Date.now() - firstUnsaved.current)));
        const timer = setTimeout(() => {
            firstUnsaved.current = null;
            void autosave();
        }, wait);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [dataJson, editable]);

    const mySection = useRef('');
    const statusRef = useRef(status);
    statusRef.current = status;
    useEffect(() => {
        if (!editable) return;
        const pull = async (): Promise<void> => {
            if (document.hidden) return;
            const res = await fetch(`${liveUrl}?section=${encodeURIComponent(mySection.current)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).catch(() => null);
            if (!res?.ok) return;
            const live = (await res.json()) as { status: string; data: Record<string, unknown>; lastEdit: LastEdit; editors: Editor[]; committee: CommitteeRow[]; missingCommittee: string[] };
            setEditors(live.editors);
            setCommitteeList(live.committee);
            setMissingList(live.missingCommittee);
            if (live.status !== statusRef.current) {
                router.reload();
                return;
            }
            const local = snapshotOf(strip(latest.current));
            const incoming: Record<string, unknown> = {};
            for (const [key, value] of Object.entries(live.data)) {
                const remote = JSON.stringify(value);
                // Take the others' change only where this person has nothing unsaved.
                if (key in local && local[key] === base.current[key] && remote !== base.current[key]) {
                    incoming[key] = value;
                    base.current[key] = remote;
                }
            }
            if (Object.keys(incoming).length) form.setData((cur) => ({ ...cur, ...incoming }));
            setLastEdit(live.lastEdit);
        };
        void pull();
        const timer = setInterval(() => void pull(), 1500);
        return () => clearInterval(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editable, liveUrl]);
    const editorsIn = (section: ProposalSection): Editor[] => editors.filter((e) => e.section === section);
    const editorsTitle = editors.map((e) => (e.section ? `${e.name} · ${PROPOSAL_SECTIONS[e.section as ProposalSection] ?? e.section}` : e.name)).join('\n');
    const jumpTo = (key: string): void => document.querySelector(`[data-section='${key}']`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    const flag = (section: ProposalSection): string | null => returnSections.find((r) => r.key === section)?.note ?? null;
    useLeaveReminder(`The proposal for ${event.name}`, editable);

    const [asking, setAsking] = useState(false);
    const ask = useForm({ user_ids: [] as number[], note: '' });
    const sendAsk = (): void =>
        ask.post(`/events/${event.slug}/proposal/ask`, {
            preserveScroll: true,
            onSuccess: () => {
                setAsking(false);
                ask.reset();
            },
        });
    const discard = (): void => {
        if (confirm('Discard this draft? It cannot be undone.')) router.delete(`/events/${event.slug}/proposal`);
    };
    const [signing, setSigning] = useState(false);

    // Every compulsory part, checked as people type: drives the gauge and the "take me to the problem" guide.
    const checks: { key: ProposalSection; label: string; done: boolean }[] = [
        {
            key: 'concept',
            label: 'Concept paper',
            done:
                filled(d.name) &&
                filled(d.summary) &&
                filled(d.venue) &&
                !!d.starts_at &&
                !!d.ends_at &&
                d.ends_at > d.starts_at &&
                Number(d.expected_participants) >= 1 &&
                d.nature.length > 0 &&
                (!d.nature.includes('others') || filled(d.nature_other)) &&
                d.source_of_fund.length > 0 &&
                (!isLate || filled(d.late_reason)),
        },
        { key: 'introduction', label: 'Introduction', done: filled(d.introduction) },
        { key: 'objectives', label: `Objectives (${writtenObjectives}/2)`, done: writtenObjectives >= 2 },
        { key: 'participants', label: 'Target participants', done: filled(d.eligibility) },
        ...(isWorkshop ? [{ key: 'speakers' as const, label: 'Speakers', done: d.speakers.length > 0 && d.speakers.every(speakerDone) }] : []),
        { key: 'activities', label: 'Activities', done: d.activities.length > 0 && d.activities.every((a) => filled(a.name) && filled(a.description)) },
        ...(d.ict_required ? [{ key: 'ict' as const, label: 'ICT needs', done: filled(d.ict_requirements) }] : []),
        ...(d.transport_required ? [{ key: 'logistics' as const, label: 'Transport', done: d.trips.length > 0 && d.trips.every(tripDone) }] : []),
        { key: 'committee', label: isWorkshop ? 'Persons in charge' : 'Committee', done: !missingList.length && !props.missingClubCommittee.length },
        { key: 'budget', label: 'Budget', done: [...d.budget.revenue, ...d.budget.expenses].every((i) => filled(i.description) && i.quantity >= 1) && (breakEven || filled(d.deficit_reason)) },
        { key: 'conclusion', label: 'Conclusion', done: filled(d.conclusion) },
        ...(d.extra_sections.length ? [{ key: 'extra' as const, label: 'Other parts', done: d.extra_sections.every(blockDone) }] : []),
    ];
    const blockers = [...props.missingClubCommittee.map((p) => `Club committee: ${p}`), ...missingList.map((p) => `Event committee: ${p}`), ...checks.filter((c) => !c.done && c.key !== 'committee').map((c) => c.label)];

    // The guide: the first problem flashes red three times; once it is fixed, the next one blinks until it is touched.
    const bad = new Set<ProposalSection>([...checks.filter((c) => !c.done).map((c) => c.key), ...Object.keys(form.errors).map(sectionOfField)]);
    const problems = ORDER.filter((k) => bad.has(k));
    const problemsRef = useRef(problems);
    problemsRef.current = problems;
    const [guiding, setGuiding] = useState(false);
    const [target, setTarget] = useState<ProposalSection | null>(null);
    const [alertOf, setAlertOf] = useState<{ key: ProposalSection; mode: 'flash' | 'blink' } | null>(null);
    const alertFor = (key: ProposalSection): 'flash' | 'blink' | null => (alertOf?.key === key ? alertOf.mode : null);
    const guideTo = (key: ProposalSection, mode: 'flash' | 'blink'): void => {
        setTarget(key);
        setAlertOf(null);
        setTimeout(() => {
            setAlertOf({ key, mode });
            jumpTo(key);
            if (mode === 'flash') setTimeout(() => setAlertOf((a) => (a?.key === key && a.mode === 'flash' ? null : a)), 2200);
        }, 0);
    };
    const startGuide = (): boolean => {
        const first = problemsRef.current[0];
        if (!first) return false;
        setGuiding(true);
        guideTo(first, 'flash');
        return true;
    };
    const problemsKey = problems.join(',');
    useEffect(() => {
        if (!guiding || !target || problems.includes(target)) return;
        // Fixed: after a short pause (so typing is not interrupted), move on to the next problem, top to bottom.
        const timer = setTimeout(() => {
            const next = problemsRef.current[0];
            if (!next) {
                setGuiding(false);
                setTarget(null);
                setAlertOf(null);
                return;
            }
            if (next !== target) guideTo(next, 'blink');
        }, 900);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [problemsKey, guiding, target]);
    const stopBlink = (e: SyntheticEvent): void => {
        const section = (e.target as HTMLElement).closest<HTMLElement>('[data-section]')?.dataset.section;
        if (alertOf?.mode === 'blink' && section === alertOf.key) setAlertOf(null);
    };
    const onFocus = (e: FocusEvent): void => {
        mySection.current = (e.target as HTMLElement).closest<HTMLElement>('[data-section]')?.dataset.section ?? mySection.current;
        stopBlink(e);
    };
    const trySubmit = (): void => {
        if (!startGuide()) setSigning(true);
    };
    // useForm keeps the last transform: set it before every request, so the signature goes with the submission only.
    const save = (e?: FormEvent): void => {
        e?.preventDefault();
        form.transform((data) => strip(data));
        form.put(`/events/${event.slug}/proposal`, { preserveScroll: true });
    };
    const submit = (): void => {
        form.transform((data) => data);
        form.post(`/events/${event.slug}/proposal/submit`, {
            preserveScroll: true,
            onSuccess: () => setSigning(false),
            onError: () => {
                setSigning(false);
                setTimeout(startGuide, 0);
            },
        });
    };

    // The four compulsory offices are fixed slots: pick the person (name or student ID) and add.
    const [slots, setSlots] = useState<Record<string, Slot>>(() => Object.fromEntries(options.compulsoryPositions.map((p) => [p, { student_number: '', responsibility: '', error: null, busy: false }])));
    const setSlot = (position: string, patch: Partial<Slot>): void => setSlots((s) => ({ ...s, [position]: { ...(s[position] as Slot), ...patch } }));
    const slotPickers = useRef<Record<string, PersonPickerHandle | null>>({});
    const fillSlot = (position: string): void => {
        const slot = slots[position];
        if (!slot?.student_number) return;
        setSlot(position, { busy: true });
        router.post(
            `/events/${event.slug}/committee`,
            { student_number: slot.student_number, position, responsibility: slot.responsibility },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSlot(position, { student_number: '', responsibility: '', error: null });
                    slotPickers.current[position]?.clear();
                },
                onError: (errs) => setSlot(position, { error: errs.student_number ?? errs.position ?? null }),
                onFinish: () => setSlot(position, { busy: false }),
            },
        );
    };
    const holderOf = (position: string): CommitteeRow | undefined => committeeList.find((c) => c.position === position);
    const otherMembers = committeeList.filter((c) => !options.compulsoryPositions.includes(c.position));
    const member = useForm({ student_number: '', position: '', responsibility: '' });
    const memberPicker = useRef<PersonPickerHandle>(null);
    const addMember = (): void =>
        member.post(`/events/${event.slug}/committee`, {
            preserveScroll: true,
            onSuccess: () => {
                member.reset();
                memberPicker.current?.clear();
            },
        });
    const removeMember = (id: number): void => router.delete(`/events/${event.slug}/committee/${id}`, { preserveScroll: true });

    // The first time the committee opens its approved proposal on this device, celebrate it.
    const [celebrate, setCelebrate] = useState<string | null>(null);
    useEffect(() => {
        if (status !== 'approved') return;
        const key = `celebrated:proposal:${event.slug}`;
        try {
            if (localStorage.getItem(key)) return;
            localStorage.setItem(key, '1');
        } catch {
            return;
        }
        setCelebrate('Proposal approved!');
    }, [status, event.slug]);
    const section = (key: ProposalSection) => ({ section: key, alert: alertFor(key), flag: flag(key), editors: editorsIn(key) });

    return (
        <AppLayout>
            <Head title={`Proposal · ${event.name}`} />
            <form className="grid gap-6 pb-8" onSubmit={save} onFocus={onFocus} onPointerDown={stopBlink}>
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold">Event Proposal</h1>
                        <p className="text-sm text-ink-muted">06 Event Proposal Template · {event.name}</p>
                    </div>
                    <Badge tone={status === 'returned' ? 'warning' : status === 'approved' ? 'success' : 'neutral'}>{status.replace('_', ' ')}</Badge>
                </header>
                {status === 'approved' && (
                    <section className="flex flex-wrap items-center justify-between gap-3 rounded-card border border-success/40 bg-success/5 px-4 py-3">
                        <p className="text-sm">
                            <strong className="text-success">Approved.</strong> Download the signed proposal to keep a copy.
                        </p>
                        <div className="flex gap-2">
                            <a href={`/documents/${documentId}/download/pdf`} className="rounded-control bg-ink px-4 py-2 text-sm font-semibold text-white hover:bg-ink/85">
                                Download PDF
                            </a>
                            <a href={`/documents/${documentId}/download/docx`} className="rounded-control border border-line bg-surface px-4 py-2 text-sm font-medium hover:bg-surface-muted">
                                Word
                            </a>
                        </div>
                    </section>
                )}
                {approval && (
                    <section className="rounded-card border border-line bg-surface p-5 shadow-card">
                        <ApprovalTracker progress={approval.progress} steps={approval.steps} />
                    </section>
                )}
                {status === 'returned' && (returnComment || returnSections.length > 0) && (
                    <div className="grid gap-2 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-sm">
                        <p className="font-semibold text-danger">Returned for changes. Fix the highlighted parts, then sign and submit again.</p>
                        {returnComment && !returnSections.length && <p>“{returnComment}”</p>}
                        {returnSections.length > 0 && (
                            <ul className="grid gap-1">
                                {returnSections.map((r) => (
                                    <li key={r.key}>
                                        <button type="button" className="font-medium text-danger underline" onClick={() => jumpTo(r.key)}>
                                            {PROPOSAL_SECTIONS[r.key as ProposalSection] ?? r.key}
                                        </button>
                                        : {r.note}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
                {editable && <ProgressGauge items={checks} title="Compulsory parts: tap one to go there" onJump={jumpTo} />}

                <FormSection title="Student Organization Concept Paper" {...section('concept')}>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <LockedField label="To" value={locked.to} />
                        <LockedField label="From" value={locked.from} />
                        <LockedField label="Date of Submission" value={locked.submitted_on ?? 'Set by the server on submit'} />
                    </div>
                    <Input value={d.name} onChange={(v) => set('name', v)} label="Event Name" required readOnly={readOnly} error={errors.name} />
                    <Textarea value={d.summary} onChange={(v) => set('summary', v)} label="Event Summary" required rows={3} readOnly={readOnly} error={errors.summary} />
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Input value={d.expected_participants} onChange={(v) => set('expected_participants', v as unknown as number)} type="number" label="Expected participants" required readOnly={readOnly} error={errors.expected_participants} />
                        <Input
                            value={d.starts_at}
                            onChange={(v) => set('starts_at', v)}
                            type="datetime-local"
                            label="Event starts"
                            required
                            readOnly={readOnly}
                            error={errors.starts_at}
                            hint={`Submit ${leadTime.days} days ahead: earliest ${date(leadTime.earliest)}`}
                        />
                        <Input value={d.ends_at} onChange={(v) => set('ends_at', v)} type="datetime-local" label="Event ends" required readOnly={readOnly} error={errors.ends_at} />
                    </div>
                    {isLate && (
                        <div className="fade-up">
                            <Textarea
                                value={d.late_reason ?? ''}
                                onChange={(v) => set('late_reason', v)}
                                label="Why it is submitted late"
                                required
                                rows={2}
                                readOnly={readOnly}
                                placeholder={`This is inside the ${leadTime.days}-day lead time. Explain why; SLD decides.`}
                                error={errors.late_reason}
                            />
                        </div>
                    )}
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            Nature of event<span className="text-danger"> *</span>
                        </legend>
                        <CheckboxGroup value={d.nature} onChange={(v) => set('nature', v as string[])} options={options.natures} columns={3} disabled={readOnly} />
                        {d.nature.includes('others') && <Input value={d.nature_other} onChange={(v) => set('nature_other', v)} label="Please specify" readOnly={readOnly} error={errors.nature_other} />}
                        {errors.nature && <p className="text-xs text-danger">{errors.nature}</p>}
                    </fieldset>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Input
                            value={d.cca_participant}
                            onChange={(v) => set('cca_participant', v as unknown as number)}
                            type="number"
                            label="CCA Hours · Participants"
                            required
                            readOnly={readOnly}
                            hint={`Default ${Math.min(duration, ccaLimit)} (1 hour = 1 CCA) · SLD limit ${ccaLimit}`}
                            error={errors.cca_participant}
                        />
                        <Input value={d.cca_committee} onChange={(v) => set('cca_committee', v as unknown as number)} type="number" label="CCA Hours · Event Committee" required readOnly={readOnly} hint={`Up to ${ccaLimit} (set by SLD)`} error={errors.cca_committee} />
                        <LockedField label="Project Duration" value={`${duration} hours`} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Input value={d.venue} onChange={(v) => set('venue', v)} label="Event Venue" required readOnly={readOnly} error={errors.venue} />
                        <Select
                            value={d.mode}
                            onChange={(v) => set('mode', v as ProposalData['mode'])}
                            label="Mode"
                            options={[
                                { value: 'physical', label: 'Physical' },
                                { value: 'virtual', label: 'Virtual' },
                            ]}
                            disabled={readOnly}
                        />
                    </div>
                    <div className="flex flex-wrap gap-6">
                        <Checkbox checked={d.is_outdoor} onChange={(v) => set('is_outdoor', v)} label="Outdoor / off-campus (indemnity required from everyone)" disabled={readOnly} />
                        <Checkbox checked={d.is_faculty_event} onChange={(v) => set('is_faculty_event', v)} label="Faculty event (adds the Dean to approvals)" disabled={readOnly} />
                    </div>
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">SDG Goals</legend>
                        <CheckboxGroup value={d.sdgs} onChange={(v) => set('sdgs', v.map(Number))} options={options.sdgs} columns={2} numbered disabled={readOnly} />
                    </fieldset>
                    <div className="grid gap-2">
                        <span className="text-sm font-medium">
                            Collaboration <span className="font-normal text-ink-muted">(optional)</span>
                        </span>
                        {d.collaboration.length > 0 && (
                            <ul className="flex flex-wrap gap-1.5">
                                {d.collaboration.map((c, i) => (
                                    <li key={c} className="flex items-center gap-1 rounded-full border border-line bg-surface-muted py-1 pl-3 pr-1.5 text-sm">
                                        {c}
                                        {!readOnly && (
                                            <button type="button" className="grid size-5 place-items-center rounded-full text-ink-muted hover:bg-danger/10 hover:text-danger" aria-label={`Remove ${c}`} onClick={() => set('collaboration', d.collaboration.filter((_, j) => j !== i))}>
                                                ✕
                                            </button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {!readOnly ? (
                            <div className="flex gap-2">
                                <input
                                    value={partner}
                                    list="partner-clubs"
                                    placeholder="Pick a club, or type another partner"
                                    aria-label="Add a partner"
                                    className="h-10 min-w-0 flex-1 rounded-control border border-line bg-surface px-3 text-sm"
                                    onChange={(e) => {
                                        setPartner(e.target.value);
                                        if (clubNames.includes(e.target.value)) addPartner(e.target.value);
                                    }}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            addPartner();
                                        }
                                    }}
                                />
                                <Button size="sm" variant="secondary" disabled={!partner.trim()} onClick={() => addPartner()}>
                                    Add
                                </Button>
                                <datalist id="partner-clubs">
                                    {clubNames.map((name) => (
                                        <option key={name} value={name} />
                                    ))}
                                </datalist>
                            </div>
                        ) : (
                            !d.collaboration.length && <p className="text-sm text-ink-muted">None</p>
                        )}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <LockedField label="Total Budget" value={rm(expensesSen)} />
                        <fieldset className="grid gap-2">
                            <legend className="mb-1 text-sm font-medium">
                                Source of fund<span className="text-danger"> *</span>
                            </legend>
                            <CheckboxGroup value={d.source_of_fund} onChange={(v) => set('source_of_fund', v as string[])} options={options.fundSources} disabled={readOnly} />
                            {errors.source_of_fund && <p className="text-xs text-danger">{errors.source_of_fund}</p>}
                        </fieldset>
                    </div>
                </FormSection>

                <FormSection title="Introduction" {...section('introduction')} required hint="Brief introduction of the event, including the relevant SDGs.">
                    <Textarea value={d.introduction} onChange={(v) => set('introduction', v)} rows={5} required readOnly={readOnly} error={errors.introduction} />
                </FormSection>

                <FormSection title="Objectives" {...section('objectives')} required>
                    <DynamicTable
                        rows={d.objectives.map((text) => ({ text }))}
                        onChange={(rows) => set('objectives', rows.map((r) => r.text))}
                        columns={[{ key: 'text', label: 'Objective' }]}
                        blank={{ text: '' }}
                        disabled={readOnly}
                        errors={form.errors as Record<string, string>}
                        errorPrefix="objectives"
                        addLabel="Add another objective"
                        min={2}
                    />
                    <p className={`text-xs ${writtenObjectives < 2 ? 'text-warning' : 'text-success'}`}>At least 2 objectives; add more if you need them ({writtenObjectives} written).</p>
                    {errors.objectives && <p className="text-xs text-danger">{errors.objectives}</p>}
                </FormSection>

                <FormSection title="Target Participants" {...section('participants')} required>
                    <Input value={d.eligibility} onChange={(v) => set('eligibility', v)} label="Eligibility" required readOnly={readOnly} error={errors.eligibility} />
                </FormSection>

                {isWorkshop && (
                    <FormSection title="Speaker / External Profile" {...section('speakers')} required hint="Workshop: every speaker or external person, with a photo, phone and designation. LinkedIn is optional.">
                        <ol className="relative grid gap-3">
                            {d.speakers.map((p, i) => (
                                <li key={i} className={`fade-up grid gap-3 rounded-control border p-4 sm:grid-cols-[auto_1fr] ${speakerDone(p) ? 'border-line' : 'border-warning/60'}`}>
                                    <PhotoField value={p.photo} onChange={(v) => setSpeaker(i, { photo: v })} uploadUrl={imageUrl} imageBase={imageBase} label="Photo" required readOnly={readOnly} error={errors[`speakers.${i}.photo`]} />
                                    <div className="grid content-start gap-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <h3 className="text-sm font-semibold">Person {i + 1}</h3>
                                            {!readOnly && d.speakers.length > 1 && (
                                                <button type="button" className="text-sm text-ink-muted hover:text-danger" onClick={() => set('speakers', d.speakers.filter((_, j) => j !== i))}>
                                                    Remove
                                                </button>
                                            )}
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <Input value={p.name} onChange={(v) => setSpeaker(i, { name: v })} label="Name" required readOnly={readOnly} error={errors[`speakers.${i}.name`]} />
                                            <Input value={p.designation} onChange={(v) => setSpeaker(i, { designation: v })} label="Designation" required readOnly={readOnly} error={errors[`speakers.${i}.designation`]} />
                                            <Input value={p.phone} onChange={(v) => setSpeaker(i, { phone: v })} type="tel" label="Phone number" required readOnly={readOnly} error={errors[`speakers.${i}.phone`]} />
                                            <Input value={p.linkedin} onChange={(v) => setSpeaker(i, { linkedin: v })} label="LinkedIn profile (optional)" readOnly={readOnly} error={errors[`speakers.${i}.linkedin`]} />
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ol>
                        {!readOnly && (
                            <button type="button" className="justify-self-start text-sm font-medium text-brand-blue" onClick={() => set('speakers', [...d.speakers, blankSpeaker()])}>
                                ＋ Add another person
                            </button>
                        )}
                        {errors.speakers && <p className="text-xs text-danger">{errors.speakers}</p>}
                    </FormSection>
                )}

                <FormSection title="Proposed Activities" {...section('activities')} required>
                    <DynamicTable
                        rows={d.activities}
                        onChange={(rows) => set('activities', rows)}
                        columns={[
                            { key: 'name', label: 'Name of the Activity' },
                            { key: 'description', label: 'Description of Activity', type: 'textarea' },
                        ]}
                        blank={{ name: '', description: '' }}
                        disabled={readOnly}
                        errors={form.errors as Record<string, string>}
                        errorPrefix="activities"
                        addLabel="Add activity"
                    />
                </FormSection>

                <FormSection title="Tentative / Event Itinerary" hint="Optional. List what you know so far; times can be left blank." {...section('itinerary')}>
                    <DynamicTable
                        rows={d.itinerary}
                        onChange={(rows) => set('itinerary', rows)}
                        columns={[
                            { key: 'start', label: 'Time Start', type: 'time' },
                            { key: 'end', label: 'Time End', type: 'time' },
                            { key: 'agenda', label: 'Agenda' },
                        ]}
                        blank={{ start: '', end: '', agenda: '' }}
                        disabled={readOnly}
                        errors={form.errors as Record<string, string>}
                        errorPrefix="itinerary"
                    />
                </FormSection>

                <FormSection title="ICT & Maintenance Requirement" {...section('ict')}>
                    <Checkbox checked={!!d.ict_required} onChange={(v) => set('ict_required', v)} label="ICT or maintenance support required (projector, sound, Wi-Fi, room setup…)" disabled={readOnly} />
                    {d.ict_required ? (
                        <div className="fade-up">
                            <Textarea value={d.ict_requirements} onChange={(v) => set('ict_requirements', v)} label="What is needed" required rows={3} readOnly={readOnly} error={errors.ict_requirements} />
                        </div>
                    ) : (
                        <p className="text-sm text-ink-muted">
                            Not ticked: the form will say <strong>Not required</strong>.
                        </p>
                    )}
                </FormSection>

                <FormSection title="Logistic Arrangements" {...section('logistics')}>
                    <Checkbox
                        checked={d.transport_required}
                        onChange={(v) => form.setData((cur) => ({ ...cur, transport_required: v, trips: v && !cur.trips.length ? [blankTrip()] : cur.trips }))}
                        label="Transportation required"
                        disabled={readOnly}
                    />
                    {!d.transport_required ? (
                        <p className="text-sm text-ink-muted">
                            Not ticked: the form will say <strong>Not required</strong>.
                        </p>
                    ) : (
                        <>
                            <ol className="relative grid gap-3">
                                {d.trips.map((trip, i) => (
                                    <li key={i} className="fade-up grid gap-3 rounded-control border border-line p-4">
                                        <div className="flex items-center justify-between gap-2">
                                            <h3 className="text-sm font-semibold">Trip {i + 1}</h3>
                                            {!readOnly && d.trips.length > 1 && (
                                                <button type="button" className="text-sm text-ink-muted hover:text-danger" onClick={() => set('trips', d.trips.filter((_, j) => j !== i))}>
                                                    Remove
                                                </button>
                                            )}
                                        </div>
                                        <Input
                                            value={trip.arrangement}
                                            onChange={(v) => setTrip(i, { arrangement: v })}
                                            label="Arrangement"
                                            required
                                            placeholder="By participants' own transport / university transport"
                                            readOnly={readOnly}
                                            error={errors[`trips.${i}.arrangement`]}
                                        />
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <Input
                                                value={trip.departure_at}
                                                onChange={(v) => setTrip(i, { departure_at: v })}
                                                type="datetime-local"
                                                label="Departure date & time"
                                                required
                                                min={d.starts_at}
                                                max={d.ends_at}
                                                readOnly={readOnly}
                                                error={tripError(i, 'departure_at', trip.departure_at, null)}
                                            />
                                            <Input value={trip.departure_pickup} onChange={(v) => setTrip(i, { departure_pickup: v })} label="Pickup point" required readOnly={readOnly} error={errors[`trips.${i}.departure_pickup`]} />
                                        </div>
                                        <div className="grid gap-2 border-l-2 border-brand-cyan/50 pl-3">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">Destinations in order · the last one is the drop-off point</p>
                                            {trip.stops.map((st, s) => (
                                                <div key={s} className="grid gap-2 rounded-control bg-surface-muted p-3">
                                                    <div className="flex items-center justify-between gap-2 text-sm font-medium">
                                                        <span>{s === trip.stops.length - 1 ? 'Drop-off point (last destination)' : `Stop ${s + 1}`}</span>
                                                        {!readOnly && trip.stops.length > 1 && (
                                                            <button type="button" className="text-xs text-ink-muted hover:text-danger" onClick={() => setTrip(i, { stops: trip.stops.filter((_, k) => k !== s) })}>
                                                                Remove
                                                            </button>
                                                        )}
                                                    </div>
                                                    <div className="grid gap-2 sm:grid-cols-3">
                                                        <Input value={st.name} onChange={(v) => setStop(i, s, { name: v })} label="Location name" required readOnly={readOnly} error={errors[`trips.${i}.stops.${s}.name`]} />
                                                        <Input
                                                            value={st.at}
                                                            onChange={(v) => setStop(i, s, { at: v })}
                                                            type="datetime-local"
                                                            label="Date & time"
                                                            required
                                                            min={d.starts_at}
                                                            max={d.ends_at}
                                                            readOnly={readOnly}
                                                            error={tripError(i, `stops.${s}.at`, st.at, [trip.departure_at, ...trip.stops.slice(0, s).map((x) => x.at)].filter(Boolean).pop() ?? null)}
                                                        />
                                                        <Input value={st.point} onChange={(v) => setStop(i, s, { point: v })} label="Point" placeholder="Main gate, lobby…" required readOnly={readOnly} error={errors[`trips.${i}.stops.${s}.point`]} />
                                                    </div>
                                                </div>
                                            ))}
                                            {errors[`trips.${i}.stops`] && <p className="text-xs text-danger">{errors[`trips.${i}.stops`]}</p>}
                                            {!readOnly && (
                                                <button type="button" className="justify-self-start text-xs font-medium text-brand-blue" onClick={() => setTrip(i, { stops: [...trip.stops, blankStop()] })}>
                                                    ＋ Add a place on the way
                                                </button>
                                            )}
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <Input
                                                value={trip.return_at}
                                                onChange={(v) => setTrip(i, { return_at: v })}
                                                type="datetime-local"
                                                label="Estimated return date & time"
                                                required
                                                min={d.starts_at}
                                                max={d.ends_at}
                                                readOnly={readOnly}
                                                error={tripError(i, 'return_at', trip.return_at, tripTimes(trip).slice(0, -1).filter(Boolean).pop() ?? null)}
                                            />
                                            <Input value={trip.return_pickup} onChange={(v) => setTrip(i, { return_pickup: v })} label="Return pickup point" required readOnly={readOnly} error={errors[`trips.${i}.return_pickup`]} />
                                        </div>
                                        <p className="text-xs text-ink-muted">
                                            All times must be within the event: {d.starts_at.replace('T', ' ')} – {d.ends_at.replace('T', ' ')}.
                                        </p>
                                    </li>
                                ))}
                            </ol>
                            {!readOnly && (
                                <button type="button" className="justify-self-start text-sm font-medium text-brand-blue" onClick={() => set('trips', [...d.trips, blankTrip()])}>
                                    ＋ Add another trip (different place or time)
                                </button>
                            )}
                        </>
                    )}
                </FormSection>

                <FormSection
                    title="Event Committee & Responsibilities"
                    {...section('committee')}
                    required
                    hint={
                        isWorkshop
                            ? 'Workshop: the four offices are optional, but at least 2 persons in charge are needed. Add them below (e.g. role: Person in Charge).'
                            : 'President, Vice President, Secretary and Treasurer are compulsory. Separate from the club committee.'
                    }
                >
                    {missingList.length > 0 && <p className="rounded-control bg-warning/10 px-3 py-2 text-sm text-warning">Still needed: {missingList.join(', ')}</p>}
                    <ol className="grid gap-2">
                        {options.compulsoryPositions.map((pos, i) => {
                            const holder = holderOf(pos);
                            const slot = slots[pos];
                            return (
                                <li key={pos} className={`grid gap-3 rounded-control border p-3 sm:grid-cols-[9rem_1fr] sm:items-start ${holder ? 'border-success/40 bg-success/5' : 'border-line'}`}>
                                    <p className="flex items-center gap-2 pt-2 text-sm font-semibold">
                                        <span className={`grid size-6 place-items-center rounded-full text-xs text-white ${holder ? 'bg-success' : 'bg-ink'}`}>{i + 1}</span>
                                        {pos}
                                        {!isWorkshop && <span className="text-danger">*</span>}
                                    </p>
                                    {holder ? (
                                        <div className="flex flex-wrap items-center justify-between gap-2 pt-1 text-sm">
                                            <span>
                                                <strong>{holder.name}</strong> <span className="text-ink-muted">{holder.student_number}</span>
                                                {holder.responsibility && ` — ${holder.responsibility}`}
                                            </span>
                                            {!readOnly && (
                                                <button type="button" className="text-ink-muted hover:text-danger" onClick={() => removeMember(holder.id)}>
                                                    Change
                                                </button>
                                            )}
                                        </div>
                                    ) : !readOnly && slot ? (
                                        <div className="grid gap-2 sm:grid-cols-[1.4fr_1fr_auto] sm:items-end">
                                            <PersonPicker
                                                ref={(el) => {
                                                    slotPickers.current[pos] = el;
                                                }}
                                                value={slot.student_number}
                                                onChange={(v) => setSlot(pos, { student_number: v })}
                                                label="Student ID or name"
                                                placeholder="Type a name (e.g. tan) or student ID"
                                                error={slot.error ?? undefined}
                                            />
                                            <Input value={slot.responsibility} onChange={(v) => setSlot(pos, { responsibility: v })} label="Responsibility" />
                                            <Button variant="secondary" disabled={!slot.student_number} loading={slot.busy} onClick={() => fillSlot(pos)}>
                                                Add
                                            </Button>
                                        </div>
                                    ) : (
                                        <p className={`pt-2 text-sm ${isWorkshop ? 'text-ink-muted' : 'text-warning'}`}>{isWorkshop ? 'Optional' : 'Not filled'}</p>
                                    )}
                                </li>
                            );
                        })}
                    </ol>
                    <div className="grid gap-2">
                        <h3 className="text-sm font-semibold">Other roles</h3>
                        {otherMembers.length > 0 && (
                            <ul className="grid gap-2">
                                {otherMembers.map((m) => (
                                    <li key={m.id} className="flex flex-wrap items-center justify-between gap-2 rounded-control border border-line px-3 py-2 text-sm">
                                        <span>
                                            <strong>{m.position}</strong> · {m.name} <span className="text-ink-muted">{m.student_number}</span>
                                            {m.responsibility && ` — ${m.responsibility}`}
                                        </span>
                                        {!readOnly && (
                                            <button type="button" className="text-ink-muted hover:text-danger" onClick={() => removeMember(m.id)}>
                                                Remove
                                            </button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {!readOnly && (
                            <div className="grid gap-3 rounded-control bg-surface-muted p-4 sm:grid-cols-[1.4fr_1fr_1.2fr_auto] sm:items-end">
                                <PersonPicker ref={memberPicker} value={member.data.student_number} onChange={(v) => member.setData('student_number', v)} label="Student ID or name" placeholder="Type a name or student ID" error={member.errors.student_number} />
                                <PositionPicker value={member.data.position} onChange={(v) => member.setData('position', v)} required={[]} taken={committeeList.map((c) => c.position)} error={member.errors.position} />
                                <Input value={member.data.responsibility} onChange={(v) => member.setData('responsibility', v)} label="Responsibility" error={member.errors.responsibility} />
                                <Button variant="secondary" loading={member.processing} onClick={addMember}>
                                    Add
                                </Button>
                            </div>
                        )}
                    </div>
                </FormSection>

                <FormSection title="Estimated Budget" {...section('budget')} required>
                    <BudgetTable budget={d.budget} onChange={(b) => set('budget', b)} disabled={readOnly} errors={form.errors as Record<string, string>} />
                    {!breakEven && (
                        <div className="fade-up">
                            <Textarea
                                value={d.deficit_reason ?? ''}
                                onChange={(v) => set('deficit_reason', v)}
                                label="Why the event runs at a deficit"
                                required
                                rows={2}
                                readOnly={readOnly}
                                placeholder="e.g. A community service event funded from the club account; SLD decides."
                                error={errors.deficit_reason}
                            />
                        </div>
                    )}
                </FormSection>

                <FormSection title="Conclusion" {...section('conclusion')} required>
                    <Textarea value={d.conclusion} onChange={(v) => set('conclusion', v)} rows={4} required readOnly={readOnly} error={errors.conclusion} />
                </FormSection>

                {(d.extra_sections.length > 0 || !readOnly) && (
                    <FormSection
                        title="Other parts"
                        {...section('extra')}
                        hint="Optional. Add headings, paragraphs, bullet lists, pictures or tables; they print after the conclusion. Anything added must be filled in or deleted before submitting."
                    >
                        <ol className="relative grid gap-3">
                            {d.extra_sections.map((block, b) => (
                                <li key={b} className={`fade-up grid gap-2 rounded-control border p-3 ${blockDone(block) ? 'border-line' : 'border-warning/60'}`}>
                                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                                        <Badge>{BLOCK_LABELS[block.kind]}</Badge>
                                        {!readOnly && (
                                            <div className="flex gap-3 text-ink-muted">
                                                <button type="button" className="hover:text-ink disabled:opacity-30" disabled={b === 0} aria-label="Move up" onClick={() => moveBlock(b, -1)}>
                                                    ↑
                                                </button>
                                                <button type="button" className="hover:text-ink disabled:opacity-30" disabled={b === d.extra_sections.length - 1} aria-label="Move down" onClick={() => moveBlock(b, 1)}>
                                                    ↓
                                                </button>
                                                <button type="button" className="hover:text-danger" onClick={() => set('extra_sections', d.extra_sections.filter((_, j) => j !== b))}>
                                                    Delete
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                    {block.kind === 'heading' || block.kind === 'subheading' ? (
                                        <Input value={block.text} onChange={(v) => setBlock(b, { text: v })} label={BLOCK_LABELS[block.kind]} readOnly={readOnly} error={errors[`extra_sections.${b}.text`]} />
                                    ) : block.kind === 'paragraph' || block.kind === 'bullets' ? (
                                        <Textarea
                                            value={block.text}
                                            onChange={(v) => setBlock(b, { text: v })}
                                            label={block.kind === 'bullets' ? 'Bullet points (one per line)' : 'Paragraph'}
                                            rows={block.kind === 'bullets' ? 4 : 3}
                                            readOnly={readOnly}
                                            error={errors[`extra_sections.${b}.text`]}
                                        />
                                    ) : block.kind === 'image' ? (
                                        <div className="grid gap-2">
                                            <PhotoField value={block.image} onChange={(v) => setBlock(b, { image: v })} uploadUrl={imageUrl} imageBase={imageBase} label="Picture" shape="wide" required readOnly={readOnly} error={errors[`extra_sections.${b}.image`]} />
                                            <Input value={block.text} onChange={(v) => setBlock(b, { text: v })} label="Caption (optional)" readOnly={readOnly} />
                                            <p className="text-xs text-ink-muted">Printed centred, sized to read clearly on A4 (up to 12 × 9 cm).</p>
                                        </div>
                                    ) : (
                                        <div className="grid gap-2">
                                            <div className="overflow-x-auto rounded-control border border-line">
                                                <table className="w-full text-sm">
                                                    <tbody>
                                                        {block.rows.map((row, r) => (
                                                            <tr key={r} className={`border-t border-line first:border-t-0 ${r === 0 ? 'bg-surface-muted font-medium' : ''}`}>
                                                                {row.map((cell, c) => (
                                                                    <td key={c} className="min-w-28 border-l border-line p-1 first:border-l-0">
                                                                        <input
                                                                            value={cell}
                                                                            readOnly={readOnly}
                                                                            placeholder={r === 0 ? `Header ${c + 1}` : ''}
                                                                            aria-label={`Row ${r + 1}, column ${c + 1}`}
                                                                            className={`h-9 w-full rounded-control border px-2 ${filled(cell) ? 'border-line' : 'border-warning/60'}`}
                                                                            onChange={(e) => setCell(b, r, c, e.target.value)}
                                                                        />
                                                                    </td>
                                                                ))}
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                            {!readOnly && (
                                                <div className="flex flex-wrap gap-3 text-xs font-medium text-brand-blue">
                                                    <button type="button" onClick={() => resizeTable(b, 'row', 1)}>
                                                        ＋ Row
                                                    </button>
                                                    <button type="button" onClick={() => resizeTable(b, 'row', -1)}>
                                                        − Row
                                                    </button>
                                                    <button type="button" onClick={() => resizeTable(b, 'column', 1)}>
                                                        ＋ Column
                                                    </button>
                                                    <button type="button" onClick={() => resizeTable(b, 'column', -1)}>
                                                        − Column
                                                    </button>
                                                </div>
                                            )}
                                            {errors[`extra_sections.${b}.rows`] && <p className="text-xs text-danger">{errors[`extra_sections.${b}.rows`]}</p>}
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ol>
                        {!readOnly && (
                            <div className="grid gap-3 rounded-control bg-surface-muted p-3">
                                <p className="text-sm font-medium">＋ Add others</p>
                                <div className="flex flex-wrap gap-2">
                                    {(['heading', 'subheading', 'paragraph', 'bullets', 'image'] as const).map((kind) => (
                                        <Button key={kind} size="sm" variant="secondary" onClick={() => addBlock(kind)}>
                                            {BLOCK_LABELS[kind]}
                                        </Button>
                                    ))}
                                </div>
                                <div className="flex flex-wrap items-end gap-2">
                                    <div className="w-24">
                                        <Input value={tableSize.rows} onChange={(v) => setTableSize({ ...tableSize, rows: v })} type="number" label="Rows" min={1} max={30} />
                                    </div>
                                    <div className="w-24">
                                        <Input value={tableSize.columns} onChange={(v) => setTableSize({ ...tableSize, columns: v })} type="number" label="Columns" min={1} max={8} />
                                    </div>
                                    <Button size="sm" variant="secondary" onClick={() => addBlock('table')}>
                                        Add table
                                    </Button>
                                </div>
                            </div>
                        )}
                    </FormSection>
                )}

                <FormSection title="Approval & Verification">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <LockedField label="Prepared by" value={locked.prepared_by} />
                        <LockedField label="Date" value="Server date when you sign" />
                    </div>
                    <p className="text-sm text-ink-muted">Then: Club Advisor → SLD Officer → Assistant Registrar → Registrar → Bursar → COO{d.is_faculty_event && ' (Dean after the advisor)'}.</p>
                </FormSection>

                {/* At the end of the form (not docked), so it never covers the form on a phone or a laptop. */}
                {editable && (
                    <div className="rounded-card border border-line bg-surface p-4 shadow-card">
                        <div className="flex flex-wrap items-center justify-end gap-3">
                            {editors.length > 0 && (
                                <div className="flex items-center gap-2 text-xs" title={editorsTitle}>
                                    <span className="flex -space-x-2">
                                        {editors.slice(0, 4).map((e) => (
                                            <Avatar key={e.id} src={e.photo_url} name={e.name} size="xs" className="ring-2 ring-surface" />
                                        ))}
                                    </span>
                                    <span className="hidden text-ink-muted sm:inline">{editors.length === 1 ? `${editors[0]!.name.split(' ')[0]} is editing with you` : `${editors.length} others editing`}</span>
                                </div>
                            )}
                            <div className="mr-auto grid text-xs">
                                <span className="text-ink-muted">
                                    {saving === 'saving'
                                        ? 'Saving…'
                                        : saving === 'error'
                                          ? `Not saved yet: ${saveError ?? 'finish the field you are typing in'}`
                                          : lastEdit.at
                                            ? `Saved · last edited by ${lastEdit.by} ${dateTime(lastEdit.at)}`
                                            : 'Saves automatically'}
                                </span>
                                {blockers.length > 0 && <span className="text-warning">Still to do: {blockers.join(' · ')}. Press Sign &amp; submit to be taken there.</span>}
                            </div>
                            {canDiscard && (
                                <Button variant="ghost" onClick={discard}>
                                    Discard
                                </Button>
                            )}
                            {clubCommittee.length > 0 && (
                                <Button variant="secondary" onClick={() => setAsking(true)}>
                                    Ask someone to submit
                                </Button>
                            )}
                            <Button onClick={trySubmit}>Sign &amp; submit</Button>
                        </div>
                    </div>
                )}
            </form>
            <Modal open={asking} onClose={() => setAsking(false)} title="Ask someone to submit">
                <div className="grid gap-4">
                    <p className="text-sm text-ink-muted">They get a notification with a link to this proposal. Whoever signs and submits is printed as "Prepared by" with their position.</p>
                    <div className="grid gap-2">
                        {clubCommittee.map((c) => (
                            <Checkbox key={c.id} checked={ask.data.user_ids.includes(c.id)} label={c.name} onChange={(on) => ask.setData('user_ids', on ? [...ask.data.user_ids, c.id] : ask.data.user_ids.filter((x) => x !== c.id))} />
                        ))}
                    </div>
                    <Textarea value={ask.data.note} onChange={(v) => ask.setData('note', v)} label="Note (optional)" rows={2} placeholder="Budget is done, please check the itinerary and submit." />
                    {ask.errors.user_ids && <p className="text-xs text-danger">{ask.errors.user_ids}</p>}
                    <Button disabled={!ask.data.user_ids.length} loading={ask.processing} onClick={sendAsk}>
                        Notify
                    </Button>
                </div>
            </Modal>
            <Modal open={signing} onClose={() => setSigning(false)} title="Sign as preparer">
                <div className="grid gap-4">
                    <p className="text-sm text-ink-muted">
                        Signed as <strong>{locked.prepared_by}</strong>. The date and time come from the server.
                    </p>
                    <SignaturePad value={d.signature} onChange={(png, strokes) => form.setData((cur) => ({ ...cur, signature: png, signature_strokes: strokes }))} label="Your signature" signer={locked.prepared_by} />
                    {Object.keys(form.errors).length > 0 && <p className="text-sm text-danger">Some fields need attention. Close this and check the highlighted fields.</p>}
                    <Button disabled={!d.signature} loading={form.processing} onClick={submit}>
                        Submit proposal
                    </Button>
                </div>
            </Modal>
            <Celebration text={celebrate} detail="Every signature is in. Time to announce it on the feed." />
        </AppLayout>
    );
}
