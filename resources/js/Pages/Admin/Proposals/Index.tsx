import Badge from '@/Components/ui/Badge';
import Input from '@/Components/ui/Input';
import Pagination from '@/Components/ui/Pagination';
import Select from '@/Components/ui/Select';
import { useFilterVisit } from '@/hooks/useFilterVisit';
import PortalLayout from '@/Layouts/PortalLayout';
import type { Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface Row {
    id: number;
    event: string;
    event_slug: string;
    club: string;
    starts_at: string;
    status: string;
    submitted_at: string | null;
    revenue: number;
    expenses: number;
    profit: number;
    downloadable: boolean;
    approved: boolean;
}
interface ProposalsProps {
    proposals: Paginated<Row>;
    filters: { q: string; club: string; status: string | null; session: string };
    clubs: { slug: string; name: string }[];
    sessions: string[];
}
const money = (n: number): string => n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const tone = (s: string): 'success' | 'warning' | 'danger' | 'info' => (s === 'approved' ? 'success' : s === 'returned' ? 'warning' : s === 'rejected' ? 'danger' : 'info');
const STATUSES = [
    { value: '', label: 'Any status' },
    { value: 'in_review', label: 'In review' },
    { value: 'returned', label: 'Returned' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'approved', label: 'Approved' },
];

export default function Index({ proposals, filters, clubs, sessions }: ProposalsProps) {
    const [f, setF] = useState({ q: filters.q, club: filters.club, status: filters.status ?? '', session: filters.session });
    const params = useFilterVisit('/admin/proposals', f);
    const set = (key: keyof typeof f) => (v: string): void => setF({ ...f, [key]: v });
    const exportUrl = (format: 'xlsx' | 'pdf'): string => `/admin/proposals/export.${format}?${new URLSearchParams(params).toString()}`;

    return (
        <PortalLayout title="Proposals & exports">
            <Head title="Proposals & exports" />
            <div className="grid gap-5">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <p className="max-w-2xl text-sm text-ink-muted">
                        Every submitted event proposal. Download one as PDF or Word, or everything for an event in one ZIP: the proposal, fund withdrawals, attendance, post mortem and the event's finance lines.
                    </p>
                    <div className="flex gap-2">
                        <a href={exportUrl('xlsx')} className="rounded-control bg-ink px-4 py-2 text-sm font-semibold text-white hover:bg-ink/85">
                            Export list (Excel)
                        </a>
                        <a href={exportUrl('pdf')} className="rounded-control border border-line bg-surface px-4 py-2 text-sm font-medium hover:bg-surface-muted">
                            PDF
                        </a>
                    </div>
                </div>
                <div className="grid gap-3 rounded-card border border-line bg-surface p-4 sm:grid-cols-4">
                    <Input value={f.q} onChange={set('q')} label="Search" placeholder="Event or club" />
                    <Select value={f.club} onChange={set('club')} label="Club" options={[{ value: '', label: 'All clubs' }, ...clubs.map((c) => ({ value: c.slug, label: c.name }))]} />
                    <Select value={f.status} onChange={set('status')} label="Status" options={STATUSES} />
                    <Select value={f.session} onChange={set('session')} label="Session" options={sessions.map((s) => ({ value: s, label: s }))} />
                </div>
                <div className="overflow-x-auto rounded-card border border-line bg-surface">
                    <table className="w-full min-w-[52rem] text-sm">
                        <thead className="bg-surface-muted text-left text-xs uppercase text-ink-muted">
                            <tr>
                                <th className="px-4 py-2">Event</th>
                                <th className="px-4 py-2">Status</th>
                                <th className="px-4 py-2 text-right">Revenue</th>
                                <th className="px-4 py-2 text-right">Expenses</th>
                                <th className="px-4 py-2 text-right">Profit / Loss</th>
                                <th className="px-4 py-2">Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            {proposals.data.map((p) => (
                                <tr key={p.id} className="border-t border-line align-top">
                                    <td className="px-4 py-3">
                                        <Link href={`/documents/${p.id}`} className="font-medium hover:text-primary">
                                            {p.event}
                                        </Link>
                                        <p className="text-xs text-ink-muted">
                                            {p.club} · {p.starts_at}
                                            {p.submitted_at && ` · submitted ${p.submitted_at}`}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge tone={tone(p.status)}>{p.status.replace('_', ' ')}</Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">{money(p.revenue)}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{money(p.expenses)}</td>
                                    <td className={`px-4 py-3 text-right font-medium tabular-nums ${p.profit < 0 ? 'text-danger' : ''}`}>{money(p.profit)}</td>
                                    <td className="px-4 py-3">
                                        {p.downloadable && (
                                            <div className="flex flex-wrap gap-x-3 gap-y-1 text-sm font-medium text-primary">
                                                <a href={`/documents/${p.id}/download/pdf`}>PDF</a>
                                                <a href={`/documents/${p.id}/download/docx`}>Word</a>
                                                <a href={`/admin/proposals/${p.id}/package.zip`} title="Proposal, withdrawals, attendance, post mortem and finance">
                                                    All + finance (ZIP)
                                                </a>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {!proposals.data.length && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-ink-muted">
                                        No proposals match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={proposals.links} />
            </div>
        </PortalLayout>
    );
}
