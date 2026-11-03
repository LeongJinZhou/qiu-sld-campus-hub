import ApprovalTracker from '@react/Components/forms/ApprovalTracker';
import AutosaveNote from '@react/Components/forms/AutosaveNote';
import DynamicTable from '@react/Components/forms/DynamicTable';
import FormSection from '@react/Components/forms/FormSection';
import EvidencePhotos, { type Evidence } from '@react/Components/reports/EvidencePhotos';
import Avatar from '@react/Components/ui/Avatar';
import Badge from '@react/Components/ui/Badge';
import Button from '@react/Components/ui/Button';
import Icon from '@react/Components/ui/Icon';
import Input from '@react/Components/ui/Input';
import Modal from '@react/Components/ui/Modal';
import SignaturePad from '@react/Components/ui/SignaturePad';
import Textarea from '@react/Components/ui/Textarea';
import { useAutosave } from '@react/hooks/useAutosave';
import AppLayout from '@react/Layouts/AppLayout';
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

/** A meeting: QR check-in/out for its audience, attendance, and the 13 minutes signed up the chain. */
export default function Show({ evidence, meeting, people, present, checkins, qr, minutes }: MeetingShowProps) {
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
            if (leftRef.current === 0) router.reload({ only: ['qr', 'checkins', 'present'] });
        }, 1000);
        return () => clearInterval(timer);
    }, [qr]);
    const errorList = Object.values(errors);

    return (
        <AppLayout wide>
            <Head title={meeting.title} />
            <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                <div className="grid content-start gap-6">
                    <header className="grid gap-2">
                        <Link href={`/clubs/${meeting.club.slug}/meetings`} className="text-sm text-brand-blue">
                            {meeting.club.name} · meetings
                        </Link>
                        <h1 className="text-2xl font-bold">{meeting.title}</h1>
                        <p className="flex flex-wrap items-center gap-3 text-sm text-ink-muted">
                            <Badge tone="info">{meeting.kind_label}</Badge>
                            <span className="flex items-center gap-1">
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
                                    {meeting.pax ? ` · ${meeting.pax} pax` : ''}
                                </span>
                            )}
                        </p>
                        {meeting.meet_link && (
                            <a href={meeting.meet_link} target="_blank" rel="noopener" className="justify-self-start">
                                <Button>
                                    <Icon name="camera" size={16} /> Join Google Meet
                                </Button>
                            </a>
                        )}
                    </header>
                    {evidence && <EvidencePhotos evidence={evidence} canEdit title="Weekly activity photos" />}
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
                                        Official 13 Minutes ({f.toUpperCase()})
                                    </Button>
                                </a>
                            ))}
                        </div>
                    )}
                    <FormSection title="Secretary's notes" hint="Jot down what happened; AI can turn the notes into minutes you then edit.">
                        <Textarea
                            value={data.notes}
                            onChange={(v) => setData('notes', v)}
                            rows={5}
                            readOnly={readOnly}
                            placeholder={'- attendance taken, 9 present\n- Aisyah updated on beach cleanup permits\n- Wei Zhang to design poster by 10 Nov'}
                        />
                        {minutes.editable && minutes.ai_available && (
                            <Button variant="secondary" className="justify-self-start" loading={drafting} disabled={!data.notes.trim()} onClick={draftWithAi}>
                                <Icon name="sparkles" size={16} /> {drafting ? 'Drafting…' : 'Draft minutes with AI'}
                            </Button>
                        )}
                        {minutes.ai === 'failed' && <p className="text-sm text-warning">AI couldn't draft from these notes — fill the sections below yourself.</p>}
                    </FormSection>
                    <FormSection title="1. Purpose of Meeting / Activity">
                        <Input value={data.purpose} onChange={(v) => setData('purpose', v)} readOnly={readOnly} error={errors.purpose} />
                    </FormSection>
                    <FormSection title="2. Summary of Activities / Discussion">
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
                    <FormSection title="3. Decisions / Action Items" hint='Leave empty if there are none — the form prints "N/A".'>
                        <DynamicTable
                            rows={data.actions}
                            onChange={(rows) => setData('actions', rows)}
                            columns={[
                                { key: 'task', label: 'Task/Action' },
                                { key: 'pic', label: 'Person in Charge' },
                                { key: 'deadline', label: 'Deadline' },
                            ]}
                            blank={{ task: '', pic: '', deadline: '' }}
                            disabled={readOnly}
                            errors={errors as Record<string, string>}
                            errorPrefix="actions"
                            addLabel="Add action"
                        />
                    </FormSection>
                    <FormSection title="4. Next Meeting / Activity">
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Input value={data.next_date} onChange={(v) => setData('next_date', v)} type="date" label="Date" readOnly={readOnly} />
                            <Input value={data.next_time} onChange={(v) => setData('next_time', v)} type="time" label="Time" readOnly={readOnly} />
                            <Input value={data.next_venue} onChange={(v) => setData('next_venue', v)} label="Venue" readOnly={readOnly} />
                        </div>
                    </FormSection>
                    {minutes.editable && (
                        <div className="flex flex-wrap justify-end gap-2">
                            <AutosaveNote state={autosaved} className="mr-auto self-center" />
                            <Button variant="secondary" loading={form.processing} onClick={save}>
                                Save draft
                            </Button>
                            <Button onClick={() => setSigning(true)}>Sign &amp; submit</Button>
                        </div>
                    )}
                </div>
                <aside className="grid content-start gap-4">
                    {qr && (
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
                                <div className="h-full rounded-full bg-gradient-to-r from-brand-cyan to-brand-magenta transition-[width] duration-1000 ease-linear" style={{ width: `${(left / qr.seconds) * 100}%` }} />
                            </div>
                            <p className="text-center text-xs text-ink-muted">New code in {left} s</p>
                        </section>
                    )}
                    <section className="rounded-card border border-line bg-surface p-5 shadow-card">
                        <h2 className="mb-1 font-semibold">Attendance</h2>
                        <p className="mb-3 text-xs text-ink-muted">
                            {attendance.length} present
                            {meeting.kind === 'activity' && ` · ${meeting.hours} CCA each once minutes are approved`}
                        </p>
                        <ul className="grid max-h-96 gap-1 overflow-y-auto">
                            {people.map((p) => {
                                const here = attendance.includes(p.id);
                                const c = checkins[p.id];
                                return (
                                    <li key={p.id}>
                                        <button type="button" className={`flex w-full items-center gap-3 rounded-control p-2 text-left text-sm transition ${here ? 'bg-success/10' : 'hover:bg-surface-muted'}`} onClick={() => toggle(p.id)}>
                                            <Avatar src={p.photo_url} name={p.name} size="xs" />
                                            <span className="grid flex-1">
                                                <span>{p.name}</span>
                                                {c?.in ? (
                                                    <span className="text-xs text-ink-muted">
                                                        in {clock(c.in)}
                                                        {c.out && ` · out ${clock(c.out)}`} · QR
                                                    </span>
                                                ) : (
                                                    c?.method === 'manual' && <span className="text-xs text-ink-muted">ticked by hand</span>
                                                )}
                                            </span>
                                            {here && <Icon name="check" size={16} className="text-success" />}
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                        <Button variant="secondary" size="sm" className="mt-3" block onClick={saveAttendance}>
                            Save attendance
                        </Button>
                    </section>
                </aside>
            </div>
            <Modal open={signing} onClose={() => setSigning(false)} title="Prepared by">
                <div className="grid gap-4">
                    <p className="text-sm text-ink-muted">Then: President → Club Advisor → SLD. Date and time come from the server.</p>
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
