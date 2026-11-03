import Badge from '@/Components/ui/Badge';
import Select from '@/Components/ui/Select';
import PortalLayout from '@/Layouts/PortalLayout';
import { date, rm } from '@/lib/format';
import { matchesSearch } from '@/lib/search';
import { Head, Link, router } from '@inertiajs/react';
import { Fragment, useState } from 'react';

interface Pkg {
    slug: string;
    name: string;
    date: string;
    missing: string[];
    overdue: boolean;
    complete: boolean;
}
interface Row {
    slug: string;
    name: string;
    weekly: { key: string; held: number; submitted: number }[];
    weekly_missing: number;
    events: Pkg[];
    events_incomplete: number;
    agm: { report: string; package: boolean; acknowledged: boolean } | null;
}
interface TrackingProps {
    session: string;
    sessions: string[];
    months: { key: string; label: string }[];
    clubs: Row[];
    transfers: { id: number; club: string; for: string; total_sen: number; stage: string; due_on: string; days_late: number }[];
    totals: { weekly_missing: number; events_incomplete: number; agm_missing: number };
}
// Cell state for a month: nothing held (blank), all reported (done), some missing (missing count).
const cell = (w: { held: number; submitted: number }): { text: string; cls: string; title: string } =>
    w.held === 0
        ? { text: '·', cls: 'text-ink-muted', title: 'No weekly activity held' }
        : w.submitted >= w.held
          ? { text: '✓', cls: 'bg-success/15 text-success', title: `${w.submitted} of ${w.held} reports submitted` }
          : { text: `${w.held - w.submitted}`, cls: 'bg-danger/15 font-bold text-danger', title: `${w.held - w.submitted} of ${w.held} reports missing` };

