import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Icon from '@/Components/ui/Icon';
import Input from '@/Components/ui/Input';
import LiveQrCapture from '@/Components/ui/LiveQrCapture';
import Modal from '@/Components/ui/Modal';
import Select from '@/Components/ui/Select';
import AppLayout from '@/Layouts/AppLayout';
import { rm } from '@/lib/format';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState, type ChangeEvent } from 'react';

interface Item {
    name: string;
    quantity: number;
    unit_sen: number;
    total_sen?: number;
}
interface Purchase {
    id: number;
    status: 'reading' | 'draft' | 'pending' | 'owed' | 'paying' | 'recorded' | 'rejected';
    paid_with: 'club' | 'own';
    items: Item[];
    total_sen: number;
    shop: string | null;
    purchased_on: string | null;
    budget_line: string | null;
    ai_confidence: number | null;
    buyer: { id: number; name: string; qr_token: string | null };
    verifier: string | null;
    payer: string | null;
    i_paid: boolean;
    review_note: string | null;
    settle_when: 'now' | 'after_event' | null;
    settle_method: 'cash' | 'online' | null;
    payout_check: string | null;
    payout_payee: string | null;
    mine: boolean;
    to_verify: boolean;
    receipt_url: string;
    payout_url: string | null;
    at: string | null;
}
interface Draft {
    items: { name: string; quantity: string; price_rm: string }[];
    shop: string;
    purchased_on: string;
    budget_line: string;
    paid_with: 'club' | 'own';
    verifier_id: number | null;
}
interface PurchasesProps {
    event: { slug: string; name: string; ended: boolean; club: { slug: string; name: string } };
    purchases: Purchase[];
    budget: { description: string; budget_sen: number; spent_sen: number }[];
    budgetLines: string[];
    verifiers: { id: number; name: string }[];
    can: { organise: boolean };
}
const statusText = (p: Purchase): string =>
    ({
        reading: 'Reading the receipt…',
        draft: 'Check the items',
        pending: `Waiting for ${p.verifier ?? 'verification'}`,
        owed: p.settle_when === 'after_event' ? 'To pay back after the event' : 'To pay back now',
        paying: p.settle_method === 'cash' ? 'Cash handed over, waiting for the buyer' : p.payout_check === 'unread' ? 'Transfer to confirm' : 'Checking the transfer…',
        recorded: p.paid_with === 'club' ? 'Recorded (club money)' : 'Paid back and recorded',
        rejected: 'Not accepted',
    })[p.status];
const tone = (p: Purchase): 'success' | 'warning' | 'danger' | 'info' => (p.status === 'recorded' ? 'success' : p.status === 'rejected' ? 'danger' : p.status === 'reading' || p.status === 'paying' ? 'info' : 'warning');
const barWidth = (l: { budget_sen: number; spent_sen: number }): string => `${Math.min(100, l.budget_sen ? (l.spent_sen / l.budget_sen) * 100 : 100)}%`;
const toSen = (rmText: string): number => Math.round(Number(rmText || 0) * 100);

