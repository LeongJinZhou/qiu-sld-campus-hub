import Avatar from '@/Components/ui/Avatar';
import Button from '@/Components/ui/Button';
import FileUpload from '@/Components/ui/FileUpload';
import Icon from '@/Components/ui/Icon';
import QrCode from '@/Components/ui/QrCode';
import { rm } from '@/lib/format';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface PayPanelProps {
    amountSen: number;
    accountName: string | null;
    paymentUrl: string | null;
    qrCodes: { kind: 'generated' | 'uploaded'; label: string; svg?: string; url?: string }[];
    payment: { order_id?: string | null; status: string; flags: string[]; review_note: string | null; handover_id: number | null } | null;
    committee: { id: number; name: string; position: string; photo_url: string | null }[];
    receiptUrl: string;
    cashUrl: string;
    /** What happens when it's paid, e.g. "Your membership starts at once". */
    outcome: string;
}

const FLAG_TEXT: Record<string, string> = {
    amount_mismatch: 'Amount does not match the fee',
    payee_mismatch: 'Payee is not the right account',
    old_receipt: 'Receipt is older than 7 days',
    future_date: 'Receipt date is in the future',
    no_reference: 'No transaction reference found',
    no_date: 'No date found',
    low_confidence: 'Receipt is hard to read',
    unreadable: 'Receipt could not be read automatically',
    duplicate_reference: 'This transaction ID was already used for another payment',
    payer_mismatch: 'Sender name is not yours',
};
const flagText = (f: string): string => FLAG_TEXT[f] ?? f;

