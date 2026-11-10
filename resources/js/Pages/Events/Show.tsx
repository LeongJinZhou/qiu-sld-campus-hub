import IndemnityPrompt from '@/Components/events/IndemnityPrompt';
import ApprovalTracker from '@/Components/forms/ApprovalTracker';
import Avatar from '@/Components/ui/Avatar';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import FileUpload from '@/Components/ui/FileUpload';
import Icon from '@/Components/ui/Icon';
import Input from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { dateTime, rm } from '@/lib/format';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

interface EventShowProps {
    event: {
        slug: string;
        name: string;
        summary: string | null;
        venue: string | null;
        mode: string;
        is_outdoor: boolean;
        cca_participant: number;
        cca_committee: number;
        registration_open: boolean;
        members_only: boolean;
        attendance_open: boolean;
        status: string;
        starts_at: string;
        ends_at: string;
        poster_url: string | null;
        club: { slug: string; name: string };
        proposal_id: number | null;
    };
    committee: { position: string; name: string; photo_url: string | null; responsibility: string | null }[];
    approval: { progress: ApprovalProgress; steps: ApprovalStep[] } | null;
    registered: boolean;
    registrationStatus: string | null;
    ticketUrl: string | null;
    mayJoin: boolean;
    payment: { fee_sen: number; payment_url: string | null; payment_account_name: string | null; qr: { path: string; url: string }[] };
    indemnity: { signed: boolean; closes_at: string; closed: boolean } | null;
    registrations: number;
    can: { organise: boolean; update: boolean; checkIn: boolean };
}

function LinkButton({ href, children, variant = 'secondary' }: { href: string; children: ReactNode; variant?: 'primary' | 'secondary' }) {
    return (
        <Link href={href}>
            <Button block variant={variant}>
                {children}
            </Button>
        </Link>
    );
}

