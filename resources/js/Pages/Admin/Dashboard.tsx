import Badge from '@/Components/ui/Badge';
import Card from '@/Components/ui/Card';
import PortalLayout from '@/Layouts/PortalLayout';
import { rm } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';

interface DashboardProps {
    session: string;
    clubs_by_status: { active: number; at_risk: number; inactive: number };
    approvals: { role: string; waiting: number; overdue: number }[];
    overdue: { approvals: number; post_event: number; sld_requests: number };
    events_this_month: { slug: string; name: string; club: string; date: string; status: string }[];
    agm: { session: string; clubs: number; scheduled: number; reports: number; packages: number } | null;
    members: Record<'pending' | 'active' | 'grace' | 'terminated' | 'alumni', number>;
    payments: { paid_sen: number; pending_review: number };
    balances: { slug: string; name: string; ready: boolean; balance_sen: number }[];
}
// Status meaning is never colour alone: each segment carries its label and count beside it.
const STATUSES = [
    { key: 'active', label: 'Active', bar: 'bg-success' },
    { key: 'at_risk', label: 'At risk', bar: 'bg-warning' },
    { key: 'inactive', label: 'Inactive', bar: 'bg-danger' },
] as const;
const pct = (n: number, of: number): string => `${of ? Math.round((n / of) * 100) : 0}%`;

