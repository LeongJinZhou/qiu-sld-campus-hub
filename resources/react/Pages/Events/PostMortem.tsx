import ApprovalTracker from '@react/Components/forms/ApprovalTracker';
import AutosaveNote from '@react/Components/forms/AutosaveNote';
import DynamicTable from '@react/Components/forms/DynamicTable';
import FormSection from '@react/Components/forms/FormSection';
import LockedField from '@react/Components/forms/LockedField';
import Button from '@react/Components/ui/Button';
import Icon from '@react/Components/ui/Icon';
import Modal from '@react/Components/ui/Modal';
import SignaturePad from '@react/Components/ui/SignaturePad';
import { useAutosave } from '@react/hooks/useAutosave';
import AppLayout from '@react/Layouts/AppLayout';
import { copy } from '@/lib/clone';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Row = { well: string; challenges: string; improvement: string };
interface PostMortemProps {
    event: { slug: string; name: string };
    header: { event: string; club: string; date: string; time: string; venue: string; participants: number; committee: string[] };
    areas: Record<string, string>;
    document: {
        id: number;
        status: string;
        editable: boolean;
        data: { rows: Record<string, Row>; recommendations: string[] };
        approval: { progress: ApprovalProgress; steps: ApprovalStep[] } | null;
        downloads: string[];
        ai: string | null;
        ai_available: boolean;
    };
}
const FIELDS = ['well', 'challenges', 'improvement'] as const;