/** Pay online (link, payment QR codes, receipt upload) or in cash (QR handover) — club membership and paid events. */
export default function PayPanel({ amountSen, accountName, paymentUrl, qrCodes, payment, committee, receiptUrl, cashUrl, outcome }: PayPanelProps) {
    const [method, setMethod] = useState<'online' | 'cash'>('online');
    const receipt = useForm({ receipt: [] as File[] });
    const cash = useForm({ committee_user_id: null as number | null });
    const sendReceipt = (): void => {
        receipt.transform((d) => ({ receipt: d.receipt[0] ?? null }));
        receipt.post(receiptUrl, { preserveScroll: true, onSuccess: () => receipt.reset() });
    };

    return (
        <div className="grid gap-4">
            {payment && ['reading', 'pending_review', 'cash_pending'].includes(payment.status) && (
                <div className="grid gap-2 rounded-control bg-surface-muted p-4 text-sm">
                    {payment.status === 'reading' ? (
                        <p className="flex items-center gap-2 font-medium">
                            <span className="size-3 animate-ping rounded-full bg-brand-magenta" /> Checking your receipt…
                        </p>
                    ) : payment.status === 'pending_review' ? (
                        <>
                            <p className="font-medium">Your receipt is with the committee for review.</p>
                            <ul className="list-disc pl-5 text-ink-muted">
                                {payment.flags.map((f) => (
                                    <li key={f}>{flagText(f)}</li>
                                ))}
                            </ul>
                        </>
                    ) : (
                        payment.handover_id && (
                            <>
                                <p className="flex items-center gap-2 font-medium">
                                    <span className="size-2 animate-ping rounded-full bg-brand-magenta" /> Your cash payment is in progress
                                </p>
                                <p className="text-ink-muted">Open it to follow each step with the committee member.</p>
                                <Link href={`/handovers/${payment.handover_id}`}>
                                    <Button block>Continue cash payment</Button>
                                </Link>
                            </>
                        )
                    )}
                    {payment.order_id && <p className="text-xs text-ink-muted">Reference {payment.order_id}</p>}
                </div>
            )}
            {payment?.status === 'rejected' && (
                <p className="rounded-control bg-danger/10 p-3 text-sm text-danger">
                    Previous payment not accepted: {payment.review_note ?? (payment.flags.map(flagText).join(', ') || 'please try again')}
                </p>
            )}

            <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label="How do you want to pay?">
                {(['online', 'cash'] as const).map((m) => (
                    <button
                        key={m}
                        type="button"
                        role="radio"
                        aria-checked={method === m}
                        className={`grid gap-1 rounded-card border-2 p-3 text-left transition ${method === m ? 'border-ink bg-surface-muted' : 'border-line hover:border-ink/30'}`}
                        onClick={() => setMethod(m)}
                    >
                        <span className="flex items-center gap-2 font-semibold">
                            <Icon name={m === 'online' ? 'send' : 'qr'} size={18} />
                            {m === 'online' ? 'Pay online' : 'Pay in cash'}
                        </span>
                        <span className="text-xs text-ink-muted">{m === 'online' ? 'Transfer or scan, then upload the receipt' : 'Hand cash to a committee member'}</span>
                    </button>
                ))}
            </div>

            {method === 'online' ? (
                <div className="grid gap-4">
                    <ol className="grid gap-2 text-sm">
                        <li>
                            1. Pay <strong>{rm(amountSen)}</strong>
                            {accountName && (
                                <>
                                    {' '}
                                    to <strong>{accountName}</strong>
                                </>
                            )}
                            {paymentUrl && (
                                <>
                                    {' '}
                                    using the{' '}
                                    <a href={paymentUrl} target="_blank" rel="noopener" className="font-medium text-brand-blue">
                                        payment link ↗
                                    </a>
                                </>
                            )}
                            {qrCodes.length > 0 && ' or scan a QR code below'}.
                        </li>
                        <li>2. Upload the receipt. It's checked automatically: amount, date (within 7 days), reference and payee.</li>
                    </ol>
                    {qrCodes.length > 0 && (
                        <div className={`grid gap-3 ${qrCodes.length > 1 ? 'sm:grid-cols-2' : ''}`}>
                            {qrCodes.map((q, i) => (
                                <figure key={i} className="grid justify-items-center gap-2 rounded-card border border-line p-3">
                                    {q.svg ? (
                                        <QrCode svg={q.svg} label={q.label} />
                                    ) : (
                                        <>
                                            <img src={q.url} alt={q.label} className="aspect-square w-full max-w-56 rounded-control object-contain" />
                                            <figcaption className="text-xs text-ink-muted">{q.label}</figcaption>
                                        </>
                                    )}
                                </figure>
                            ))}
                        </div>
                    )}
                    <FileUpload files={receipt.data.receipt} onChange={(f) => receipt.setData('receipt', f)} label="Payment receipt" accept="image/jpeg,image/png,image/webp,application/pdf" error={receipt.errors.receipt} />
                    <Button disabled={!receipt.data.receipt.length} loading={receipt.processing} onClick={sendReceipt}>
                        Upload receipt
                    </Button>
                </div>
            ) : (
                <div className="grid gap-4">
                    <div className="grid gap-2 rounded-control bg-surface-muted p-4 text-sm">
                        <p className="font-semibold">How paying in cash works</p>
                        <ol className="grid gap-1.5 text-ink-muted">
                            <li>1. Choose who you'll pay below. They accept on their phone.</li>
                            <li>
                                2. Meet them with exactly <strong className="text-ink">{rm(amountSen)}</strong>. You both tap <strong className="text-ink">Ready</strong>.
                            </li>
                            <li>3. Your personal QR code appears. Hold it next to the cash; their camera takes the photo automatically.</li>
                            <li>
                                4. Hand over the cash and both tap <strong className="text-ink">Transaction done</strong>. {outcome}, and the club records it.
                            </li>
                        </ol>
                    </div>
                    <fieldset className="grid gap-2">
                        <legend className="mb-2 text-sm font-medium">Pay to</legend>
                        {committee.map((c) => {
                            const on = cash.data.committee_user_id === c.id;
                            return (
                                <label key={c.id} className={`flex cursor-pointer items-center gap-3 rounded-card border-2 p-3 transition ${on ? 'border-ink bg-surface-muted' : 'border-line hover:border-ink/30'}`}>
                                    <input type="radio" name="committee" checked={on} onChange={() => cash.setData('committee_user_id', c.id)} className="sr-only" />
                                    <Avatar src={c.photo_url} name={c.name} size="sm" />
                                    <span className="grid min-w-0 flex-1 text-sm">
                                        <strong className="truncate">{c.name}</strong>
                                        <span className="text-ink-muted">{c.position}</span>
                                    </span>
                                    <span className={`grid size-5 shrink-0 place-items-center rounded-full border-2 ${on ? 'border-ink bg-ink text-white' : 'border-line'}`}>
                                        {on && <Icon name="check" size={12} strokeWidth={3} />}
                                    </span>
                                </label>
                            );
                        })}
                    </fieldset>
                    <Button size="lg" block disabled={!cash.data.committee_user_id} loading={cash.processing} onClick={() => cash.post(cashUrl)}>
                        Send cash payment request
                    </Button>
                </div>
            )}
        </div>
    );
}
