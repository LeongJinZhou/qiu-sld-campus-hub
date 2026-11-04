import ApprovalTracker from '@/Components/forms/ApprovalTracker';
import ProposalView from '@/Components/forms/ProposalView';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Modal from '@/Components/ui/Modal';
import SignaturePad from '@/Components/ui/SignaturePad';
import Textarea from '@/Components/ui/Textarea';
import AppLayout from '@/Layouts/AppLayout';
import { PROPOSAL_SECTIONS, type ProposalSection } from '@/lib/proposal';
import type { PresentedDocument } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Decision = 'approve' | 'return' | 'reject';
interface ReviewProps {
    approvalId: number;
    role: string;
    canAct: boolean;
    document: PresentedDocument;
    downloads: string[];
    exceptions: { kind: string; label: string; reason: string }[];
    decidesExceptions: boolean;
    paper?: { signers: { label: string; signed: boolean }[]; scan_url: string | null; complete: boolean } | null;
    paperOptions?: string[];
}

/** One approver's step: check the document, then approve and sign, or mark what is wrong and return or reject. */
export default function Review({ approvalId, role, canAct, document, downloads, exceptions, decidesExceptions, paper = null, paperOptions = [] }: ReviewProps) {
    // Higher authorities signing on paper (SLD's step): choose who, export, upload the signed scan, tick who signed.
    const [chosen, setChosen] = useState<string[]>([]);
    const [other, setOther] = useState('');
    const startPaper = (): void => router.post(`/approvals/${approvalId}/paper`, { signers: [...chosen, ...(other.trim() ? [other.trim()] : [])] }, { preserveScroll: true });
    const paperForm = useForm({ scan: null as File | null, signed: (paper?.signers ?? []).filter((s) => s.signed).map((s) => s.label) });
    const savePaper = (): void => paperForm.post(`/approvals/${approvalId}/paper/record`, { preserveScroll: true, forceFormData: true, onSuccess: () => paperForm.setData('scan', null) });
    const cancelPaper = (): void => {
        if (confirm('No paper signatures needed after all? The uploaded scan is removed.')) router.delete(`/approvals/${approvalId}/paper`, { preserveScroll: true });
    };
    const [confirming, setConfirming] = useState(false);

    const form = useForm({ decision: 'approve' as Decision, comment: '', no_problems: false, accept_exceptions: false, signature: null as string | null, signature_strokes: null as string | null });
    const { data, setData, errors } = form;
    // No problems: tick and sign. Problems: mark the wrong parts (proposals) or write a comment, then return or reject.
    const [verdict, setVerdict] = useState<'ok' | 'problems' | null>(null);
    // Returning a proposal: tick the parts to fix and say what is wrong with each; they are highlighted for the club.
    const [marked, setMarked] = useState<Partial<Record<ProposalSection, string>>>({});
    const toggle = (key: ProposalSection, on: boolean): void => {
        const next = { ...marked };
        if (on) next[key] = next[key] ?? '';
        else delete next[key];
        setMarked(next);
    };
    const markedList = Object.entries(marked).map(([key, note]) => ({ key, note: (note ?? '').trim() }));
    const [deciding, setDeciding] = useState<Decision | null>(null);
    const decide = (decision: Decision): void => {
        setDeciding(decision);
        form.transform((d) => ({ ...d, decision, ...(decision !== 'approve' && markedList.length ? { sections: markedList } : {}) }));
        form.post(`/approvals/${approvalId}`, { preserveScroll: true, onFinish: () => setDeciding(null) });
    };
    const verdictButton = (value: 'ok' | 'problems', title: string, hint: string, on: string, cls: string) => (
        <button
            type="button"
            role="radio"
            aria-checked={verdict === value}
            className={`grid gap-1 rounded-card border p-3 text-left text-sm transition ${verdict === value ? on : 'border-line hover:bg-surface-muted'}`}
            onClick={() => setVerdict(value)}
        >
            <strong className={cls}>{title}</strong>
            <span className="text-xs text-ink-muted">{hint}</span>
        </button>
    );
    const canApprove = !!data.signature && data.no_problems && !(exceptions.length > 0 && decidesExceptions && !data.accept_exceptions) && !(paper && !paper.complete);
    const hasProblem = markedList.length > 0 || data.comment.trim() !== '';

    return (
        <AppLayout>
            <Head title={`Review · ${document.type_label}`} />
            <div className="grid gap-6 lg:grid-cols-[1fr_24rem]">
                <div className="grid content-start gap-4">
                    {exceptions.length > 0 && (
                        <section className="grid gap-2 rounded-card border-2 border-warning/50 bg-warning/5 p-4 text-sm">
                            <h2 className="font-semibold text-warning">Exceptions for SLD to decide</h2>
                            {exceptions.map((e) => (
                                <div key={e.kind}>
                                    <p className="font-medium">{e.label}</p>
                                    <p className="text-ink-muted">Reason given: {e.reason || '—'}</p>
                                </div>
                            ))}
                        </section>
                    )}
                    <h1 className="text-xl font-bold">
                        {document.type_label} · {document.event?.name ?? document.club.name}
                    </h1>
                    {downloads.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {downloads.map((format) => (
                                <a key={format} href={`/documents/${document.id}/download/${format}`}>
                                    <Button variant="secondary" size="sm">
                                        Open official {format.toUpperCase()}
                                    </Button>
                                </a>
                            ))}
                        </div>
                    )}
                    {document.type === 'proposal' && <ProposalView document={document} />}
                </div>
                <aside className="grid content-start gap-4">
                    {canAct && (
                        <Card title={`Your decision · ${role}`}>
                            <div className="grid gap-4">
                                <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Your check">
                                    {verdictButton('ok', '✓ No problems', 'Tick, sign and approve', 'border-success bg-success/10 ring-2 ring-success/30', 'text-success')}
                                    {verdictButton('problems', '✕ Something is wrong', 'Mark the parts, return or reject', 'border-danger bg-danger/10 ring-2 ring-danger/30', 'text-danger')}
                                </div>
                                {verdict === 'ok' && (
                                    <div className="fade-up grid gap-3">
                                        <label className="flex items-start gap-2 text-sm">
                                            <input checked={data.no_problems} onChange={(e) => setData('no_problems', e.target.checked)} type="checkbox" className="mt-0.5 size-4 accent-success" />I have checked this{' '}
                                            {document.type_label.toLowerCase()} and found no problems.
                                        </label>
                                        {exceptions.length > 0 && decidesExceptions && (
                                            <label className="flex items-start gap-2 rounded-control border-2 border-warning/50 bg-warning/5 p-3 text-sm">
                                                <input checked={data.accept_exceptions} onChange={(e) => setData('accept_exceptions', e.target.checked)} type="checkbox" className="mt-0.5 size-4 accent-warning" />I approve these
                                                exceptions as SLD: {exceptions.map((e) => e.label).join('; ')}.
                                            </label>
                                        )}
                                        <SignaturePad value={data.signature} onChange={(png, strokes) => setData((d) => ({ ...d, signature: png, signature_strokes: strokes }))} label="Sign to approve" />
                                        {paper && !paper.complete && (
                                            <p className="rounded-control bg-warning/10 px-3 py-2 text-xs text-warning">Waiting for the paper signatures: upload the signed scan and tick everyone who signed.</p>
                                        )}
                                        <Button disabled={!canApprove} loading={deciding === 'approve'} onClick={() => setConfirming(true)}>
                                            Approve &amp; sign
                                        </Button>
                                    </div>
                                )}
                                {verdict === 'problems' && (
                                    <div className="fade-up grid gap-3">
                                        {document.type === 'proposal' && (
                                            <div className="grid gap-2 rounded-control border border-line p-3">
                                                <p className="text-sm font-medium">Which parts are wrong?</p>
                                                {(Object.entries(PROPOSAL_SECTIONS) as [ProposalSection, string][]).map(([key, label]) => (
                                                    <div key={key} className="grid gap-1">
                                                        <label className="flex items-center gap-2 text-sm">
                                                            <input type="checkbox" className="size-4 accent-danger" checked={key in marked} onChange={(e) => toggle(key, e.target.checked)} />
                                                            {label}
                                                        </label>
                                                        {key in marked && (
                                                            <input
                                                                value={marked[key] ?? ''}
                                                                onChange={(e) => setMarked({ ...marked, [key]: e.target.value })}
                                                                className="fade-up ml-6 h-9 rounded-control border border-danger/40 px-2 text-sm outline-none focus:ring-2 focus:ring-danger/20"
                                                                placeholder={`What is wrong in ${label.toLowerCase()}`}
                                                            />
                                                        )}
                                                    </div>
                                                ))}
                                                {Object.keys(errors).some((k) => k.startsWith('sections')) && <p className="text-xs text-danger">Write what is wrong for every part you ticked.</p>}
                                            </div>
                                        )}
                                        <Textarea
                                            value={data.comment}
                                            onChange={(v) => setData('comment', v)}
                                            label={document.type === 'proposal' ? 'Anything else (optional if you marked parts)' : 'What is wrong'}
                                            rows={3}
                                            error={errors.comment}
                                        />
                                        <div className="grid grid-cols-2 gap-2">
                                            <Button variant="secondary" disabled={!hasProblem} loading={deciding === 'return'} onClick={() => decide('return')}>
                                                Return to fix
                                            </Button>
                                            <Button variant="danger" disabled={!hasProblem} loading={deciding === 'reject'} onClick={() => decide('reject')}>
                                                Reject
                                            </Button>
                                        </div>
                                        <p className="text-xs text-ink-muted">Return: the committee fixes the marked parts and resubmits. Reject: they can still correct it, but everyone signs again from the start.</p>
                                    </div>
                                )}
                            </div>
                        </Card>
                    )}
                    {(paperOptions.length > 0 || paper) && (
                        <Card title="Higher-authority signatures (paper)">
                            {!paper ? (
                                <div className="grid gap-3 text-sm">
                                    <p className="text-ink-muted">If this needs a higher authority's signature, choose who signs. You then export it, have it signed, and upload the scan.</p>
                                    {paperOptions.map((o) => (
                                        <label key={o} className="flex items-center gap-2">
                                            <input type="checkbox" checked={chosen.includes(o)} onChange={(e) => setChosen(e.target.checked ? [...chosen, o] : chosen.filter((c) => c !== o))} className="size-4 accent-primary" /> {o}
                                        </label>
                                    ))}
                                    <input value={other} onChange={(e) => setOther(e.target.value)} maxLength={120} placeholder="Someone else (name and title)" className="h-9 rounded-control border border-line bg-surface px-2 text-sm" />
                                    <Button variant="secondary" disabled={!chosen.length && !other.trim()} onClick={startPaper}>
                                        Send for signature on paper
                                    </Button>
                                </div>
                            ) : (
                                <div className="grid gap-3 text-sm">
                                    <ol className="grid gap-3">
                                        <li className="grid gap-1">
                                            <p className="font-medium">1. Export and have it signed</p>
                                            <div className="flex flex-wrap gap-2">
                                                {downloads.map((format) => (
                                                    <a key={format} href={`/documents/${document.id}/download/${format}`} className="text-primary underline">
                                                        {format.toUpperCase()}
                                                    </a>
                                                ))}
                                            </div>
                                        </li>
                                        {canAct && (
                                            <li className="grid gap-1">
                                                <p className="font-medium">2. Upload the signed scan</p>
                                                <input type="file" accept="application/pdf,image/*" className="text-xs" onChange={(e) => paperForm.setData('scan', e.target.files?.[0] ?? null)} />
                                                {paperForm.errors.scan && <p className="text-xs text-danger">{paperForm.errors.scan}</p>}
                                                {paper.scan_url && (
                                                    <a href={paper.scan_url} target="_blank" rel="noopener" className="text-xs text-primary underline">
                                                        Signed scan on file
                                                    </a>
                                                )}
                                            </li>
                                        )}
                                        <li className="grid gap-1">
                                            <p className="font-medium">{canAct ? '3. Tick who has signed' : 'Signatures'}</p>
                                            {paper.signers.map((sgn) => (
                                                <label key={sgn.label} className="flex items-center gap-2">
                                                    {canAct ? (
                                                        <input
                                                            type="checkbox"
                                                            checked={paperForm.data.signed.includes(sgn.label)}
                                                            onChange={(e) => paperForm.setData('signed', e.target.checked ? [...paperForm.data.signed, sgn.label] : paperForm.data.signed.filter((s) => s !== sgn.label))}
                                                            className="size-4 accent-success"
                                                        />
                                                    ) : (
                                                        <span>{sgn.signed ? '✓' : '…'}</span>
                                                    )}
                                                    {sgn.label}
                                                </label>
                                            ))}
                                        </li>
                                    </ol>
                                    {canAct && (
                                        <div className="flex flex-wrap gap-2">
                                            <Button size="sm" loading={paperForm.processing} onClick={savePaper}>
                                                Save
                                            </Button>
                                            <Button size="sm" variant="ghost" onClick={cancelPaper}>
                                                Not needed
                                            </Button>
                                        </div>
                                    )}
                                    <p className={`text-xs ${paper.complete ? 'text-success' : 'text-ink-muted'}`}>
                                        {paper.complete ? '✓ All signed and the scan is on file. You can now give the final approval.' : 'The final approval opens once every signatory is ticked and the scan is uploaded.'}
                                    </p>
                                </div>
                            )}
                        </Card>
                    )}
                    <Card title="Approval progress">
                        <ApprovalTracker progress={document.progress} steps={document.steps} />
                    </Card>
                </aside>
            </div>
            <Modal open={confirming} onClose={() => setConfirming(false)} title="Confirm the approval">
                <div className="grid gap-3 text-sm">
                    <p>
                        You are approving this {document.type_label.toLowerCase()}
                        {decidesExceptions && ' as the final step'}.{paper && ` It records that ${paper.signers.map((s) => s.label).join(', ')} signed on paper.`}
                    </p>
                    <div className="flex justify-end gap-2">
                        <Button variant="secondary" onClick={() => setConfirming(false)}>
                            Go back
                        </Button>
                        <Button
                            loading={form.processing}
                            onClick={() => {
                                setConfirming(false);
                                decide('approve');
                            }}
                        >
                            Yes, approve
                        </Button>
                    </div>
                </div>
            </Modal>
        </AppLayout>
    );
}