export default function Index({ session, sessions, months, clubs, transfers, totals }: TrackingProps) {
    const [search, setSearch] = useState('');
    const [onlyProblems, setOnlyProblems] = useState(false);
    const [open, setOpen] = useState<string | null>(null);
    const rows = clubs.filter((c) => matchesSearch(search, c.name) && (!onlyProblems || c.weekly_missing > 0 || c.events_incomplete > 0 || !c.agm?.package));
    const tiles = [
        { label: 'Weekly reports missing', value: totals.weekly_missing, bad: 'text-danger' },
        { label: 'Events with an incomplete package', value: totals.events_incomplete, bad: 'text-danger' },
        { label: 'Clubs without an AGM package', value: totals.agm_missing, bad: 'text-warning' },
        { label: 'Withdrawal transfers overdue', value: transfers.length, bad: 'text-danger' },
    ];

    return (
        <PortalLayout title="Submissions tracker">
            <Head title="Submissions tracker" />
            <div className="grid gap-5">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <p className="text-sm text-ink-muted">What every club has submitted for {session}, and what is missing or overdue.</p>
                    <Select value={session} onChange={(s) => router.get('/admin/tracking', { session: s }, { preserveScroll: true })} label="Session" options={sessions.map((s) => ({ value: s, label: s }))} />
                </div>
                <section className="grid gap-3 sm:grid-cols-4">
                    {tiles.map((t) => (
                        <div key={t.label} className="flex flex-col justify-between gap-1 rounded-card border border-line bg-surface p-4 shadow-card">
                            <p className="text-xs text-ink-muted">{t.label}</p>
                            <p className={`text-2xl font-bold tabular-nums ${t.value ? t.bad : 'text-success'}`}>{t.value}</p>
                        </div>
                    ))}
                </section>
                {transfers.length > 0 && (
                    <section className="grid gap-2 rounded-card border border-danger/40 bg-danger/5 p-4">
                        <h2 className="font-semibold text-danger">Transfers past the deadline</h2>
                        <ul className="grid gap-1 text-sm">
                            {transfers.map((t) => (
                                <li key={t.id} className="flex flex-wrap justify-between gap-2">
                                    <Link href={`/withdrawals/${t.id}`} className="font-medium hover:underline">
                                        {t.club} · {t.for}
                                    </Link>
                                    <span className="tabular-nums">
                                        {rm(t.total_sen)} · due {date(t.due_on)} · <strong className="text-danger">{t.days_late} days late</strong>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
                <div className="flex flex-wrap items-center gap-3">
                    <input value={search} onChange={(e) => setSearch(e.target.value)} type="search" placeholder="Find a club" className="h-10 min-w-48 flex-1 rounded-control border border-line bg-surface px-3 text-sm" />
                    <label className="flex items-center gap-2 text-sm">
                        <input checked={onlyProblems} onChange={(e) => setOnlyProblems(e.target.checked)} type="checkbox" className="size-4" /> Only clubs with something missing
                    </label>
                </div>
                <div className="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                    <table className="w-full text-sm">
                        <thead className="bg-surface-muted text-left text-xs text-ink-muted">
                            <tr>
                                <th className="sticky left-0 bg-surface-muted px-3 py-2">Club</th>
                                {months.map((m) => (
                                    <th key={m.key} className="px-2 py-2 text-center">
                                        {m.label}
                                    </th>
                                ))}
                                <th className="px-3 py-2">Events</th>
                                <th className="px-3 py-2">AGM</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((c) => (
                                <Fragment key={c.slug}>
                                    <tr className="border-t border-line">
                                        <td className="sticky left-0 bg-surface px-3 py-2 font-medium">
                                            <button type="button" className="text-left hover:underline" onClick={() => setOpen(open === c.slug ? null : c.slug)}>
                                                {c.name}
                                            </button>
                                        </td>
                                        {c.weekly.map((w) => {
                                            const s = cell(w);
                                            return (
                                                <td key={w.key} className="px-1 py-2 text-center">
                                                    <span className={`inline-grid size-7 place-items-center rounded tabular-nums ${s.cls}`} title={s.title}>
                                                        {s.text}
                                                    </span>
                                                </td>
                                            );
                                        })}
                                        <td className="px-3 py-2">
                                            {c.events_incomplete ? <Badge tone="danger">{c.events_incomplete} incomplete</Badge> : c.events.length ? <Badge tone="success">All complete</Badge> : <span className="text-ink-muted">—</span>}
                                        </td>
                                        <td className="px-3 py-2">
                                            {c.agm?.acknowledged ? (
                                                <Badge tone="success">Acknowledged</Badge>
                                            ) : c.agm?.package ? (
                                                <Badge tone="info">Package in</Badge>
                                            ) : c.agm ? (
                                                <Badge tone="warning">Report {c.agm.report.replace('_', ' ')}</Badge>
                                            ) : (
                                                <Badge tone="neutral">Not held</Badge>
                                            )}
                                        </td>
                                    </tr>
                                    {open === c.slug && (
                                        <tr className="bg-surface-muted/60">
                                            <td colSpan={months.length + 3} className="px-3 py-3">
                                                {!c.events.length && <p className="text-ink-muted">No finished events this session.</p>}
                                                <ul className="grid gap-1">
                                                    {c.events.map((e) => (
                                                        <li key={e.slug} className="flex flex-wrap items-center gap-2">
                                                            <Link href={`/events/${e.slug}/documents`} className="font-medium hover:underline">
                                                                {date(e.date)} · {e.name}
                                                            </Link>
                                                            <Badge tone={e.complete ? 'success' : e.overdue ? 'danger' : 'warning'}>{e.complete ? 'Complete' : e.overdue ? 'Overdue' : 'In progress'}</Badge>
                                                            {e.missing.length > 0 && <span className="text-xs text-ink-muted">Missing: {e.missing.join(', ')}</span>}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
                <p className="text-xs text-ink-muted">Months: ✓ every weekly activity reported · number = reports missing · · = no weekly activity held. Click a club for its events.</p>
            </div>
        </PortalLayout>
    );
}
