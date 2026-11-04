import PayPanel from '@/Components/payments/PayPanel';
import Avatar from '@/Components/ui/Avatar';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import Icon from '@/Components/ui/Icon';
import { useLeaveReminder } from '@/hooks/useLeaveReminder';
import AppLayout from '@/Layouts/AppLayout';
import { rm } from '@/lib/format';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import { useState } from 'react';

interface JoinProps {
    club: { slug: string; name: string; logo_url: string | null };
    fee: { amount_sen: number; payment_url: string | null; account_name: string | null } | null;
    drive: { title: string; ends_at: string } | null;
    membership: { status: string; expires_on: string | null; grace_ends_on: string | null } | null;
    payment: { method: string; status: string; flags: string[]; review_note: string | null; handover_id: number | null; handover_status: string | null } | null;
    committee: { id: number; name: string; position: string; photo_url: string | null }[];
    qrCodes: { kind: 'generated' | 'uploaded'; label: string; svg?: string; url?: string }[];
}

export default function Join({ club, fee, drive, membership, payment, committee, qrCodes }: JoinProps) {
    const [acknowledged, setAcknowledged] = useState(false);
    const isPaid = (fee?.amount_sen ?? 0) > 0;
    const awaitingPayment = !!membership && ['pending', 'grace', 'terminated'].includes(membership.status) && isPaid;
    // While the AI reads the receipt, refresh until it finishes.
    usePoll(3000, { only: ['payment', 'membership'] }, { autoStart: payment?.status === 'reading' });
    // Started joining a paid club but left before paying: remind them where they stopped.
    useLeaveReminder(`Paying for ${club.name}`, awaitingPayment);
    const join = (): void => router.post(`/clubs/${club.slug}/join`, { acknowledged }, { preserveScroll: true });
    const member = membership?.status === 'active' || membership?.status === 'alumni';
    const canStart = !membership || membership.status === 'terminated' || (membership.status === 'grace' && !payment);

    return (
        <AppLayout>
            <Head title={`Join ${club.name}`} />
            <div className="mx-auto grid max-w-xl gap-5">
                <header className="flex items-center gap-4">
                    <Avatar src={club.logo_url} name={club.name} size="lg" ring />
                    <div>
                        <p className="text-sm text-ink-muted">Membership</p>
                        <h1 className="text-xl font-bold">{club.name}</h1>
                        <Link href={`/clubs/${club.slug}`} className="text-sm text-primary">
                            Back to club
                        </Link>
                    </div>
                </header>
                {drive && (
                    <p className="flex items-center gap-2 rounded-card bg-gradient-to-r from-gold/30 to-primary-strong/20 px-4 py-3 text-sm font-medium">
                        <Icon name="sparkles" size={18} /> {drive.title} — membership drive is on now!
                    </p>
                )}
                {member ? (
                    <Card>
                        <div className="grid justify-items-center gap-2 py-4 text-center">
                            <span className="grid size-14 animate-tracker-pop place-items-center rounded-full bg-success text-white">
                                <Icon name="check" size={28} strokeWidth={3} />
                            </span>
                            <p className="text-lg font-semibold">You're a {membership.status === 'alumni' ? 'alumni member' : 'member'}</p>
                            {membership.expires_on && membership.status === 'active' && <p className="text-sm text-ink-muted">Active until {membership.expires_on}</p>}
                        </div>
                    </Card>
                ) : (
                    <>
                        {!fee ? (
                            <Card>
                                <p className="text-sm text-ink-muted">Joining opens once SLD approves this club's fee for the session.</p>
                            </Card>
                        ) : (
                            canStart && (
                                <Card title={membership?.status === 'grace' ? 'Renew your membership' : 'Join'}>
                                    <div className="grid gap-4">
                                        <p className="text-3xl font-bold">
                                            {isPaid ? rm(fee.amount_sen) : 'Free'} <span className="text-sm font-normal text-ink-muted">· 365 days</span>
                                        </p>
                                        {membership?.status === 'grace' && <p className="text-sm text-warning">Your membership expired. Renew by {membership.grace_ends_on}.</p>}
                                        {isPaid && (
                                            <div className="rounded-control border border-warning/40 bg-warning/5 p-3 text-sm">
                                                <Checkbox checked={acknowledged} onChange={setAcknowledged} label="I understand the fee is non-refundable and members cannot leave the club." />
                                            </div>
                                        )}
                                        <Button size="lg" disabled={isPaid && !acknowledged} onClick={join}>
                                            {isPaid ? 'Continue to payment' : 'Join now'}
                                        </Button>
                                    </div>
                                </Card>
                            )
                        )}
                        {awaitingPayment && fee && (
                            <Card title={`Pay ${rm(fee.amount_sen)}`}>
                                <PayPanel
                                    amountSen={fee.amount_sen}
                                    accountName={fee.account_name}
                                    paymentUrl={fee.payment_url}
                                    qrCodes={qrCodes}
                                    payment={payment}
                                    committee={committee}
                                    receiptUrl={`/clubs/${club.slug}/join/receipt`}
                                    cashUrl={`/clubs/${club.slug}/join/cash`}
                                    outcome="Your membership starts at once"
                                />
                            </Card>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
