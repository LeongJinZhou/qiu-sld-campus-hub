import Avatar from '@react/Components/ui/Avatar';
import Button from '@react/Components/ui/Button';
import Icon from '@react/Components/ui/Icon';
import LiveQrCapture from '@react/Components/ui/LiveQrCapture';
import QrCode from '@react/Components/ui/QrCode';
import StepIndicator from '@react/Components/ui/StepIndicator';
import { useLeaveReminder } from '@react/hooks/useLeaveReminder';
import AppLayout from '@react/Layouts/AppLayout';
import { rm } from '@/lib/format';
import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

interface HandoverProps {
    side: 'student' | 'committee';
    handover: {
        id: number;
        status: string;
        club: string;
        purpose: string;
        order_id: string | null;
        club_slug: string;
        amount_sen: number;
        student: { name: string; photo_url: string | null };
        committee: { name: string; photo_url: string | null };
        student_ready: boolean;
        committee_ready: boolean;
        student_done: boolean;
        committee_done: boolean;
        expires_at: string;
        cash_check: 'ai' | 'committee' | 'needs_confirmation' | null;
        photo_url: string | null;
    };
    qrSvg: string | null;
    expectToken: string | null;
}
const ORDER = ['requested', 'accepted', 'both_ready', 'photo_taken', 'completed'];

function Steps({ items }: { items: ReactNode[] }) {
    return (
        <ol className="grid gap-3 text-sm">
            {items.map((text, i) => (
                <li key={i} className="flex gap-3">
                    <span className="grid size-6 shrink-0 place-items-center rounded-full bg-ink text-xs font-bold text-white">{i + 1}</span>
                    <span className="pt-0.5">{text}</span>
                </li>
            ))}
        </ol>
    );
}

