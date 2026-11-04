import CustomQrPanel, { type CustomQr } from '@/Components/attendance/CustomQrPanel';
import ApprovalTracker from '@/Components/forms/ApprovalTracker';
import AutosaveNote from '@/Components/forms/AutosaveNote';
import DynamicTable from '@/Components/forms/DynamicTable';
import FormSection from '@/Components/forms/FormSection';
import EvidencePhotos, { type Evidence } from '@/Components/reports/EvidencePhotos';
import Avatar from '@/Components/ui/Avatar';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Icon from '@/Components/ui/Icon';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import SignaturePad from '@/Components/ui/SignaturePad';
import Tabs from '@/Components/ui/Tabs';
import Textarea from '@/Components/ui/Textarea';
import { useAutosave } from '@/hooks/useAutosave';
import AppLayout from '@/Layouts/AppLayout';
import { dateTime } from '@/lib/format';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Minutes {
    purpose: string;
    summary: string[];
    actions: { task: string; pic: string; deadline: string }[];
    next_date: string;
    next_time: string;
    next_venue: string;
    notes: string;
}
interface MeetingShowProps {
    evidence: Evidence | null;
    meeting: {
        id: number;
        title: string;
        kind: string;
        kind_label: string;
        audience: string | null;
        pax: number | null;
        starts_at: string;
        ends_at: string;
        venue: string | null;
        meet_link: string | null;
        club: { slug: string; name: string };
        hours: number;
    };
    people: { id: number; name: string; photo_url: string | null }[];
    present: number[];
    checkins: Record<number, { in: string | null; out: string | null; method: string | null }>;
    checklist: { user_id: number; name: string; id_number: string; role: string; present: boolean; in: string | null; out: string | null; method: string }[];
    canManage: boolean;
    customQr: CustomQr | null;
    tab: 'attendance' | 'minutes';
    qr: { in: string; out: string; seconds: number; seconds_left: number } | null;
    minutes: {
        id: number;
        status: string;
        editable: boolean;
        data: Minutes;
        approval: { progress: ApprovalProgress; steps: ApprovalStep[] } | null;
        downloads: string[];
        ai: string | null;
        ai_available: boolean;
    };
}
const clock = (at: string | null): string => (at ? new Date(at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '');
const plain = (d: Minutes & { signature: unknown; signature_strokes: unknown }): Minutes => {
    const { signature: _s, signature_strokes: _t, ...rest } = d;
    return rest;
};

/**
 * A meeting, in two tabs: Attendance (QR check-in/out for its audience, the checklist, exports) and Minutes (the 13
 * minutes, signed up the chain together with the attendance).
 */
export default function Show({ evidence, meeting, people, present, checkins, checklist, canManage, customQr, tab: initialTab, qr, minutes }: MeetingShowProps) {
    const [tab, setTab] = useState<'attendance' | 'minutes'>(initialTab);
    const switchTab = (key: string): void => {
        const next = key === 'minutes' ? 'minutes' : 'attendance';
        setTab(next);
        window.history.replaceState(window.history.state, '', `${window.location.pathname}?tab=${next}`);
    };
    const [search, setSearch] = useState('');
    const details = Object.fromEntries(checklist.map((r) => [r.user_id, r]));
    const [attendance, setAttendance] = useState<number[]>([...present]);
    useEffect(() => setAttendance([...present]), [present]);
    const toggle = (id: number): void => setAttendance(attendance.includes(id) ? attendance.filter((x) => x !== id) : [...attendance, id]);
    const saveAttendance = (): void => router.post(`/meetings/${meeting.id}/attendance`, { present: attendance }, { preserveScroll: true });

    const form = useForm<Minutes & { signature: string | null; signature_strokes: string | null }>({ ...minutes.data, signature: null, signature_strokes: null });
    const { data, setData, errors } = form;
    const url = `/meetings/${meeting.id}/minutes`;
    const drafting = minutes.ai === 'pending';
    // While the AI drafts, refresh until the draft lands, then load it into the form.
    usePoll(2500, { only: ['minutes'] }, { autoStart: drafting });
    useEffect(() => {
        if (!drafting) setData((d) => ({ ...d, ...minutes.data }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [minutes.ai]);
    const autosaved = useAutosave(url, plain(data), minutes.editable && !drafting, form.processing);
    // useForm keeps the last transform: set it before every request, so the signature goes with the submission only.
    const save = (): void => {
        form.transform(plain);
        form.put(url, { preserveScroll: true });
    };
    const draftWithAi = (): void => {
        form.transform(plain);
        form.post(`${url}/draft`, { preserveScroll: true });
    };
    const [signing, setSigning] = useState(false);
    const submit = (): void => {
        form.transform((d) => d);
        form.post(`${url}/submit`, { preserveScroll: true, onSuccess: () => setSigning(false) });
    };
    const readOnly = !minutes.editable;

    // Check-in / check-out QR on the organiser's screen: refreshes as each code expires.
    const [qrAction, setQrAction] = useState<'in' | 'out'>('in');
    const [left, setLeft] = useState(qr?.seconds_left ?? 0);
    const leftRef = useRef(left);
    useEffect(() => {
        leftRef.current = qr?.seconds_left ?? 0;
        setLeft(leftRef.current);
    }, [qr?.seconds_left, qr?.in]);
    useEffect(() => {
        if (!qr) return;
        const timer = setInterval(() => {
            leftRef.current = Math.max(0, leftRef.current - 1);
            setLeft(leftRef.current);
            if (leftRef.current === 0) router.reload({ only: ['qr', 'checkins', 'present', 'checklist'] });
        }, 1000);
        return () => clearInterval(timer);
    }, [qr]);
    const errorList = Object.values(errors);

    const shown = people.filter((p) => p.name.toLowerCase().includes(search.trim().toLowerCase()));
    const checkedOut = Object.values(checkins).filter((c) => c.out).length;
    const exportBase = `/meetings/${meeting.id}`;

    return (
        <AppLayout wide>
            <Head title={meeting.title} />
            <div className="grid gap-6">
                <header className="grid gap-3">
                    <Link href={`/clubs/${meeting.club.slug}/meetings`} className="flex items-center gap-1 text-sm font-medium text-primary">
                        ← {meeting.club.name} meetings
                    </Link>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="grid gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">{meeting.title}</h1>
                            <p className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink-muted">
                                <Badge tone="info">{meeting.kind_label}</Badge>
                                <span className="flex items-center gap-1 whitespace-nowrap">
                                    <Icon name="calendar" size={16} />
                                    {dateTime(meeting.starts_at)}
                                </span>
                                {meeting.venue && (
                                    <span className="flex items-center gap-1">
                                        <Icon name="pin" size={16} />
                                        {meeting.venue}
                                    </span>
                                )}
                                {meeting.audience && (
                                    <span className="flex items-center gap-1">
                                        <Icon name="users" size={16} />
                                        {meeting.audience}
                                        {meeting.pax ? ` · ${meeting.pax} expected` : ''}
                                    </span>
                                )}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {meeting.meet_link && (
                                <a href={meeting.meet_link} target="_blank" rel="noopener">
                                    <Button>
                                        <Icon name="camera" size={16} /> Join Google Meet
                                    </Button>
                                </a>
                            )}
                            <a href={`${exportBase}/package.zip`}>
                                <Button variant="secondary">
                                    <Icon name="download" size={16} /> Download all (ZIP)
                                </Button>
                            </a>
                        </div>
                    </div>
                </header>

                <Tabs
                    tabs={[
                        { key: 'attendance', label: `Attendance · ${attendance.length}/${people.length}` },
                        { key: 'minutes', label: `Minutes · ${minutes.status === 'draft' ? 'Draft' : minutes.status.replace('_', ' ')}` },
                    ]}
                    active={tab}
                    onChange={switchTab}
                >
                    {tab === 'attendance' ? (
                        <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                            <section className="grid content-start gap-4 rounded-card border border-line bg-surface p-5 shadow-card">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h2 className="font-semibold">Attendance checklist</h2>
                                        <p className="text-xs text-ink-muted">
                                            Tap a name to mark present or absent, then save.
                                            {meeting.kind === 'activity' && ` Each person present earns ${meeting.hours} CCA hours once the minutes are approved.`}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <a href={`${exportBase}/attendance/export.xlsx`}>
                                            <Button size="sm" variant="secondary">
                                                <Icon name="download" size={14} /> Excel
                                            </Button>
                                        </a>
                                        <a href={`${exportBase}/attendance/export.pdf`}>
                                            <Button size="sm" variant="secondary">
                                                <Icon name="download" size={14} /> PDF
                                            </Button>
                                        </a>
                                    </div>
                                </div>
                                <dl className="grid grid-cols-3 gap-2 text-center">
                                    {[
                                        { label: 'Invited', value: people.length },
                                        { label: 'Present', value: attendance.length },
                                        { label: 'Checked out', value: checkedOut },
                                    ].map((s) => (
                                        <div key={s.label} className="rounded-control bg-surface-muted px-2 py-3">
                                            <dt className="text-xs text-ink-muted">{s.label}</dt>
                                            <dd className="text-xl font-bold tabular-nums">{s.value}</dd>
                                        </div>
                                    ))}
                                </dl>
                                <Input value={search} onChange={setSearch} placeholder="Search by name" />
                                <ul className="grid max-h-[28rem] gap-1 overflow-y-auto">
                                    {shown.map((p) => {
                                        const here = attendance.includes(p.id);
                                        const c = checkins[p.id];
                                        const d = details[p.id];
                                        return (
                                            <li key={p.id}>
                                                <button
                                                    type="button"
                                                    disabled={!canManage}
                                                    aria-pressed={here}
                                                    className={`flex w-full items-center gap-3 rounded-control p-2 text-left text-sm transition ${here ? 'bg-success/10' : 'hover:bg-surface-muted'}`}
                                                    onClick={() => toggle(p.id)}
                                                >
                                                    <Avatar src={p.photo_url} name={p.name} size="xs" />
                                                    <span className="grid min-w-0 flex-1">
                                                        <span className="truncate font-medium">{p.name}</span>
                                                        <span className="truncate text-xs text-ink-muted">
                                                            {[d?.role, d?.id_number].filter(Boolean).join(' · ')}
                                                            {c?.in ? ` · in ${clock(c.in)}${c.out ? ` · out ${clock(c.out)}` : ''} (QR)` : c?.method === 'manual' ? ' · marked by committee' : ''}
                                                        </span>
                                                    </span>
                                                    <span className={`grid size-6 shrink-0 place-items-center rounded-full border ${here ? 'border-success bg-success text-white' : 'border-line'}`}>
                                                        {here && <Icon name="check" size={14} />}
                                                    </span>
                                                </button>
                                            </li>
                                        );
                                    })}
                                    {shown.length === 0 && <li className="p-4 text-center text-sm text-ink-muted">No one matches “{search}”.</li>}
                                </ul>
                                {canManage && (
                                    <Button className="justify-self-end" onClick={saveAttendance}>
                                        Save attendance
                                    </Button>
                                )}
                            </section>
                            <aside className="grid content-start gap-4">
                                {qr ? (
                                    <section className="grid gap-3 rounded-card border border-line bg-surface p-5 shadow-card">
                                        <h2 className="font-semibold">Attendance QR</h2>
                                        <p className="text-xs text-ink-muted">Only {meeting.audience?.toLowerCase() ?? 'the committee and advisors'} can check in. Each person scans with their own phone.</p>
                                        <div className="grid grid-cols-2 gap-1 rounded-control bg-surface-muted p-1 text-sm" role="radiogroup">
                                            {(['in', 'out'] as const).map((a) => (
                                                <button key={a} type="button" role="radio" aria-checked={qrAction === a} className={`rounded-control py-1.5 ${qrAction === a ? 'bg-surface font-semibold shadow-card' : ''}`} onClick={() => setQrAction(a)}>
                                                    Check {a}
                                                </button>
                                            ))}
                                        </div>
                                        {/* Server-generated SVG of the meeting's own signed check-in / check-out URL. */}
                                        <div className="mx-auto size-56 rounded-card bg-white p-3 [&>svg]:size-full" role="img" aria-label={`QR to check ${qrAction}`} dangerouslySetInnerHTML={{ __html: qr[qrAction] }} />
                                        <div className="h-1.5 overflow-hidden rounded-full bg-surface-muted">
                                            <div className="h-full rounded-full bg-primary transition-[width] duration-1000 ease-linear" style={{ width: `${(left / qr.seconds) * 100}%` }} />
                                        </div>
                                        <p className="text-center text-xs text-ink-muted">New code in {left} s</p>
                                    </section>
                                ) : (
                                    canManage && (
                                        <section className="rounded-card border border-line bg-surface p-5 text-sm text-ink-muted shadow-card">
                                            <h2 className="mb-1 font-semibold text-ink">Attendance QR</h2>
                                            The rotating check-in QR appears here from 30 minutes before the meeting until 2 hours after it ends.
                                        </section>
                                    )
                                )}
                                {customQr && <CustomQrPanel qr={customQr} uploadUrl={`/meetings/${meeting.id}/custom-qr`} title={meeting.title} />}
                            </aside>
                        </div>
                    ) : (
                        <div className="grid gap-6">
                            {minutes.approval && (
                                <section className="rounded-card border border-line bg-surface p-5 shadow-card">
                                    <ApprovalTracker progress={minutes.approval.progress} steps={minutes.approval.steps} />
                                </section>
                            )}
                            {minutes.downloads.length > 0 && (
                                <div className="flex flex-wrap gap-2">
                                    {minutes.downloads.map((f) => (
                                        <a key={f} href={`/documents/${minutes.id}/download/${f}`}>
                                            <Button variant="secondary" size="sm">
                                                <Icon name="download" size={14} /> Minutes ({f.toUpperCase()})
                                            </Button>
                                        </a>
                                    ))}
                                </div>
                            )}
                            {evidence && <EvidencePhotos evidence={evidence} canEdit title="Weekly activity photos" />}
                            <FormSection title="Secretary's notes" hint="Write down what happened. AI can turn your notes into draft minutes for you to check and edit.">
                                <Textarea
                                    value={data.notes}
                                    onChange={(v) => setData('notes', v)}
                                    rows={5}
                                    readOnly={readOnly}
                                    placeholder={'- Attendance taken, 9 present\n- Aisyah updated the team on the beach clean-up permit\n- Wei Zhang to design the poster by 10 Nov'}
                                />
                                {minutes.editable && minutes.ai_available && (
                                    <Button variant="secondary" className="justify-self-start" loading={drafting} disabled={!data.notes.trim()} onClick={draftWithAi}>
                                        <Icon name="sparkles" size={16} /> {drafting ? 'Drafting…' : 'Draft minutes with AI'}
                                    </Button>
                                )}
                                {minutes.ai === 'failed' && <p className="text-sm text-warning">AI could not draft from these notes. Please fill in the sections below.</p>}
                            </FormSection>
                            <FormSection title="1. Purpose of the meeting or activity">
                                <Input value={data.purpose} onChange={(v) => setData('purpose', v)} readOnly={readOnly} error={errors.purpose} />
                            </FormSection>
                            <FormSection title="2. Summary of activities and discussion">
                                <DynamicTable
                                    rows={data.summary.map((text) => ({ text }))}
                                    onChange={(rows) => setData('summary', rows.map((r) => r.text))}
                                    columns={[{ key: 'text', label: 'Point', type: 'textarea' }]}
                                    blank={{ text: '' }}
                                    disabled={readOnly}
                                    errors={errors as Record<string, string>}
                                    errorPrefix="summary"
                                    addLabel="Add point"
                                />
                            </FormSection>
                            <FormSection title="3. Decisions and action items" hint='Leave empty if there are none. The form will show "N/A".'>
                                <DynamicTable
                                    rows={data.actions}
                                    onChange={(rows) => setData('actions', rows)}
                                    columns={[
                                        { key: 'task', label: 'Task or action' },
                                        { key: 'pic', label: 'Person in charge' },
                                        { key: 'deadline', label: 'Deadline' },
                                    ]}
                                    blank={{ task: '', pic: '', deadline: '' }}
                                    disabled={readOnly}
                                    errors={errors as Record<string, string>}
                                    errorPrefix="actions"
                                    addLabel="Add action"
                                />
                            </FormSection>
                            <FormSection title="4. Next meeting or activity">
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <Input value={data.next_date} onChange={(v) => setData('next_date', v)} type="date" label="Date" readOnly={readOnly} />
                                    <Input value={data.next_time} onChange={(v) => setData('next_time', v)} type="time" label="Time" readOnly={readOnly} />
                                    <Input value={data.next_venue} onChange={(v) => setData('next_venue', v)} label="Venue" readOnly={readOnly} />
                                </div>
                            </FormSection>
                            <section className="rounded-card border border-line bg-surface-muted p-4 text-sm">
                                <p className="font-medium">Attendance goes with the minutes</p>
                                <p className="text-ink-muted">
                                    {attendance.length} of {people.length} present. The President, Club Advisor and SLD see this attendance checklist when they approve the minutes.
                                </p>
                            </section>
                            {minutes.editable && canManage && (
                                <div className="flex flex-wrap justify-end gap-2">
                                    <AutosaveNote state={autosaved} className="mr-auto self-center" />
                                    <Button variant="secondary" loading={form.processing} onClick={save}>
                                        Save draft
                                    </Button>
                                    <Button onClick={() => setSigning(true)}>Sign and submit</Button>
                                </div>
                            )}
                        </div>
                    )}
                </Tabs>
            </div>
            <Modal open={signing} onClose={() => setSigning(false)} title="Sign as preparer">
                <div className="grid gap-4">
                    <p className="text-sm text-ink-muted">After you sign, the minutes and attendance go to the President, then the Club Advisor, then SLD. The date and time are recorded automatically.</p>
                    <SignaturePad value={data.signature} onChange={(png, strokes) => setData((d) => ({ ...d, signature: png, signature_strokes: strokes }))} label="Signature" />
                    {errorList.length > 0 && <p className="text-sm text-danger">{errorList[0]}</p>}
                    <Button disabled={!data.signature} loading={form.processing} onClick={submit}>
                        Submit minutes
                    </Button>
                </div>
            </Modal>
        </AppLayout>
    );
}