export default function PostMortem({ event, header, areas, document }: PostMortemProps) {
    const form = useForm({ rows: copy(document.data.rows), recommendations: [...document.data.recommendations], signature: null as string | null, signature_strokes: null as string | null });
    const { data, setData } = form;
    const readOnly = !document.editable;
    const url = `/events/${event.slug}/post-mortem`;
    const suggesting = document.ai === 'pending';
    const draft = { rows: data.rows, recommendations: data.recommendations };
    const autosaved = useAutosave(url, draft, document.editable && !suggesting, form.processing);
    usePoll(2500, { only: ['document'] }, { autoStart: suggesting });
    // When the AI has finished, load what it wrote into the form.
    useEffect(() => {
        if (!suggesting) setData((d) => ({ ...d, rows: copy(document.data.rows), recommendations: [...document.data.recommendations] }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [document.ai]);
    const [signing, setSigning] = useState(false);

    // useForm keeps the last transform: set it before every request, so the signature always goes with the submission.
    const save = (): void => {
        form.transform((d) => ({ rows: d.rows, recommendations: d.recommendations }));
        form.put(url, { preserveScroll: true });
    };
    const suggest = (): void => {
        form.transform((d) => ({ rows: d.rows, recommendations: d.recommendations }));
        form.post(`${url}/suggest`, { preserveScroll: true });
    };
    const submit = (): void => {
        form.transform((d) => d);
        form.post(`${url}/submit`, { preserveScroll: true, onSuccess: () => setSigning(false) });
    };
    const setCell = (key: string, field: (typeof FIELDS)[number], value: string): void => setData('rows', { ...data.rows, [key]: { ...(data.rows[key] as Row), [field]: value } });
    const errors = Object.values(form.errors);

    return (
        <AppLayout wide>
            <Head title={`Post mortem · ${event.name}`} />
            <div className="grid gap-6">
                <header>
                    <Link href={`/events/${event.slug}`} className="text-sm text-brand-blue">
                        {event.name}
                    </Link>
                    <h1 className="text-xl font-bold">Event Post Mortem</h1>
                    <p className="text-sm text-ink-muted">09 template · Prepared by → Club Advisor → SLD</p>
                </header>
                {document.approval && (
                    <section className="rounded-card border border-line bg-surface p-5 shadow-card">
                        <ApprovalTracker progress={document.approval.progress} steps={document.approval.steps} />
                    </section>
                )}
                {document.downloads.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {document.downloads.map((f) => (
                            <a key={f} href={`/documents/${document.id}/download/${f}`}>
                                <Button variant="secondary" size="sm">
                                    Official 09 Post Mortem ({f.toUpperCase()})
                                </Button>
                            </a>
                        ))}
                    </div>
                )}
                <FormSection title="Event">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <LockedField label="Event Name" value={header.event} />
                        <LockedField label="Organizing Club" value={header.club} />
                        <LockedField label="Date" value={header.date} />
                        <LockedField label="Time" value={header.time} />
                        <LockedField label="Venue" value={header.venue} />
                        <LockedField label="Number of Actual Participants" value={String(header.participants)} />
                    </div>
                    <LockedField label="Committees (with position)" value={header.committee.join(' · ')} />
                </FormSection>
                <FormSection title="Review" hint='Fill the relevant one. If there is nothing, leave it empty — the form prints "N/A".'>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[56rem] text-sm">
                            <thead className="text-left text-xs uppercase text-ink-muted">
                                <tr>
                                    <th className="w-72 py-2" />
                                    <th className="px-2 py-2">What went well</th>
                                    <th className="px-2 py-2">Challenges faced</th>
                                    <th className="px-2 py-2">Improvement suggestion</th>
                                </tr>
                            </thead>
                            <tbody>
                                {Object.entries(areas).map(([key, label]) => (
                                    <tr key={key} className="border-t border-line align-top">
                                        <th className="py-2 pr-3 text-left font-medium">
                                            {key}. {label}
                                        </th>
                                        {FIELDS.map((field) => (
                                            <td key={field} className="px-1 py-1.5">
                                                <textarea
                                                    value={data.rows[key]?.[field] ?? ''}
                                                    onChange={(e) => setCell(key, field, e.target.value)}
                                                    rows={3}
                                                    readOnly={readOnly}
                                                    aria-label={`${key} ${field}`}
                                                    className="w-full rounded-control border border-line px-2 py-1.5 read-only:bg-surface-muted"
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </FormSection>
                <FormSection title="Recommendations for future committees">
                    <DynamicTable
                        rows={data.recommendations.map((text) => ({ text }))}
                        onChange={(rows) => setData('recommendations', rows.map((r) => r.text))}
                        columns={[{ key: 'text', label: 'Recommendation', type: 'textarea' }]}
                        blank={{ text: '' }}
                        disabled={readOnly}
                        errorPrefix="recommendations"
                        addLabel="Add recommendation"
                    />
                    {document.editable && document.ai_available && (
                        <Button variant="secondary" className="justify-self-start" loading={suggesting} onClick={suggest}>
                            <Icon name="sparkles" size={16} /> {suggesting ? 'Thinking…' : 'Suggest improvements with AI'}
                        </Button>
                    )}
                    <p className="text-xs text-ink-muted">AI only fills empty improvement and recommendation boxes; edit anything it writes.</p>
                </FormSection>
                {document.editable && (
                    <div className="flex flex-wrap justify-end gap-2">
                        <AutosaveNote state={autosaved} className="mr-auto self-center" />
                        <Button variant="secondary" loading={form.processing} onClick={save}>
                            Save draft
                        </Button>
                        <Button onClick={() => setSigning(true)}>Sign &amp; submit</Button>
                    </div>
                )}
            </div>
            <Modal open={signing} onClose={() => setSigning(false)} title="Prepared by">
                <div className="grid gap-4">
                    <SignaturePad value={data.signature} onChange={(png, strokes) => setData((d) => ({ ...d, signature: png, signature_strokes: strokes }))} label="Signature" />
                    {errors.length > 0 && <p className="text-sm text-danger">{errors[0]}</p>}
                    <Button disabled={!data.signature} loading={form.processing} onClick={submit}>
                        Submit post mortem
                    </Button>
                </div>
            </Modal>
        </AppLayout>
    );
}