/** Cash payment, step by step on both phones: request, accept, ready, QR photo with the cash, done. */
export default function Handover({ side, handover: h, qrSvg, expectToken }: HandoverProps) {
    // Leaving mid-way (home button, app switch) leaves a reminder that brings the person back here.
    useLeaveReminder(`Your cash payment for ${h.club}`, !['completed', 'declined', 'expired'].includes(h.status));
    // Both screens poll so each side sees the other's taps within seconds.
    usePoll(2000, { only: ['handover', 'qrSvg'] });
    const [busy, setBusy] = useState(false);
    const act = (action: string, data: Record<string, unknown> = {}): void => {
        setBusy(true);
        router.post(`/handovers/${h.id}`, { action, ...data } as RequestPayload, { preserveScroll: true, forceFormData: 'photo' in data, onFinish: () => setBusy(false) });
    };
    const onPhoto = (photo: Blob): void => act('photo', { photo: new File([photo], 'handover.jpg', { type: 'image/jpeg' }) });
    // Retake goes back to the QR step on the server, so the student's phone shows their QR again too.
    const retake = (): void => act('retake');

    const amount = rm(h.amount_sen);
    const me = side === 'student' ? { ready: h.student_ready, done: h.student_done } : { ready: h.committee_ready, done: h.committee_done };
    const other = side === 'student' ? h.committee : h.student;
    const cashOk = h.cash_check === 'ai' || h.cash_check === 'committee';
    const at = ORDER.indexOf(['student_ready', 'committee_ready'].includes(h.status) ? 'accepted' : h.status);
    const steps = ['Request', 'Accept', 'Ready', 'Photo', 'Done'].map((label, i) => ({
        label,
        state: h.status === 'completed' || i < at ? ('done' as const) : i === at ? ('current' as const) : ('pending' as const),
    }));
    const waiting = (text: string) => <p className="text-center text-sm text-ink-muted">{text}</p>;
    const photo = h.photo_url && <img src={h.photo_url} alt="Cash payment photo" className="w-full rounded-card border border-line" />;

    let body: ReactNode;
    if (h.status === 'requested') {
        body =
            side === 'committee' ? (
                <>
                    <p className="text-center font-medium">
                        {h.student.name} wants to pay you {amount} in cash.
                    </p>
                    <div className="grid grid-cols-2 gap-2">
                        <Button variant="secondary" loading={busy} onClick={() => act('decline')}>
                            Decline
                        </Button>
                        <Button loading={busy} onClick={() => act('accept')}>
                            Accept
                        </Button>
                    </div>
                </>
            ) : (
                <p className="text-center text-ink-muted">Waiting for {other.name} to accept your request…</p>
            );
    } else if (['accepted', 'student_ready', 'committee_ready'].includes(h.status)) {
        body = (
            <>
                <h2 className="font-semibold">{side === 'student' ? 'How to pay in cash' : 'How to collect the cash'}</h2>
                {side === 'student' ? (
                    <Steps
                        items={[
                            <>
                                Bring exactly <strong>{amount}</strong> in cash and meet {other.name} in person.
                            </>,
                            <>
                                Tap <strong>Ready to pay</strong>. Your personal QR code appears on this screen.
                            </>,
                            <>Hold your phone next to the cash. {other.name}'s camera takes the photo by itself.</>,
                            <>
                                Hand over the cash, then both tap <strong>Transaction done</strong>.
                            </>,
                        ]}
                    />
                ) : (
                    <Steps
                        items={[
                            <>
                                Meet {other.name} in person. They pay <strong>{amount}</strong>.
                            </>,
                            <>
                                Tap <strong>Ready to receive</strong>. Your camera opens.
                            </>,
                            <>
                                Point it at the student's QR code with the cash in the same picture. It captures automatically when it finds <strong>their</strong> QR.
                            </>,
                            <>
                                Check the photo (retake if needed), take the cash, then both tap <strong>Transaction done</strong>.
                            </>,
                        ]}
                    />
                )}
                {!me.ready ? (
                    <Button size="lg" block loading={busy} onClick={() => act('ready')}>
                        {side === 'student' ? 'Ready to pay' : 'Ready to receive'}
                    </Button>
                ) : (
                    <p className="flex items-center justify-center gap-2 text-sm text-ink-muted">
                        <span className="size-2 animate-ping rounded-full bg-brand-magenta" /> Waiting for {other.name} to tap ready…
                    </p>
                )}
            </>
        );
    } else if (h.status === 'both_ready' || h.status === 'photo_taken') {
        if (side === 'student') {
            body =
                h.status === 'both_ready' ? (
                    <>
                        <p className="text-center font-medium">
                            Show this QR to {other.name}, next to your {amount} cash.
                        </p>
                        {qrSvg && <QrCode svg={qrSvg} label="Your personal QR — the same one as on your profile" />}
                        <p className="text-center text-xs text-ink-muted">Keep the screen bright and still. The photo is taken automatically.</p>
                    </>
                ) : (
                    <>
                        {photo}
                        <p className="text-center text-sm">{cashOk ? 'Photo checked.' : `${other.name} is checking the photo…`} Hand over the cash, then confirm.</p>
                        {!me.done ? (
                            <Button size="lg" block disabled={!cashOk} loading={busy} onClick={() => act('done')}>
                                Transaction done
                            </Button>
                        ) : (
                            waiting(`Waiting for ${other.name} to confirm…`)
                        )}
                    </>
                );
        } else if (h.status === 'both_ready' && expectToken) {
            body = <LiveQrCapture expectToken={expectToken} personName={other.name} onCapture={onPhoto} />;
        } else {
            body = (
                <>
                    {photo}
                    <ul className="grid gap-1 text-sm">
                        <li className="flex items-center gap-2 text-success">
                            <Icon name="check" size={16} /> {other.name}'s QR code matches
                        </li>
                        {h.cash_check === 'ai' && (
                            <li className="flex items-center gap-2 text-success">
                                <Icon name="check" size={16} /> Cash detected in the photo
                            </li>
                        )}
                        {h.cash_check === 'committee' && (
                            <li className="flex items-center gap-2 text-success">
                                <Icon name="check" size={16} /> You confirmed the cash
                            </li>
                        )}
                    </ul>
                    {h.cash_check === 'needs_confirmation' ? (
                        <div className="grid gap-2 rounded-control bg-warning/10 p-3 text-sm">
                            <p className="font-medium">Is {amount} in cash clearly visible next to the QR?</p>
                            <div className="grid grid-cols-2 gap-2">
                                <Button variant="secondary" loading={busy} onClick={retake}>
                                    Retake
                                </Button>
                                <Button loading={busy} onClick={() => act('confirm_cash')}>
                                    Cash is correct
                                </Button>
                            </div>
                        </div>
                    ) : !me.done ? (
                        <>
                            <Button size="lg" block loading={busy} onClick={() => act('done')}>
                                Transaction done
                            </Button>
                            <button type="button" className="justify-self-center text-sm text-ink-muted underline" onClick={retake}>
                                Retake photo
                            </button>
                        </>
                    ) : (
                        waiting(`Waiting for ${other.name} to confirm…`)
                    )}
                </>
            );
        }
    } else if (h.status === 'completed') {
        body = (
            <>
                <span className="mx-auto grid size-16 animate-tracker-pop place-items-center rounded-full bg-success text-white">
                    <Icon name="check" size={32} strokeWidth={3} />
                </span>
                <p className="text-center text-lg font-semibold">Payment complete</p>
                <dl className="grid grid-cols-[8rem_1fr] gap-y-1 text-sm">
                    <dt className="text-ink-muted">Amount</dt>
                    <dd className="font-medium">{amount} (cash)</dd>
                    <dt className="text-ink-muted">Paid by</dt>
                    <dd>{h.student.name}</dd>
                    <dt className="text-ink-muted">Received by</dt>
                    <dd>{h.committee.name}</dd>
                    <dt className="text-ink-muted">For</dt>
                    <dd>
                        {h.purpose} · {h.club}
                    </dd>
                    {h.order_id && (
                        <>
                            <dt className="text-ink-muted">Reference</dt>
                            <dd className="font-mono text-xs">{h.order_id}</dd>
                        </>
                    )}
                </dl>
                {h.photo_url && (
                    <a href={h.photo_url} target="_blank" rel="noopener" className="text-center text-sm font-medium text-brand-blue">
                        View the payment photo
                    </a>
                )}
                <p className="text-center text-sm text-ink-muted">
                    {h.purpose === 'Membership' ? 'Membership is active' : 'The registration is confirmed'} and the club's finance has recorded it. A receipt was sent to both of you.
                </p>
                <Link href={`/clubs/${h.club_slug}`} className="justify-self-center">
                    <Button variant="secondary">Back to club</Button>
                </Link>
            </>
        );
    } else {
        body = <p className="text-center text-ink-muted">This request was {h.status}.</p>;
    }

    return (
        <AppLayout>
            <Head title="Cash payment" />
            <div className="mx-auto grid max-w-md gap-5">
                <header className="grid gap-1 rounded-card border border-line bg-surface p-5 text-center shadow-card">
                    <p className="text-sm text-ink-muted">
                        Cash payment · {h.purpose} · {h.club}
                    </p>
                    <p className="text-3xl font-bold tabular-nums">{amount}</p>
                    <div className="mt-3 grid grid-cols-[1fr_auto_1fr] items-center gap-2 text-sm">
                        <span className="grid justify-items-center gap-1">
                            <Avatar src={h.student.photo_url} name={h.student.name} size="sm" />
                            <span className="line-clamp-2">{h.student.name}</span>
                            <span className="text-xs text-ink-muted">pays</span>
                        </span>
                        <Icon name="send" size={18} className="text-ink-muted" />
                        <span className="grid justify-items-center gap-1">
                            <Avatar src={h.committee.photo_url} name={h.committee.name} size="sm" />
                            <span className="line-clamp-2">{h.committee.name}</span>
                            <span className="text-xs text-ink-muted">receives</span>
                        </span>
                    </div>
                </header>
                <StepIndicator steps={steps} />
                <section className="grid gap-4 rounded-card border border-line bg-surface p-5 shadow-card">{body}</section>
                <p className="text-center text-xs text-ink-muted">Request expires {h.expires_at}.</p>
            </div>
        </AppLayout>
    );
}
