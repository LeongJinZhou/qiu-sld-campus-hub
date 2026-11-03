import Badge from '@react/Components/ui/Badge';
import Button from '@react/Components/ui/Button';
import EmptyState from '@react/Components/ui/EmptyState';
import AppLayout from '@react/Layouts/AppLayout';
import { rm } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Item {
    id: number;
    student: string;
    student_number: string | null;
    status: string;
    amount_sen: number;
    ai: { amount_sen: number | null; date: string | null; reference: string | null; payee: string | null; payer: string | null; confidence: number | null };
    flags: string[];
    has_reference: boolean;
    order_id: string | null;
    receipt_url: string;
    at: string | null;
}

/** The Treasurer's queue: unclear receipts first, then the latest auto-confirmed ones for spot checks. */
export default function Review({ club, fee_sen, pending_total, items }: { club: { slug: string; name: string }; fee_sen: number | null; pending_total: number; items: Item[] }) {
    const [references, setReferences] = useState<Record<number, string>>({});
    function decide(id: number, approve: boolean): void {
        const note = approve ? null : prompt('Reason for rejecting (sent to the student)');
        if (!approve && !note) return;
        router.post(`/payments/${id}/decide`, { approve, note, reference: references[id] || null }, { preserveScroll: true });
    }
    const flagged = (p: Item, ...flags: string[]): string => (p.flags.some((f) => flags.includes(f)) ? 'font-semibold text-danger' : '');

    return (
        <AppLayout wide>
            <Head title="Payment review" />
            <div className="grid gap-4">
                <div>
                    <h1 className="text-xl font-bold">Membership payments · {club.name}</h1>
                    <p className="text-sm text-ink-muted">
                        Fee {fee_sen !== null ? rm(fee_sen) : 'not set'}. Unclear receipts wait here ({pending_total} waiting, oldest first); the latest 50 auto-confirmed ones follow for spot checks.
                    </p>
                </div>
                {!items.length && <EmptyState title="Nothing to review" />}
                {items.map((p) => {
                    const checks: [string, string, string, string][] = [
                        ['Amount', rm(p.amount_sen), p.ai.amount_sen !== null ? rm(p.ai.amount_sen) : '—', flagged(p, 'amount_mismatch')],
                        ['Date', 'within 7 days', p.ai.date ?? '—', flagged(p, 'old_receipt', 'future_date', 'no_date')],
                        ['Reference', 'unique', p.ai.reference ?? '—', flagged(p, 'no_reference')],
                        ['Sender', p.student, p.ai.payer ?? '—', flagged(p, 'payer_mismatch')],
                        ['Payee', 'club account', p.ai.payee ?? '—', flagged(p, 'payee_mismatch')],
                        ['Confidence', '≥ 80%', p.ai.confidence !== null ? `${Math.round(p.ai.confidence * 100)}%` : '—', ''],
                    ];
                    return (
                        <article key={p.id} className="grid gap-4 rounded-card border border-line bg-surface p-4 shadow-card md:grid-cols-[18rem_1fr]">
                            <a href={p.receipt_url} target="_blank" rel="noopener" className="block overflow-hidden rounded-control border border-line bg-surface-muted">
                                <img src={p.receipt_url} alt="Receipt" className="max-h-80 w-full object-contain" />
                            </a>
                            <div className="grid content-start gap-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p>
                                        <strong>{p.student}</strong>{' '}
                                        <span className="text-sm text-ink-muted">
                                            {p.student_number} · {p.at}
                                        </span>
                                    </p>
                                    <Badge tone={p.status === 'auto_confirmed' ? 'success' : 'warning'}>{p.status.replace('_', ' ')}</Badge>
                                </div>
                                <table className="w-full text-sm">
                                    <thead className="text-left text-xs uppercase text-ink-muted">
                                        <tr>
                                            <th className="py-1">Check</th>
                                            <th>Expected</th>
                                            <th>AI read</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {checks.map(([label, expected, read, cls]) => (
                                            <tr key={label}>
                                                <td className="py-1">{label}</td>
                                                <td className="py-1">{expected}</td>
                                                <td className={`py-1 ${cls}`}>{read}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {p.flags.includes('duplicate_reference') && <p className="rounded-control bg-danger/10 p-2 text-sm font-semibold text-danger">This transaction ID was already used for another payment.</p>}
                                {p.order_id && <p className="break-all font-mono text-xs text-ink-muted">{p.order_id}</p>}
                                {p.status === 'pending_review' && !p.has_reference && (
                                    <label className="grid gap-1 text-sm">
                                        <span className="font-medium">
                                            Transaction ID on the receipt <span className="text-ink-muted">(needed to confirm; checked against every payment)</span>
                                        </span>
                                        <input value={references[p.id] ?? ''} onChange={(e) => setReferences({ ...references, [p.id]: e.target.value })} className="rounded-control border border-line bg-surface px-3 py-2" placeholder="e.g. 2026110112345678" />
                                    </label>
                                )}
                                {p.status === 'pending_review' && (
                                    <div className="flex flex-wrap gap-2">
                                        <Button onClick={() => decide(p.id, true)}>Confirm payment</Button>
                                        <Button variant="secondary" onClick={() => decide(p.id, false)}>
                                            Reject
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </article>
                    );
                })}
            </div>
        </AppLayout>
    );
}