/** Event purchases: snap a receipt, AI reads it, the buyer checks it, the committee verifies and pays back (cash or transfer). */
export default function Purchases({ event, purchases, budget, budgetLines, verifiers, can }: PurchasesProps) {
    // Snap: the photo goes up at once; AI reads it in the background while this page checks back.
    const [snapping, setSnapping] = useState(false);
    const snap = (e: ChangeEvent<HTMLInputElement>): void => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;
        setSnapping(true);
        router.post(`/events/${event.slug}/purchases`, { receipt: file }, { forceFormData: true, preserveScroll: true, onFinish: () => setSnapping(false) });
    };
    const busy = purchases.some((p) => p.status === 'reading' || p.payout_check === 'reading');
    useEffect(() => {
        if (!busy) return;
        const timer = setInterval(() => router.reload({ only: ['purchases', 'budget'] }), 2500);
        return () => clearInterval(timer);
    }, [busy]);

    // Drafts the buyer is checking: items as read by AI, editable, total worked out as they type.
    const [drafts, setDrafts] = useState<Record<number, Draft>>({});
    useEffect(() => {
        setDrafts((current) => {
            const next = { ...current };
            for (const p of purchases) {
                if (p.status === 'draft' && p.mine && !next[p.id]) {
                    next[p.id] = {
                        items: (p.items.length ? p.items : [{ name: '', quantity: 1, unit_sen: 0 }]).map((i) => ({ name: i.name, quantity: String(i.quantity), price_rm: (i.unit_sen / 100).toFixed(2) })),
                        shop: p.shop ?? '',
                        purchased_on: p.purchased_on ?? '',
                        budget_line: p.budget_line ?? '',
                        paid_with: can.organise ? 'club' : 'own',
                        verifier_id: null,
                    };
                }
            }
            return next;
        });
    }, [purchases, can.organise]);
    const setDraft = (id: number, patch: Partial<Draft>): void => setDrafts((d) => ({ ...d, [id]: { ...(d[id] as Draft), ...patch } }));
    const setDraftItem = (id: number, index: number, patch: Partial<Draft['items'][number]>): void =>
        setDrafts((d) => ({ ...d, [id]: { ...(d[id] as Draft), items: (d[id] as Draft).items.map((it, i) => (i === index ? { ...it, ...patch } : it)) } }));
    const draftTotal = (id: number): number => (drafts[id]?.items ?? []).reduce((sum, i) => sum + toSen(i.price_rm) * Number(i.quantity || 0), 0);
    const [submitting, setSubmitting] = useState<number | null>(null);
    function submitDraft(id: number): void {
        const d = drafts[id];
        if (!d) return;
        setSubmitting(id);
        router.put(
            `/purchases/${id}`,
            {
                items: d.items.filter((i) => i.name.trim()).map((i) => ({ name: i.name.trim(), quantity: Number(i.quantity), unit_sen: toSen(i.price_rm) })),
                shop: d.shop || null,
                purchased_on: d.purchased_on || null,
                budget_line: d.budget_line || null,
                paid_with: d.paid_with,
                verifier_id: d.paid_with === 'own' ? d.verifier_id : null,
            },
            { preserveScroll: true, onFinish: () => setSubmitting(null) },
        );
    }
    const verify = (p: Purchase, settle_when: 'now' | 'after_event'): void => router.post(`/purchases/${p.id}/verify`, { approve: true, settle_when }, { preserveScroll: true });
    const reject = (p: Purchase): void => {
        const note = prompt('Why is this purchase not accepted? (sent to the buyer)');
        if (note) router.post(`/purchases/${p.id}/verify`, { approve: false, note }, { preserveScroll: true });
    };

    // Pay back: cash (photo of the buyer's own QR with the cash) or transfer (upload the transfer receipt).
    const [paying, setPaying] = useState<Purchase | null>(null);
    const [payMethod, setPayMethod] = useState<'cash' | 'online' | null>(null);
    const payForm = useForm({ photo: null as File | null, receipt: null as File | null });
    const sendPayout = (path: 'pay-cash' | 'pay-online', data: { photo?: File; receipt?: File }): void => {
        if (!paying) return;
        payForm.transform(() => data);
        payForm.post(`/purchases/${paying.id}/${path}`, { preserveScroll: true, forceFormData: true, onSuccess: () => setPaying(null), onFinish: () => payForm.reset() });
    };
    const onCashPhoto = (blob: Blob): void => sendPayout('pay-cash', { photo: new File([blob], 'payback.jpg', { type: 'image/jpeg' }) });
    const onTransfer = (e: ChangeEvent<HTMLInputElement>): void => {
        const file = e.target.files?.[0];
        if (file) sendPayout('pay-online', { receipt: file });
    };
    const post = (p: Purchase, action: string): void => router.post(`/purchases/${p.id}/${action}`, {}, { preserveScroll: true });
    const totals = {
        budget: budget.reduce((s, l) => s + l.budget_sen, 0),
        spent: budget.reduce((s, l) => s + l.spent_sen, 0),
        owed: purchases.filter((p) => ['pending', 'owed', 'paying'].includes(p.status)).reduce((s, p) => s + p.total_sen, 0),
    };
    const paidWithOptions = can.organise
        ? [
              { value: 'club', label: 'Club money' },
              { value: 'own', label: 'My own money (claim back)' },
          ]
        : [{ value: 'own', label: 'My own money (claim back)' }];

    return (
        <AppLayout wide>
            <Head title={`Purchases · ${event.name}`} />
            <div className="grid gap-6">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <Link href={`/events/${event.slug}`} className="text-sm text-primary">
                            ← {event.name}
                        </Link>
                        <h1 className="text-2xl font-bold">Purchases &amp; claims</h1>
                        <p className="text-sm text-ink-muted">Photograph every receipt at the till. Items and prices are read for you; claims are verified and paid back here.</p>
                    </div>
                    <label className="relative inline-flex cursor-pointer items-center gap-2 overflow-hidden rounded-control bg-ink px-5 py-3 text-sm font-semibold text-white shadow-pop transition hover:-translate-y-0.5 active:scale-[0.98]">
                        <span className="absolute inset-0 animate-shimmer" aria-hidden="true" />
                        <Icon name="camera" size={18} />
                        {snapping ? 'Uploading…' : 'Snap a receipt'}
                        <input type="file" accept="image/*,application/pdf" capture="environment" className="sr-only" disabled={snapping} onChange={snap} />
                    </label>
                </header>
                <section className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-card border border-line bg-surface p-4 shadow-card">
                        <p className="text-xs text-ink-muted">Approved budget</p>
                        <p className="text-xl font-bold tabular-nums">{rm(totals.budget)}</p>
                    </div>
                    <div className="rounded-card border border-line bg-surface p-4 shadow-card">
                        <p className="text-xs text-ink-muted">Spent and recorded</p>
                        <p className={`text-xl font-bold tabular-nums ${totals.spent > totals.budget ? 'text-danger' : 'text-success'}`}>{rm(totals.spent)}</p>
                    </div>
                    <div className="rounded-card border border-line bg-surface p-4 shadow-card">
                        <p className="text-xs text-ink-muted">Claims in progress</p>
                        <p className="text-xl font-bold tabular-nums text-warning">{rm(totals.owed)}</p>
                    </div>
                </section>
                {budget.length > 0 && (
                    <section className="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card">
                        <h2 className="font-semibold">Budget against spending</h2>
                        {budget.map((l) => (
                            <div key={l.description} className="grid gap-1 text-sm">
                                <div className="flex justify-between gap-3">
                                    <span>{l.description}</span>
                                    <span className={`tabular-nums ${l.spent_sen > l.budget_sen ? 'font-semibold text-danger' : 'text-ink-muted'}`}>
                                        {rm(l.spent_sen)} / {rm(l.budget_sen)}
                                    </span>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-surface-muted">
                                    <div className={`h-full rounded-full transition-[width] duration-700 ease-out ${l.spent_sen > l.budget_sen ? 'bg-danger' : 'bg-gradient-to-r from-primary to-primary'}`} style={{ width: barWidth(l) }} />
                                </div>
                            </div>
                        ))}
                    </section>
                )}
                <section className="relative grid gap-4">
                    {purchases.map((p) => {
                        const d = drafts[p.id];
                        return (
                            <article key={p.id} className="fade-up grid gap-4 rounded-card border border-line bg-surface p-4 shadow-card md:grid-cols-[10rem_1fr]">
                                <a href={p.receipt_url} target="_blank" rel="noopener" className="block h-40 overflow-hidden rounded-control border border-line bg-surface-muted md:h-auto">
                                    <img src={p.receipt_url} alt="Receipt" className="size-full object-cover" />
                                </a>
                                <div className="grid content-start gap-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-sm">
                                            <strong>{p.buyer.name}</strong>{' '}
                                            <span className="text-ink-muted">
                                                · {p.paid_with === 'club' ? 'club money' : 'own money'}
                                                {p.shop && ` · ${p.shop}`} · {p.at}
                                            </span>
                                        </p>
                                        <Badge tone={tone(p)}>{statusText(p)}</Badge>
                                    </div>
                                    {p.status === 'reading' ? (
                                        <div className="grid gap-2">
                                            {[1, 2, 3].map((n) => (
                                                <div key={n} className="h-4 animate-pulse rounded bg-surface-muted" style={{ width: `${90 - n * 15}%` }} />
                                            ))}
                                        </div>
                                    ) : p.status === 'draft' && p.mine && d ? (
                                        <form
                                            className="grid gap-3"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                submitDraft(p.id);
                                            }}
                                        >
                                            <p className="text-xs text-ink-muted">
                                                {p.items.length ? `Read from the receipt${p.ai_confidence !== null ? ` (${Math.round(p.ai_confidence * 100)}% sure)` : ''}. Fix anything that is wrong.` : 'Could not read the receipt. Type the items in.'}
                                            </p>
                                            <div className="grid gap-2">
                                                {d.items.map((item, i) => (
                                                    <div key={i} className="grid grid-cols-[1fr_4.5rem_6rem_auto] items-end gap-2">
                                                        <Input value={item.name} onChange={(v) => setDraftItem(p.id, i, { name: v })} label={i === 0 ? 'Item' : undefined} placeholder="Item" />
                                                        <Input value={item.quantity} onChange={(v) => setDraftItem(p.id, i, { quantity: v })} type="number" label={i === 0 ? 'Qty' : undefined} />
                                                        <Input value={item.price_rm} onChange={(v) => setDraftItem(p.id, i, { price_rm: v })} type="number" label={i === 0 ? 'Price (RM)' : undefined} />
                                                        <button type="button" className="h-10 px-1 text-ink-muted hover:text-danger" aria-label="Remove item" onClick={() => setDraft(p.id, { items: d.items.filter((_, x) => x !== i) })}>
                                                            <Icon name="x" size={16} />
                                                        </button>
                                                    </div>
                                                ))}
                                                <button type="button" className="justify-self-start text-sm font-medium text-primary" onClick={() => setDraft(p.id, { items: [...d.items, { name: '', quantity: '1', price_rm: '0.00' }] })}>
                                                    ＋ Add item
                                                </button>
                                            </div>
                                            <div className="grid gap-3 sm:grid-cols-3">
                                                <Input value={d.shop} onChange={(v) => setDraft(p.id, { shop: v })} label="Shop" />
                                                <Input value={d.purchased_on} onChange={(v) => setDraft(p.id, { purchased_on: v })} type="date" label="Date" />
                                                <Select value={d.budget_line} onChange={(v) => setDraft(p.id, { budget_line: v })} label="Budget line" options={[...budgetLines.map((l) => ({ value: l, label: l })), { value: '', label: 'Not in the budget' }]} />
                                            </div>
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <Select value={d.paid_with} onChange={(v) => setDraft(p.id, { paid_with: v as 'club' | 'own' })} label="Paid with" options={paidWithOptions} />
                                                {d.paid_with === 'own' && (
                                                    <Select
                                                        value={d.verifier_id}
                                                        onChange={(v) => setDraft(p.id, { verifier_id: Number(v) })}
                                                        label="Verified by (event committee)"
                                                        placeholder="Choose who checks it"
                                                        options={verifiers.map((v) => ({ value: v.id, label: v.name }))}
                                                    />
                                                )}
                                            </div>
                                            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3">
                                                <p className="text-lg font-bold tabular-nums">{rm(draftTotal(p.id))}</p>
                                                <Button type="submit" loading={submitting === p.id} disabled={draftTotal(p.id) <= 0 || (d.paid_with === 'own' && !d.verifier_id)}>
                                                    {d.paid_with === 'club' ? 'Record purchase' : 'Send for verification'}
                                                </Button>
                                            </div>
                                        </form>
                                    ) : (
                                        <>
                                            <ul className="grid gap-1 text-sm">
                                                {p.items.map((item, i) => (
                                                    <li key={i} className="flex justify-between gap-3">
                                                        <span>
                                                            {item.name}
                                                            {item.quantity > 1 && <span className="text-ink-muted"> × {item.quantity}</span>}
                                                        </span>
                                                        <span className="tabular-nums">{rm(item.total_sen ?? item.unit_sen * item.quantity)}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-line pt-2 text-sm">
                                                <span className="text-ink-muted">
                                                    {p.budget_line ? `Budget: ${p.budget_line}` : 'Not in the budget'}
                                                    {p.payer && ` · paid back by ${p.payer}`}
                                                </span>
                                                <strong className="tabular-nums">{rm(p.total_sen)}</strong>
                                            </div>
                                            {p.review_note && <p className="rounded-control bg-warning/10 p-2 text-sm text-warning">{p.review_note}</p>}
                                            {p.payout_url && (
                                                <a href={p.payout_url} target="_blank" rel="noopener" className="text-sm font-medium text-primary">
                                                    {p.settle_method === 'cash' ? 'Cash photo' : 'Transfer receipt'}
                                                    {p.payout_payee && ` (to ${p.payout_payee})`}
                                                </a>
                                            )}
                                            {p.to_verify && (
                                                <div className="flex flex-wrap gap-2">
                                                    <Button onClick={() => verify(p, 'now')}>Verify · pay back now</Button>
                                                    <Button variant="secondary" onClick={() => verify(p, 'after_event')}>
                                                        Verify · pay after the event
                                                    </Button>
                                                    <Button variant="ghost" onClick={() => reject(p)}>
                                                        Reject
                                                    </Button>
                                                </div>
                                            )}
                                            {can.organise && !p.mine && p.status === 'owed' && (
                                                <Button
                                                    disabled={p.settle_when === 'after_event' && !event.ended}
                                                    className="justify-self-start"
                                                    onClick={() => {
                                                        setPaying(p);
                                                        setPayMethod(null);
                                                    }}
                                                >
                                                    <Icon name="send" size={16} />
                                                    {p.settle_when === 'after_event' && !event.ended ? 'Pay back after the event' : `Pay back ${rm(p.total_sen)}`}
                                                </Button>
                                            )}
                                            {p.mine && p.status === 'paying' && p.settle_method === 'cash' && (
                                                <Button className="justify-self-start" onClick={() => post(p, 'received')}>
                                                    I got {rm(p.total_sen)} in cash
                                                </Button>
                                            )}
                                            {p.i_paid && p.status === 'paying' && p.payout_check === 'unread' && (
                                                <div className="grid gap-2 rounded-control bg-surface-muted p-3 text-sm">
                                                    <p>
                                                        The transfer receipt could not be read automatically. Check that it went to <strong>{p.buyer.name}</strong> for {rm(p.total_sen)}.
                                                    </p>
                                                    <Button variant="secondary" className="justify-self-start" onClick={() => post(p, 'confirm-transfer')}>
                                                        Yes, it went to {p.buyer.name}
                                                    </Button>
                                                </div>
                                            )}
                                        </>
                                    )}
                                </div>
                            </article>
                        );
                    })}
                </section>
                {!purchases.length && <p className="rounded-card border border-dashed border-line p-8 text-center text-sm text-ink-muted">No purchases yet. Photograph the first receipt when you buy something for the event.</p>}
            </div>
            <Modal open={paying !== null} onClose={() => setPaying(null)} title={paying ? `Pay back ${paying.buyer.name} · ${rm(paying.total_sen)}` : ''}>
                {paying && (
                    <div className="grid gap-4">
                        {!payMethod ? (
                            <div className="grid grid-cols-2 gap-3">
                                <button type="button" className="grid justify-items-center gap-2 rounded-card border border-line p-4 text-sm transition hover:-translate-y-0.5 hover:shadow-pop" onClick={() => setPayMethod('cash')}>
                                    <Icon name="qr" size={28} />
                                    <strong>Cash</strong>
                                    <span className="text-center text-xs text-ink-muted">Photo of their QR with the cash</span>
                                </button>
                                <button type="button" className="grid justify-items-center gap-2 rounded-card border border-line p-4 text-sm transition hover:-translate-y-0.5 hover:shadow-pop" onClick={() => setPayMethod('online')}>
                                    <Icon name="send" size={28} />
                                    <strong>Transfer</strong>
                                    <span className="text-center text-xs text-ink-muted">TNG or bank; upload the receipt</span>
                                </button>
                            </div>
                        ) : payMethod === 'cash' ? (
                            <>
                                <p className="text-sm">Ask {paying.buyer.name} to open their profile QR. Hold the cash next to their phone.</p>
                                {paying.buyer.qr_token && <LiveQrCapture expectToken={paying.buyer.qr_token} personName={paying.buyer.name} onCapture={onCashPhoto} />}
                                {payForm.processing && <p className="text-center text-sm text-ink-muted">Checking the photo…</p>}
                            </>
                        ) : (
                            <>
                                <p className="text-sm">
                                    Transfer {rm(paying.total_sen)} to <strong>{paying.buyer.name}</strong>, then upload the transfer receipt. The name on it must be {paying.buyer.name}.
                                </p>
                                <label className="grid cursor-pointer justify-items-center gap-2 rounded-card border-2 border-dashed border-line p-6 text-sm text-ink-muted transition hover:border-primary">
                                    <Icon name="receipt" size={28} />
                                    {payForm.processing ? 'Uploading…' : 'Choose the transfer receipt'}
                                    <input type="file" accept="image/*,application/pdf" className="sr-only" onChange={onTransfer} />
                                </label>
                            </>
                        )}
                        {(payForm.errors.photo || payForm.errors.receipt) && <p className="text-sm text-danger">{payForm.errors.photo ?? payForm.errors.receipt}</p>}
                        {payMethod && (
                            <button type="button" className="justify-self-start text-sm text-ink-muted underline" onClick={() => setPayMethod(null)}>
                                Back
                            </button>
                        )}
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
