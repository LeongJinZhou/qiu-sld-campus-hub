import ApprovalTracker from '@react/Components/forms/ApprovalTracker';
import Badge from '@react/Components/ui/Badge';
import Button from '@react/Components/ui/Button';
import Icon from '@react/Components/ui/Icon';
import Input from '@react/Components/ui/Input';
import Modal from '@react/Components/ui/Modal';
import SignaturePad from '@react/Components/ui/SignaturePad';
import AppLayout from '@react/Layouts/AppLayout';
import { dateTime, rm } from '@/lib/format';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type ChangeEvent } from 'react';

interface WithdrawalProps {
    withdrawal: {
        id: number;
        status: string;
        stage: string | null;
        stage_label: string | null;
        club: { slug: string; name: string };
        event: { slug: string; name: string } | null;
        scope: 'event' | 'club';
        purpose: string | null;
        needed_by: string | null;
        items: { description: string; unit_sen: number; quantity: number; amount_sen: number }[];
        total_sen: number;
        bank: { bank_name: string; account_name: string; account_number: string } | null;
        prepared_by: string | null;
        created_at: string | null;
        bankin_reference: string | null;
        note: string | null;
        order_id: string | null;
        completed_at: string | null;
        files: Partial<Record<'form' | 'finance' | 'bankin', string>>;
    };
    approval: { progress: ApprovalProgress; steps: ApprovalStep[] } | null;
    stages: Record<string, string>;
    can: { sign: boolean; sld: boolean; bankin: boolean };
}
const ORDER = ['awaiting_finance', 'awaiting_bankin', 'bankin_uploaded', 'completed'];
const action = 'grid gap-3 rounded-card border-2 border-brand-cyan/40 bg-brand-cyan/5 p-4';
const fileLink = 'inline-flex items-center gap-1.5 rounded-control border border-line px-3 py-2 hover:bg-surface-muted';

