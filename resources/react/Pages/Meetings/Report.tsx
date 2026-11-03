import Badge from '@react/Components/ui/Badge';
import Input from '@react/Components/ui/Input';
import Pagination from '@react/Components/ui/Pagination';
import Select from '@react/Components/ui/Select';
import { useFilterVisit } from '@react/hooks/useFilterVisit';
import AppLayout from '@react/Layouts/AppLayout';
import PortalLayout from '@react/Layouts/PortalLayout';
import { dateTime } from '@/lib/format';
import type { Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface Row {
    id: number;
    date: string;
    club: string;
    club_slug: string;
    kind: string;
    title: string;
    audience: string;
    venue: string | null;
    pax: number | null;
    invited: number;
    present: number;
    checked_out: number;
    rate: number;
    minutes: string;
    upcoming: boolean;
}
interface ReportProps {
    club: { slug: string; name: string } | null;
    meetings: Paginated<Row>;
    totals: { meetings: number; committee: number; activities: number; average_rate: number; minutes_approved: number };
    filters: { kind: string; month: string; club: string; session: string };
    kinds: Record<string, string>;
    clubs: { slug: string; name: string }[];
    sessions: string[];
    exportBase: string;
}
const minutesTone = (s: string): 'success' | 'warning' | 'info' | 'neutral' => (s === 'Approved' ? 'success' : s === 'Returned' ? 'warning' : s === 'In Review' ? 'info' : 'neutral');

/** Meetings report: one club's (committee and advisor) or every club's (SLD, in the admin portal). */
export default function Report({ club, meetings, totals, filters, kinds, clubs, sessions, exportBase }: ReportProps) {
    const title = club ? `Meetings report · ${club.name}` : 'Meetings';
    const [f, setF] = useState({ ...filters });
    const params = useFilterVisit(club ? `/clubs/${club.slug}/meetings/report` : '/admin/meetings', f, 300);
    const set = (key: keyof typeof f) => (v: string): void => setF({ ...f, [key]: v });
    const exportUrl = (format: 'xlsx' | 'pdf'): string => `${exportBase}/export.${format}?${new URLSearchParams(params).toString()}`;
    const cards = [
        { label: 'Meetings', value: totals.meetings },
        { label: 'Committee meetings', value: totals.committee },
        { label: 'Weekly activities', value: totals.activities },
        { label: 'Average attendance', value: `${totals.average_rate}%` },
        { label: 'Minutes approved', value: totals.minutes_approved },
    ];
    const content = (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    {club ? (
                        <div>
                            <h1 className="text-xl font-bold">{title}</h1>
                            <Link href={`/clubs/${club.slug}/meetings`} className="text-sm text-brand-blue">
                                Back to meetings
                            </Link>
                        </div>
                    ) : (
                        <p className="max-w-2xl text-sm text-ink-muted">Every club's committee meetings and weekly activities: who was invited, who came (QR or ticked), who checked out, and the minutes.</p>
                    )}
                    <div className="flex gap-2">
                        <a href={exportUrl('xlsx')} className="rounded-control bg-ink px-4 py-2 text-sm font-semibold text-white hover:bg-ink/85">
                            Export (Excel)
                        </a>
                        <a href={exportUrl('pdf')} className="rounded-control border border-line bg-surface px-4 py-2 text-sm font-medium hover:bg-surface-muted">
                            PDF
                        </a>
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    {cards.map((c) => (
                        <div key={c.label} className="rounded-card border border-line bg-surface p-4 shadow-card">
                            <p className="text-xs text-ink-muted">{c.label}</p>
                            <p className="text-2xl font-bold tabular-nums">{c.value}</p>
                        </div>
                    ))}
                </div>
                <div className={`grid gap-3 rounded-card border border-line bg-surface p-4 ${club ? 'sm:grid-cols-3' : 'sm:grid-cols-4'}`}>
                    {!club && <Select value={f.club} onChange={set('club')} label="Club" options={[{ value: '', label: 'All clubs' }, ...clubs.map((c) => ({ value: c.slug, label: c.name }))]} />}
                    <Select value={f.kind} onChange={set('kind')} label="Type" options={[{ value: '', label: 'All types' }, ...Object.entries(kinds).map(([value, label]) => ({ value, label }))]} />
                    <Input value={f.month} onChange={set('month')} type="month" label="Month" />
                    <Select value={f.session} onChange={set('session')} label="Session" options={sessions.map((s) => ({ value: s, label: s }))} />
                </div>
                <div className="overflow-x-auto rounded-card border border-line bg-surface">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-surface-muted text-xs uppercase tracking-wide text-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-medium">Date</th>
                                {!club && <th className="px-3 py-3 font-medium">Club</th>}
                                <th className="px-3 py-3 font-medium">Meeting</th>
                                <th className="px-3 py-3 font-medium">For</th>
                                <th className="px-3 py-3 text-right font-medium">Invited</th>
                                <th className="px-3 py-3 text-right font-medium">Present</th>
                                <th className="px-3 py-3 text-right font-medium">Out</th>
                                <th className="px-3 py-3 text-right font-medium">Rate</th>
                                <th className="px-3 py-3 font-medium">Minutes</th>
                            </tr>
                        </thead>
                        <tbody>
                            {meetings.data.map((r) => (
                                <tr key={r.id} className="border-t border-line align-top">
                                    <td className="whitespace-nowrap px-3 py-2 text-xs">{dateTime(r.date)}</td>
                                    {!club && <td className="px-3 py-2">{r.club}</td>}
                                    <td className="px-3 py-2">
                                        <Link href={`/meetings/${r.id}`} className="font-medium text-brand-blue">
                                            {r.title}
                                        </Link>
                                        <div className="text-xs text-ink-muted">
                                            {r.kind}
                                            {r.venue && ` · ${r.venue}`}
                                            {r.pax ? ` · ${r.pax} pax planned` : ''}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2 text-xs">{r.audience}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{r.invited}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{r.present}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{r.checked_out}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{r.upcoming ? '—' : `${r.rate}%`}</td>
                                    <td className="px-3 py-2">
                                        <Badge tone={minutesTone(r.minutes)}>{r.minutes}</Badge>
                                    </td>
                                </tr>
                            ))}
                            {!meetings.data.length && (
                                <tr>
                                    <td colSpan={club ? 8 : 9} className="px-3 py-8 text-center text-ink-muted">
                                        No meetings match.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={meetings.links} />
            </div>
        </>
    );
    // SLD stays inside the admin portal; the club's own committee and advisor use the app layout.
    return club ? <AppLayout wide>{content}</AppLayout> : <PortalLayout title={title}>{content}</PortalLayout>;
}
