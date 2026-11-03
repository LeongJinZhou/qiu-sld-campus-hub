import PayPanel from '@/Components/payments/PayPanel';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Icon from '@/Components/ui/Icon';
import { useLeaveReminder } from '@/hooks/useLeaveReminder';
import AppLayout from '@/Layouts/AppLayout';
import { dateTime, rm } from '@/lib/format';
import { Head, Link, usePoll } from '@inertiajs/react';

interface PayProps {
    event: { slug: string; name: string; fee_sen: number; payment_url: string | null; payment_account_name: string | null; is_outdoor: boolean; club: { slug: string; name: string }; starts_at: string };
    registration: { status: string; paid_at: string | null; ticket_url: string | null };
    qrCodes: { kind: 'generated' | 'uploaded'; label: string; svg?: string; url?: string }[];
    payment: { order_id: string | null; method: string; status: string; flags: string[]; review_note: string | null; handover_id: number | null } | null;
    committee: { id: number; name: string; position: string; photo_url: string | null }[];
}

export default function Pay({ event, registration, qrCodes, payment, committee }: PayProps) {
    const paid = registration.status === 'registered';
    // While a receipt is being read, refresh until it's done.
    usePoll(3000, { only: ['payment', 'registration'] }, { autoStart: payment?.status === 'reading' });
    useLeaveReminder(`Paying for ${event.name}`, !paid);

    return (
        <AppLayout>
            <Head title={`Pay · ${event.name}`} />
            <div className="mx-auto grid max-w-xl gap-5">
                <header className="grid gap-1">
                    <Link href={`/events/${event.slug}`} className="text-sm text-brand-blue">
                        ← {event.name}
                    </Link>
                    <h1 className="text-xl font-bold">Event registration</h1>
                    <p className="flex items-center gap-2 text-sm text-ink-muted">
                        <Icon name="calendar" size={16} /> {dateTime(event.starts_at)} · {event.club.name}
                    </p>
                </header>
                {paid ? (
                    <Card>
                        <div className="grid justify-items-center gap-2 py-4 text-center">
                            <span className="grid size-14 animate-tracker-pop place-items-center rounded-full bg-success text-white">
                                <Icon name="check" size={28} strokeWidth={3} />
                            </span>
                            <p className="text-lg font-semibold">You're registered</p>
                            <p className="text-sm text-ink-muted">
                                Paid {registration.paid_at}
                                {payment?.order_id && ` · ref ${payment.order_id}`}
                            </p>
                            {registration.ticket_url && (
                                <Link href={registration.ticket_url}>
                                    <Button>
                                        <Icon name="ticket" size={18} /> View my ticket
                                    </Button>
                                </Link>
                            )}
                            {event.is_outdoor && (
                                <Link href={`/events/${event.slug}/indemnity`}>
                                    <Button variant="secondary">Sign the indemnity form</Button>
                                </Link>
                            )}
                        </div>
                    </Card>
                ) : (
                    <Card title={`Pay ${rm(event.fee_sen)} to confirm your place`}>
                        <PayPanel
                            amountSen={event.fee_sen}
                            accountName={event.payment_account_name}
                            paymentUrl={event.payment_url}
                            qrCodes={qrCodes}
                            payment={payment}
                            committee={committee}
                            receiptUrl={`/events/${event.slug}/pay/receipt`}
                            cashUrl={`/events/${event.slug}/pay/cash`}
                            outcome="Your registration is confirmed at once"
                        />
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