export default function Show({ event, committee, approval, registered, registrationStatus, ticketUrl, mayJoin, payment, indemnity, registrations, can }: EventShowProps) {
    // Committee: fee, account, link and up to two own payment QR codes.
    const pay = useForm({ fee_rm: String(payment.fee_sen / 100), payment_account_name: payment.payment_account_name ?? '', payment_url: payment.payment_url ?? '', qr: [] as File[], remove_qr: [] as string[] });
    const toggleRemove = (path: string): void => pay.setData('remove_qr', pay.data.remove_qr.includes(path) ? pay.data.remove_qr.filter((p) => p !== path) : [...pay.data.remove_qr, path]);
    const savePayment = (e: FormEvent): void => {
        e.preventDefault();
        pay.transform((d) => ({ ...d, _method: 'put' }));
        pay.post(`/events/${event.slug}/payment-settings`, { preserveScroll: true, forceFormData: true, onSuccess: () => pay.reset('qr', 'remove_qr') });
    };
    const register = (): void => router.post(`/events/${event.slug}/register`, {}, { preserveScroll: true });
    const unregister = (): void => router.delete(`/events/${event.slug}/register`, { preserveScroll: true });
    const audience = [
        { v: false, l: 'Anyone at QIU', d: 'Members and non-members can register.' },
        { v: true, l: 'Club members only', d: `Only ${event.club.name} members (and the committee). Others are asked to join first.` },
    ];

    return (
        <AppLayout>
            <Head title={event.name} />
            <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div className="grid content-start gap-6">
                    {event.poster_url && <img src={event.poster_url} alt={`${event.name} poster`} className="w-full rounded-card border border-line" />}
                    <header className="grid gap-2">
                        <Link href={`/clubs/${event.club.slug}`} className="text-sm font-medium text-primary">
                            {event.club.name}
                        </Link>
                        <h1 className="text-2xl font-bold">{event.name}</h1>
                        <div className="flex flex-wrap gap-2">
                            <Badge tone={event.status === 'approved' ? 'success' : 'neutral'}>{event.status.replace('_', ' ')}</Badge>
                            {event.is_outdoor && <Badge tone="warning">Outdoor / off-campus · indemnity required</Badge>}
                            <Badge tone="info">{event.cca_participant} CCA</Badge>
                            <Badge tone={event.members_only ? 'warning' : 'success'}>{event.members_only ? `${event.club.name} members only` : 'Open to all QIU students'}</Badge>
                        </div>
                    </header>
                    <dl className="grid gap-1 text-sm">
                        <div className="flex flex-wrap gap-2">
                            <dt className="w-16 text-ink-muted">When</dt>
                            <dd>
                                {dateTime(event.starts_at)} – {dateTime(event.ends_at)}
                            </dd>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <dt className="w-16 text-ink-muted">Where</dt>
                            <dd>
                                {event.venue} <span className="capitalize text-ink-muted">({event.mode})</span>
                            </dd>
                        </div>
                    </dl>
                    {event.summary && <p className="whitespace-pre-line text-sm">{event.summary}</p>}
                    {approval && (
                        <Card title="Proposal approval">
                            <ApprovalTracker progress={approval.progress} steps={approval.steps} />
                        </Card>
                    )}
                    {committee.length > 0 && (
                        <Card title="Event committee">
                            <ul className="grid gap-3 sm:grid-cols-2">
                                {committee.map((m) => (
                                    <li key={m.position} className="flex items-center gap-3">
                                        <Avatar src={m.photo_url} name={m.name} size="sm" />
                                        <span className="grid text-sm">
                                            <strong>{m.name}</strong>
                                            <span className="text-ink-muted">{m.position}</span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}
                </div>
                <aside className="grid content-start gap-3">
                    <Card>
                        <p className="text-sm text-ink-muted">
                            {registrations} registered
                            {!!payment.fee_sen && ` · fee ${rm(payment.fee_sen)}`}
                        </p>
                        {registrationStatus === 'pending_payment' ? (
                            <>
                                <p className="mt-2 text-sm font-medium text-warning">Place reserved — payment pending.</p>
                                <Link href={`/events/${event.slug}/pay`}>
                                    <Button block className="mt-2">
                                        Pay {rm(payment.fee_sen)}
                                    </Button>
                                </Link>
                                <button type="button" className="mt-1 text-xs text-ink-muted underline" onClick={unregister}>
                                    Cancel
                                </button>
                            </>
                        ) : registered ? (
                            <>
                                <p className="mt-2 text-sm font-medium text-success">You are registered.</p>
                                {registrationStatus === 'registered' && indemnity && !indemnity.signed && !indemnity.closed ? (
                                    <div className="mt-2">
                                        <IndemnityPrompt slug={event.slug} name={event.name} closesAt={indemnity.closes_at} />
                                    </div>
                                ) : (
                                    ticketUrl && (
                                        <Link href={ticketUrl}>
                                            <Button block className="mt-2">
                                                View my ticket
                                            </Button>
                                        </Link>
                                    )
                                )}
                                <button type="button" className="mt-1 text-xs text-ink-muted underline" onClick={unregister}>
                                    Cancel registration
                                </button>
                            </>
                        ) : event.registration_open && !mayJoin ? (
                            <>
                                <p className="mt-2 text-sm">
                                    This event is for <strong>{event.club.name}</strong> members only.
                                </p>
                                <Link href={`/clubs/${event.club.slug}/join`}>
                                    <Button block className="mt-2">
                                        Join {event.club.name} to register
                                    </Button>
                                </Link>
                            </>
                        ) : event.registration_open ? (
                            <Button block className="mt-3" onClick={register}>
                                {payment.fee_sen ? `Register · ${rm(payment.fee_sen)}` : 'Register'}
                            </Button>
                        ) : (
                            <p className="mt-2 text-sm text-ink-muted">Registration opens once SLD approves the proposal.</p>
                        )}
                        {indemnity &&
                            (registered || can.organise) &&
                            (indemnity.signed ? (
                                <p className="mt-3 text-sm text-success">✓ Indemnity signed</p>
                            ) : !indemnity.closed ? (
                                <Link href={`/events/${event.slug}/indemnity`}>
                                    <Button variant="secondary" block className="mt-3">
                                        Sign indemnity (closes {indemnity.closes_at})
                                    </Button>
                                </Link>
                            ) : (
                                <p className="mt-3 text-sm text-danger">Indemnity signing has closed.</p>
                            ))}
                    </Card>
                    {can.update && (
                        <Card title="Who can register">
                            <div className="grid gap-2" role="radiogroup" aria-label="Who can register">
                                {audience.map((o) => {
                                    const on = event.members_only === o.v;
                                    return (
                                        <button
                                            key={String(o.v)}
                                            type="button"
                                            role="radio"
                                            aria-checked={on}
                                            className={`grid gap-0.5 rounded-control border p-3 text-left text-sm transition ${on ? 'border-ink bg-surface-muted' : 'border-line hover:bg-surface-muted'}`}
                                            onClick={() => router.put(`/events/${event.slug}/registration-settings`, { members_only: o.v }, { preserveScroll: true })}
                                        >
                                            <span className="flex items-center gap-2 font-semibold">
                                                <span className={`grid size-4 place-items-center rounded-full border-2 ${on ? 'border-ink' : 'border-line'}`}>{on && <span className="size-2 rounded-full bg-ink" />}</span>
                                                {o.l}
                                            </span>
                                            <span className="pl-6 text-xs text-ink-muted">{o.d}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        </Card>
                    )}
                    {can.update && (
                        <Card title="Event payment">
                            <form className="grid gap-3" onSubmit={savePayment}>
                                <Input value={pay.data.fee_rm} onChange={(v) => pay.setData('fee_rm', v)} type="number" label="Fee (RM, 0 = free)" error={pay.errors.fee_rm} />
                                {Number(pay.data.fee_rm) > 0 && (
                                    <>
                                        <Input value={pay.data.payment_account_name} onChange={(v) => pay.setData('payment_account_name', v)} label="Pay to (account name)" error={pay.errors.payment_account_name} />
                                        <Input
                                            value={pay.data.payment_url}
                                            onChange={(v) => pay.setData('payment_url', v)}
                                            label="Payment link"
                                            placeholder="https://…"
                                            hint="A QR code of this link is shown to payers automatically."
                                            error={pay.errors.payment_url}
                                        />
                                        {payment.qr.length > 0 && (
                                            <div className="flex flex-wrap gap-2">
                                                {payment.qr.map((q) => {
                                                    const removing = pay.data.remove_qr.includes(q.path);
                                                    return (
                                                        <span key={q.path} className="relative">
                                                            <img src={q.url} alt="Payment QR" className={`size-20 rounded-control border border-line object-cover ${removing ? 'opacity-30' : ''}`} />
                                                            <button
                                                                type="button"
                                                                className="absolute -right-1 -top-1 grid size-5 place-items-center rounded-full bg-ink text-xs text-white"
                                                                aria-label={removing ? 'Keep this QR' : 'Remove this QR'}
                                                                onClick={() => toggleRemove(q.path)}
                                                            >
                                                                {removing ? '↺' : '×'}
                                                            </button>
                                                        </span>
                                                    );
                                                })}
                                            </div>
                                        )}
                                        {payment.qr.length - pay.data.remove_qr.length < 2 && (
                                            <FileUpload files={pay.data.qr} onChange={(f) => pay.setData('qr', f)} multiple label="Your own payment QR codes (up to 2, optional)" accept="image/png,image/jpeg,image/webp" error={pay.errors.qr} />
                                        )}
                                    </>
                                )}
                                <Button type="submit" loading={pay.processing}>
                                    Save payment settings
                                </Button>
                            </form>
                        </Card>
                    )}
                    {can.checkIn && !can.organise && (
                        <LinkButton href={`/events/${event.slug}/attendance`} variant="primary">
                            <Icon name="scan" size={18} /> Door helper: scan tickets
                        </LinkButton>
                    )}
                    {can.checkIn && event.status === 'approved' && (
                        <LinkButton href={`/events/${event.slug}/purchases`}>
                            <Icon name="receipt" size={18} /> Purchases &amp; claims
                        </LinkButton>
                    )}
                    {can.organise && (
                        <>
                            <LinkButton href={`/events/${event.slug}/attendance`} variant="primary">
                                Door &amp; attendance
                            </LinkButton>
                            {!!payment.fee_sen && <LinkButton href={`/clubs/${event.club.slug}/finance/events/${event.slug}`}>Who paid · money tally</LinkButton>}
                            {event.is_outdoor && <LinkButton href={`/events/${event.slug}/indemnities`}>Indemnity list</LinkButton>}
                            {event.status === 'approved' && <LinkButton href={`/events/${event.slug}/documents`}>Post-event documents</LinkButton>}
                            {event.status === 'approved' && <LinkButton href={`/events/${event.slug}/post-mortem`}>Post mortem (09)</LinkButton>}
                        </>
                    )}
                    {can.update && <LinkButton href={`/events/${event.slug}/poster`}>Poster</LinkButton>}
                    {can.update && event.proposal_id && (
                        <>
                            <LinkButton href={`/events/${event.slug}/proposal`}>Proposal form</LinkButton>
                            <LinkButton href={`/documents/${event.proposal_id}`}>Approval progress</LinkButton>
                        </>
                    )}
                </aside>
            </div>
        </AppLayout>
    );
}
