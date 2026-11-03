import ApprovalTracker from '@/Components/forms/ApprovalTracker';
import AutosaveNote from '@/Components/forms/AutosaveNote';
import DynamicTable from '@/Components/forms/DynamicTable';
import FormSection from '@/Components/forms/FormSection';
import LockedField from '@/Components/forms/LockedField';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Icon from '@/Components/ui/Icon';
import Modal from '@/Components/ui/Modal';
import SignaturePad from '@/Components/ui/SignaturePad';
import Textarea from '@/Components/ui/Textarea';
import { useAutosave } from '@/hooks/useAutosave';
import AppLayout from '@/Layouts/AppLayout';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';

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
interface AgmReportProps {
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
}
const tone = (a?: string): 'success' | 'neutral' | 'info' => (a === 'present' ? 'success' : a === 'advisor' ? 'info' : 'neutral');

export default function Report({ agm, document, data: initial, record, minObjectives, ai, aiAvailable }: AgmReportProps) {
    const form = useForm<ReportData & { signature: string | null; signature_strokes: string | null }>({ ...initial, signature: null, signature_strokes: null });
    const { data, setData, errors } = form;
    const url = `/agms/${agm.id}/report`;
    const drafting = ai === 'pending';
    const draft: ReportData = { objectives: data.objectives, speech: data.speech, agenda: data.agenda, adjournment: data.adjournment };
    const autosaved = useAutosave(url, draft, document.editable && !drafting, form.processing);
    usePoll(2500, { only: ['data', 'ai'] }, { autoStart: drafting });
    // When the AI draft lands, load it into the form.
    useEffect(() => {
        if (!drafting) setData((d) => ({ ...d, ...initial }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ai]);

    const strip = (d: typeof data): ReportData => ({ objectives: d.objectives, speech: d.speech, agenda: d.agenda, adjournment: d.adjournment });
    // useForm keeps the last transform: set it before every request, so the signature goes with the submission only.
    const save = (): void => {
        form.transform(strip);
        form.put(url, { preserveScroll: true });
    };
    const draftWithAi = (): void => {
        form.transform(strip);
        form.post(`${url}/draft`, { preserveScroll: true });
    };
    const [signing, setSigning] = useState(false);
    const submit = (): void => {
        form.transform((d) => d);
        form.post(`${url}/submit`, { preserveScroll: true, onSuccess: () => setSigning(false) });
    };
    const filledObjectives = data.objectives.filter((o) => o.trim()).length;
    const readOnly = !document.editable;
    const errorList = Object.values(errors);

    return (
        <AppLayout wide>
            <Head title="AGM Report" />
            <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                <div className="grid content-start gap-6">
                    <header className="grid gap-1">
                        <Link href={`/clubs/${agm.club.slug}/agm`} className="text-sm text-brand-blue">
                            {agm.club.name} · AGM
                        </Link>
                        <h1 className="text-2xl font-bold">Annual General Meeting Report</h1>
                        <p className="text-sm text-ink-muted">Session {record.session} · locked fields come from records and cannot be changed.</p>
                    </header>
                    {document.downloads.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {document.downloads.map((f) => (
                                <a key={f} href={`/documents/${document.id}/download/${f}`}>
                                    <Button variant="secondary" size="sm">
                                        Official 01 AGM Report ({f.toUpperCase()})
                                    </Button>
                                </a>
                            ))}
                        </div>
                    )}
                    <FormSection title="Header">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <LockedField label="Student organization" value={record.club} />
                            <LockedField label="Date" value={record.date} />
                            <LockedField label="Time" value={record.time} />
                            <LockedField label="Venue" value={record.venue} />
                            <LockedField label="Prepared by" value="Whoever signs and submits (their name and position)" />
                            <LockedField label="Checked by (President)" value={record.signers.President} />
                            <LockedField label="Reviewed by (Advisor)" value={record.signers.Advisor} />
                        </div>
                    </FormSection>
                    <FormSection title="1.0 Objectives" hint={`At least ${minObjectives}. AI drafts 3 from the whole session; edit freely or add more.`}>
                        {document.editable && aiAvailable && (
                            <Button variant="secondary" size="sm" className="justify-self-start" loading={drafting} onClick={draftWithAi}>
                                <Icon name="sparkles" size={16} /> {drafting ? 'Drafting…' : 'Draft objectives, speech and agenda with AI'}
                            </Button>
                        )}
                        {ai === 'failed' && <p className="text-sm text-warning">AI couldn't draft this time — please write them yourself.</p>}
                        <DynamicTable
                            rows={data.objectives.map((text) => ({ text }))}
                            onChange={(rows) => setData('objectives', rows.map((r) => r.text))}
                            columns={[{ key: 'text', label: 'Objective', type: 'textarea' }]}
                            blank={{ text: '' }}
                            disabled={readOnly}
                            errors={errors as Record<string, string>}
                            errorPrefix="objectives"
                            addLabel="Add objective"
                        />
                        <p className={`text-xs ${filledObjectives < minObjectives ? 'text-warning' : 'text-ink-muted'}`}>
                            {filledObjectives} written · minimum {minObjectives}
                        </p>
                    </FormSection>
                    <FormSection title="2.0 Attendance" hint="Computed from AGM attendance. Alumni are not counted.">
                        <p className="rounded-control bg-surface-muted p-3 text-sm">
                            Our club has a total of <strong>{record.total_members}</strong> members, <strong>{record.attended_members}</strong> members attended the AGM, <strong>{record.absent_members}</strong> members absent.
                        </p>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="text-xs text-ink-muted">
                                    <tr>
                                        {['No.', 'Name', 'Position', 'Student ID', 'Year', 'Program', ''].map((h) => (
                                            <th key={h} className="p-2">
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {record.attendance.map((r, i) => (
                                        <tr key={i} className="border-t border-line">
                                            <td className="p-2">{i + 1}</td>
                                            <td className="p-2">{r.name}</td>
                                            <td className="p-2">{r.position}</td>
                                            <td className="p-2">{r.student_id}</td>
                                            <td className="p-2">{r.year}</td>
                                            <td className="p-2">{r.programme}</td>
                                            <td className="p-2">
                                                <Badge tone={tone(r.attendance)}>{r.attendance}</Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </FormSection>
                    <FormSection title="3.0 Opening speech">
                        <DynamicTable
                            rows={data.speech.map((text) => ({ text }))}
                            onChange={(rows) => setData('speech', rows.map((r) => r.text))}
                            columns={[{ key: 'text', label: 'Point', type: 'textarea' }]}
                            blank={{ text: '' }}
                            disabled={readOnly}
                            errors={errors as Record<string, string>}
                            errorPrefix="speech"
                            addLabel="Add point"
                        />
                    </FormSection>
                    <FormSection title="4.0 Event agenda">
                        <DynamicTable
                            rows={data.agenda}
                            onChange={(rows) => setData('agenda', rows)}
                            columns={[
                                { key: 'start', label: 'Time start' },
                                { key: 'end', label: 'Time end' },
                                { key: 'item', label: 'Agenda' },
                            ]}
                            blank={{ start: '', end: '', item: '' }}
                            disabled={readOnly}
                            errors={errors as Record<string, string>}
                            errorPrefix="agenda"
                            addLabel="Add item"
                        />
                    </FormSection>
                    <FormSection title={`5.0 Current committee members ${record.session}`} hint="From the club's roles; not editable.">
                        <ul className="grid gap-1 text-sm">
                            {record.committee.map((r, i) => (
                                <li key={i}>
                                    {i + 1}. <strong>{r.position}</strong> — {r.name}{' '}
                                    <span className="text-ink-muted">
                                        {r.student_id} {r.programme}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </FormSection>
                    <FormSection title={`6.0 Election of new committee members ${record.next_session}`} hint="From the election module.">
                        <ul className="grid gap-1 text-sm">
                            {record.election.election.map((l) => (
                                <li key={l}>{l}</li>
                            ))}
                        </ul>
                    </FormSection>
                    <FormSection title={`7.0 Appointment of new committee members ${record.next_session}`}>
                        <ul className="grid gap-1 text-sm">
                            {record.election.appointments.map((l) => (
                                <li key={l}>{l}</li>
                            ))}
                        </ul>
                    </FormSection>
                    <FormSection title="8.0 Adjournment of the meeting">
                        <Textarea value={data.adjournment} onChange={(v) => setData('adjournment', v)} rows={2} readOnly={readOnly} placeholder="The meeting was adjourned at 6.00 PM by the President." />
                    </FormSection>
                    {document.editable && (
                        <div className="flex flex-wrap justify-end gap-2">
                            <AutosaveNote state={autosaved} className="mr-auto self-center" />
                            <Button variant="secondary" loading={form.processing} onClick={save}>
                                Save draft
                            </Button>
                            <Button disabled={filledObjectives < minObjectives} onClick={() => setSigning(true)}>
                                Sign &amp; submit
                            </Button>
                        </div>
                    )}
                </div>
                <aside className="grid content-start gap-4">
                    {document.approval && (
                        <section className="rounded-card border border-line bg-surface p-5 shadow-card">
                            <ApprovalTracker progress={document.approval.progress} steps={document.approval.steps} />
                        </section>
                    )}
                </aside>
            </div>
            <Modal open={signing} onClose={() => setSigning(false)} title="Prepared by">
                <div className="grid gap-4">
                    <p className="text-sm text-ink-muted">Then: President → Club Advisor → SLD. Attendance, committee and election sections are frozen as they are now.</p>
                    <SignaturePad value={data.signature} onChange={(png, strokes) => setData((d) => ({ ...d, signature: png, signature_strokes: strokes }))} label="Your signature" />
                    {errorList.length > 0 && <p className="text-sm text-danger">{errorList[0]}</p>}
                    <Button disabled={!data.signature} loading={form.processing} onClick={submit}>
                        Submit AGM Report
                    </Button>
                </div>
            </Modal>
        </AppLayout>
    );
}