export default function Dashboard({ session, clubs_by_status, approvals, overdue, events_this_month, agm, members, payments, balances }: DashboardProps) {
    const totalClubs = clubs_by_status.active + clubs_by_status.at_risk + clubs_by_status.inactive;
    const maxWaiting = Math.max(1, ...approvals.map((a) => a.waiting));
    const tile = 'rounded-card border border-line bg-surface p-4 shadow-card';

    return (
        <PortalLayout title={`Dashboard · ${session}`}>
            <Head title="SLD dashboard" />
            <div className="grid gap-6">
                <section className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div className={tile}>
                        <p className="text-xs text-ink-muted">Active members</p>
                        <p className="text-3xl font-bold tabular-nums">{members.active + members.grace}</p>
                        <p className="text-xs text-ink-muted">
                            {members.grace} in grace · {members.alumni} alumni
                        </p>
                    </div>
                    <div className={tile}>
                        <p className="text-xs text-ink-muted">Fees collected this session</p>
                        <p className="text-3xl font-bold tabular-nums">{rm(payments.paid_sen)}</p>
                        <p className="text-xs text-ink-muted">{payments.pending_review} receipts waiting for Treasurers</p>
                    </div>
                    <div className={tile}>
                        <p className="text-xs text-ink-muted">Overdue approvals</p>
                        <p className={`text-3xl font-bold tabular-nums ${overdue.approvals ? 'text-danger' : ''}`}>{overdue.approvals}</p>
                        <p className="text-xs text-ink-muted">past their 3-working-day step</p>
                    </div>
                    <div className={tile}>
                        <p className="text-xs text-ink-muted">Overdue post-event documents</p>
                        <p className={`text-3xl font-bold tabular-nums ${overdue.post_event ? 'text-danger' : ''}`}>{overdue.post_event}</p>
                        <p className="text-xs text-ink-muted">finance, receipts, post mortem, reel…</p>
                        <Link href="/admin/requests" className={`text-xs ${overdue.sld_requests ? 'text-danger' : 'text-ink-muted'}`}>
                            {overdue.sld_requests} overdue replies to SLD
                        </Link>
                    </div>
                </section>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card title="Clubs by status">
                        <div className="flex h-3 gap-0.5 overflow-hidden rounded-full bg-surface-muted" role="img" aria-label={STATUSES.map((s) => `${s.label} ${clubs_by_status[s.key]}`).join(', ')}>
                            {STATUSES.map((s) => (
                                <div key={s.key} className={s.bar} style={{ width: pct(clubs_by_status[s.key], totalClubs) }} title={`${s.label}: ${clubs_by_status[s.key]}`} />
                            ))}
                        </div>
                        <ul className="mt-3 flex flex-wrap gap-4 text-sm">
                            {STATUSES.map((s) => (
                                <li key={s.key} className="flex items-center gap-2">
                                    <span className={`size-2.5 rounded-full ${s.bar}`} />
                                    {s.label} <strong className="tabular-nums">{clubs_by_status[s.key]}</strong>
                                </li>
                            ))}
                        </ul>
                        <Link href="/admin/clubs" className="mt-3 inline-block text-sm text-primary">
                            See all clubs →
                        </Link>
                    </Card>
                    <Card title="Approvals waiting, by approver">
                        {approvals.length ? (
                            <ul className="grid gap-2 text-sm">
                                {approvals.map((a) => (
                                    <li key={a.role} className="grid grid-cols-[9rem_1fr_auto] items-center gap-3" title={`${a.role}: ${a.waiting} waiting, ${a.overdue} overdue`}>
                                        <span className="truncate">{a.role}</span>
                                        <span className="h-2.5 rounded-r bg-surface-muted">
                                            <span className="block h-full rounded-r bg-primary" style={{ width: pct(a.waiting, maxWaiting) }} />
                                        </span>
                                        <span className="flex items-center gap-2 tabular-nums">
                                            {a.waiting}
                                            {!!a.overdue && <Badge tone="danger">{a.overdue} overdue</Badge>}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-ink-muted">Nothing waiting.</p>
                        )}
                    </Card>
                    <Card title={`AGM ${agm?.session ?? ''}`}>
                        {agm ? (
                            <>
                                <ul className="grid gap-3 text-sm">
                                    {[
                                        { label: 'AGM scheduled', n: agm.scheduled },
                                        { label: 'AGM Report submitted', n: agm.reports },
                                        { label: 'Package submitted', n: agm.packages },
                                    ].map((row) => (
                                        <li key={row.label} className="grid gap-1">
                                            <span className="flex justify-between">
                                                <span>{row.label}</span>
                                                <span className="tabular-nums">
                                                    {row.n} / {agm.clubs}
                                                </span>
                                            </span>
                                            <span className="h-2 rounded-full bg-surface-muted">
                                                <span className="block h-full rounded-full bg-primary" style={{ width: pct(row.n, agm.clubs) }} />
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                                <Link href="/admin/agm" className="mt-3 inline-block text-sm text-primary">
                                    AGM details →
                                </Link>
                            </>
                        ) : (
                            <p className="text-sm text-ink-muted">
                                No AGM period open.{' '}
                                <Link href="/admin/agm" className="text-primary">
                                    Open one
                                </Link>
                            </p>
                        )}
                    </Card>
                    <Card title="Events this month">
                        {events_this_month.length ? (
                            <ul className="divide-y divide-line text-sm">
                                {events_this_month.map((e) => (
                                    <li key={e.slug} className="flex items-center justify-between gap-3 py-2">
                                        <Link href={`/events/${e.slug}`} className="min-w-0">
                                            <span className="font-medium">{e.name}</span>{' '}
                                            <span className="text-ink-muted">
                                                · {e.club} · {e.date}
                                            </span>
                                        </Link>
                                        <Badge>{e.status.replace('_', ' ')}</Badge>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-ink-muted">No events this month.</p>
                        )}
                    </Card>
                </div>
                <Card title="Club balances">
                    <ul className="grid gap-1 text-sm sm:grid-cols-2">
                        {balances.map((b) => (
                            <li key={b.slug} className="flex justify-between gap-3 rounded-control px-2 py-1.5 hover:bg-surface-muted">
                                <Link href={`/clubs/${b.slug}/finance`}>{b.name}</Link>
                                {b.ready ? <span className={`tabular-nums ${b.balance_sen < 0 ? 'text-danger' : ''}`}>{rm(b.balance_sen)}</span> : <Badge tone="warning">No opening balance</Badge>}
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </PortalLayout>
    );
}