/** One fund withdrawal's journey: signatures, Finance, bank-in, SLD confirms; each person sees what they must do next. */
export default function Withdrawal({ withdrawal: w, approval, can }: WithdrawalProps) {
    // The whole journey on one line: signatures first, then the money.
    const signed = w.status === 'approved';
    const at = w.stage ? ORDER.indexOf(w.stage) : -1;
    const journey = [
        { label: 'Filled in', done: true, current: false },
        { label: 'Treasurer, Advisor & SLD sign', done: signed, current: !signed },
        { label: 'Finance signs', done: at > 0, current: at === 0 },
        { label: 'Bank-in', done: at > 1, current: at === 1 },
        { label: 'SLD confirms', done: at > 2, current: at === 2 },
    ];
    const sign = useForm({ signature: null as string | null, signature_strokes: null as string | null, bank_confirmed: false });
    const [signing, setSigning] = useState(false);
    const doSign = (): void => {
        sign.transform((d) => ({ ...d, bank_confirmed: true }));
        sign.post(`/withdrawals/${w.id}/sign`, { preserveScroll: true, onFinish: () => setSigning(false) });
    };
    const upload = useForm({ file: null as File | null, reference: '' });
    const send = (step: 'finance-signed' | 'bank-in', e: ChangeEvent<HTMLInputElement>): void => {
        const file = e.target.files?.[0] ?? null;
        e.target.value = '';
        if (!file) return;
        upload.transform((d) => ({ ...d, file }));
        upload.post(`/withdrawals/${w.id}/${step}`, { preserveScroll: true, forceFormData: true, onSuccess: () => upload.reset() });
    };
    const complete = (): void => {
        if (confirm(`Confirm RM ${(w.total_sen / 100).toFixed(2)} arrived? It will be recorded in the club finance.`)) router.post(`/withdrawals/${w.id}/complete`, {}, { preserveScroll: true });
    };
    const sendBack = (): void => {
        const note = prompt('What is wrong with the bank-in proof? (sent to the Treasurer)');
        if (note) router.post(`/withdrawals/${w.id}/reject-proof`, { note }, { preserveScroll: true });
    };
    const bankDetails = w.bank && (
        <>
            <div className="flex justify-between gap-3">
                <dt className="text-ink-muted">Bank</dt>
                <dd className="font-semibold">{w.bank.bank_name}</dd>
            </div>
            <div className="flex justify-between gap-3">
                <dt className="text-ink-muted">Account name</dt>
                <dd className="font-semibold">{w.bank.account_name}</dd>
            </div>
            <div className="flex justify-between gap-3">
                <dt className="text-ink-muted">Account number</dt>
                <dd className="font-mono text-base font-bold tracking-wider">{w.bank.account_number}</dd>
            </div>
        </>
    );
    const signErrors = Object.values(sign.errors);

    return (
        <AppLayout>
            <Head title="Fund withdrawal" />
            <div className="grid gap-5">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link href={`/clubs/${w.club.slug}/finance`} className="text-sm text-brand-blue">
                            ← {w.club.name} finance
                        </Link>
                        <h1 className="text-xl font-bold">Fund withdrawal · {rm(w.total_sen)}</h1>
                        <p className="text-sm text-ink-muted">
                            <Badge tone={w.scope === 'event' ? 'info' : 'neutral'}>{w.scope === 'event' ? 'Event' : 'Club'}</Badge>{' '}
                            {w.event ? (
                                <Link href={`/events/${w.event.slug}`} className="font-medium hover:underline">
                                    {w.event.name}
                                </Link>
                            ) : (
                                w.purpose
                            )}{' '}
                            · filled in by {w.prepared_by}
                            {w.created_at && ` ${dateTime(w.created_at)}`}
                        </p>
                    </div>
                    <Badge tone={w.stage === 'completed' ? 'success' : w.status === 'rejected' ? 'danger' : 'warning'}>{w.stage_label ?? w.status.replace('_', ' ')}</Badge>
                </header>
                <ol className="grid grid-cols-5 gap-1 text-center text-[11px] sm:text-xs" aria-label="Progress">
                    {journey.map((s, i) => (
                        <li key={i} className="grid justify-items-center gap-1">
                            <span className={`grid size-7 place-items-center rounded-full text-xs font-bold transition ${s.done ? 'bg-success text-white' : s.current ? 'animate-tracker-pulse bg-brand-magenta text-white' : 'bg-surface-muted text-ink-muted'}`}>
                                {s.done ? '✓' : i + 1}
                            </span>
                            <span className={s.current ? 'font-semibold' : 'text-ink-muted'}>{s.label}</span>
                        </li>
                    ))}
                </ol>
                {w.note && <p className="rounded-card bg-danger/10 px-4 py-3 text-sm text-danger">{w.note}</p>}
                {w.order_id && (
                    <p className="rounded-card bg-success/10 px-4 py-3 text-sm text-success">
                        Recorded in the club finance {w.completed_at ? dateTime(w.completed_at) : ''} · transaction <span className="font-mono font-semibold">{w.order_id}</span>
                    </p>
                )}
                {can.sign && (
                    <section className={action}>
                        <h2 className="font-semibold">Your signature as Treasurer</h2>
                        <p className="text-sm">Check the items and bank details below, then sign. It goes to the Advisor next.</p>
                        <Button className="justify-self-start" onClick={() => setSigning(true)}>
                            Check &amp; sign
                        </Button>
                    </section>
                )}
                {can.sld && w.stage === 'awaiting_finance' && (
                    <section className={action}>
                        <h2 className="font-semibold">SLD: send to Finance</h2>
                        <p className="text-sm">1. Download the 07 form and pass it to Finance to sign. 2. Upload Finance's signed copy here.</p>
                        <div className="flex flex-wrap gap-2">
                            {w.files.form && (
                                <a href={w.files.form}>
                                    <Button variant="secondary">
                                        <Icon name="file" size={16} /> Download 07 form (PDF)
                                    </Button>
                                </a>
                            )}
                            <label className="inline-flex h-10 cursor-pointer items-center gap-2 rounded-control bg-ink px-4 text-sm font-medium text-white">
                                {upload.processing ? 'Uploading…' : 'Upload Finance-signed copy'}
                                <input type="file" accept="application/pdf,image/*" className="sr-only" onChange={(e) => send('finance-signed', e)} />
                            </label>
                        </div>
                    </section>
                )}
                {can.bankin && (
                    <section className={action}>
                        <h2 className="font-semibold">Treasurer: did the money arrive?</h2>
                        <p className="text-sm">
                            When {rm(w.total_sen)} reaches {w.bank?.account_name} ({w.bank?.bank_name}), upload the bank-in receipt or statement.
                        </p>
                        <div className="flex flex-wrap items-end gap-2">
                            <div className="w-56">
                                <Input value={upload.data.reference} onChange={(v) => upload.setData('reference', v)} label="Bank reference (optional)" />
                            </div>
                            <label className="inline-flex h-10 cursor-pointer items-center gap-2 rounded-control bg-ink px-4 text-sm font-medium text-white">
                                {upload.processing ? 'Uploading…' : 'Upload bank-in proof'}
                                <input type="file" accept="application/pdf,image/*" className="sr-only" onChange={(e) => send('bank-in', e)} />
                            </label>
                        </div>
                    </section>
                )}
                {can.sld && w.stage === 'bankin_uploaded' && (
                    <section className={action}>
                        <h2 className="font-semibold">SLD: confirm the bank-in</h2>
                        <p className="text-sm">Open the proof below. Confirming records the withdrawal in the club finance; the balance changes only now.</p>
                        <div className="flex flex-wrap gap-2">
                            <Button onClick={complete}>Confirm &amp; record</Button>
                            <Button variant="secondary" onClick={sendBack}>
                                Send back
                            </Button>
                        </div>
                    </section>
                )}
                {upload.errors.file && <p className="text-sm text-danger">{upload.errors.file}</p>}
                <section className="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card">
                    <h2 className="font-semibold">Items</h2>
                    <ul className="grid gap-1 text-sm">
                        {w.items.map((i, n) => (
                            <li key={n} className="flex justify-between gap-3">
                                <span>
                                    {i.description} <span className="text-ink-muted">× {i.quantity}</span>
                                </span>
                                <span className="tabular-nums">{rm(i.amount_sen)}</span>
                            </li>
                        ))}
                    </ul>
                    <p className="flex justify-between border-t border-line pt-2 font-semibold">
                        <span>Total</span>
                        <span className="tabular-nums">{rm(w.total_sen)}</span>
                    </p>
                    {w.bank && (
                        <dl className="grid gap-1 rounded-control bg-surface-muted p-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt className="text-xs text-ink-muted">Bank</dt>
                                <dd className="font-medium">{w.bank.bank_name}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-muted">Account name</dt>
                                <dd className="font-medium">{w.bank.account_name}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-muted">Account number</dt>
                                <dd className="font-mono font-medium">{w.bank.account_number}</dd>
                            </div>
                        </dl>
                    )}
                </section>
                <section className="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card">
                    <h2 className="font-semibold">Evidence</h2>
                    <div className="flex flex-wrap gap-2 text-sm">
                        {w.files.form && (
                            <a href={w.files.form} className={fileLink}>
                                <Icon name="file" size={16} /> 07 form
                            </a>
                        )}
                        {w.files.finance && (
                            <a href={w.files.finance} target="_blank" rel="noopener" className={fileLink}>
                                <Icon name="check" size={16} /> Finance-signed copy
                            </a>
                        )}
                        {w.files.bankin && (
                            <a href={w.files.bankin} target="_blank" rel="noopener" className={fileLink}>
                                <Icon name="receipt" size={16} /> Bank-in proof{w.bankin_reference && ` · ${w.bankin_reference}`}
                            </a>
                        )}
                        {!Object.keys(w.files).length && <span className="text-ink-muted">Files appear here as each step is done.</span>}
                    </div>
                </section>
                {approval && (
                    <section className="rounded-card border border-line bg-surface p-4 shadow-card">
                        <ApprovalTracker progress={approval.progress} steps={approval.steps} />
                    </section>
                )}
            </div>
            <Modal open={signing} onClose={() => setSigning(false)} title="Check the bank details, then sign">
                <div className="grid gap-4 text-sm">
                    {w.bank && (
                        <dl className="grid gap-2 rounded-card border-2 border-warning/50 bg-warning/5 p-4">
                            {bankDetails}
                            <div className="flex justify-between gap-3 border-t border-line pt-2">
                                <dt className="text-ink-muted">Amount</dt>
                                <dd className="font-semibold">{rm(w.total_sen)}</dd>
                            </div>
                        </dl>
                    )}
                    <SignaturePad value={sign.data.signature} onChange={(png, strokes) => sign.setData((d) => ({ ...d, signature: png, signature_strokes: strokes }))} label="Your signature" />
                    {signErrors.length > 0 && <p className="text-danger">{signErrors[0]}</p>}
                    <Button disabled={!sign.data.signature} loading={sign.processing} onClick={doSign}>
                        The bank details are correct · sign
                    </Button>
                </div>
            </Modal>
        </AppLayout>
    );
}
