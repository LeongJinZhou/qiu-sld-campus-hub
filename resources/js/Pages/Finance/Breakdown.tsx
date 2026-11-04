import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import Icon from '@/Components/ui/Icon';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import AppLayout from '@/Layouts/AppLayout';
import { date, rm } from '@/lib/format';
import { matchesSearch } from '@/lib/search';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Tally {
    label: string;
    count: number;
    sen: number;
}
interface Row {
    name: string;
    student_number: string | null;
    email: string;
    status: string;
    date: string;
    method: string | null;
    order_id: string | null;
    amount_sen: number;
    collected_by: string | null;
    drive?: string | null;
}
interface BreakdownProps {
    club: { slug: string; name: string };
    breakdown: {
        title: string;
        subtitle: string;
        tiles: { label: string; count?: number; sen?: number; net?: boolean }[];
        check: { payments_sen: number; ledger_sen: number; expected_sen: number | null; ok: boolean };
        groups: { title: string; items: Tally[] }[];
        rows: Row[];
    };
    url: string;
    session: string | null;
    sessions: string[];
}
const tone = (s: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' =>
    s === 'Paid' ? 'success' : s === 'Rejected' ? 'danger' : s === 'Being checked' ? 'info' : s.startsWith('Registered') ? 'neutral' : 'warning';

/** Who paid for membership (or an event), how and to whom, with the Treasurer's reconciliation check. */
export default function Breakdown({ club, breakdown, url, session, sessions }: BreakdownProps) {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const statuses = [...new Set(breakdown.rows.map((r) => r.status))];
    const rows = breakdown.rows.filter((r) => (!status || r.status === status) && matchesSearch(search, r.name, r.student_number, r.email, r.order_id));
    const exportUrl = (format: string): string => `${url}/export/${format}${session ? `?session=${encodeURIComponent(session)}` : ''}`;
    const { check } = breakdown;
    const expected = check.expected_sen !== null;

    return (
        <AppLayout>
            <Head title={`${breakdown.title} · ${club.name}`} />
            <div className="grid gap-6">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="min-w-0">
                        <Link href={`/clubs/${club.slug}/finance`} className="text-sm text-primary">
                            ← Finance · {club.name}
                        </Link>
                        <h1 className="text-xl font-bold">{breakdown.title}</h1>
                        <p className="text-sm text-ink-muted">{breakdown.subtitle}</p>
                    </div>
                    <div className="flex flex-wrap items-end gap-2">
                        {session && <Select value={session} onChange={(s) => router.get(url, { session: s }, { preserveScroll: true })} label="Session" options={sessions.map((s) => ({ value: s, label: s }))} />}
                        <a href={exportUrl('xlsx')}>
                            <Button variant="secondary">
                                <Icon name="file" size={16} /> Excel
                            </Button>
                        </a>
                        <a href={exportUrl('pdf')}>
                            <Button variant="secondary">PDF</Button>
                        </a>
                    </div>
                </header>
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {breakdown.tiles.map((t) => (
                        <div key={t.label} className="rounded-card border border-line bg-surface p-4 shadow-card">
                            <p className="text-xs text-ink-muted">{t.label}</p>
                            {t.sen !== undefined ? (
                                <p className={`text-xl font-bold tabular-nums ${t.net ? (t.sen < 0 ? 'text-danger' : 'text-success') : ''}`}>{rm(t.sen)}</p>
                            ) : (
                                <p className="text-xl font-bold tabular-nums">{t.count}</p>
                            )}
                        </div>
                    ))}
                </div>
                {/* Payments counted vs. money filed in the ledger: the Treasurer's quick reconciliation. */}
                <div className={`flex items-start gap-3 rounded-card border p-4 text-sm ${check.ok ? 'border-success/30 bg-success/5' : 'border-warning/40 bg-warning/10'}`}>
                    <Icon name={check.ok ? 'check' : 'shield'} size={18} className="mt-0.5" />
                    {check.ok ? (
                        <p>
                            Everything matches: {rm(check.payments_sen)} in confirmed payments, {rm(check.ledger_sen)} filed in the ledger
                            {expected && `, and paid places × fee = ${rm(check.expected_sen!)}`}.
                        </p>
                    ) : (
                        <p>
                            Please check: confirmed payments {rm(check.payments_sen)} · in the ledger {rm(check.ledger_sen)}
                            {expected && ` · paid places × fee ${rm(check.expected_sen!)}`}. A fee changed after some people paid also shows here.
                        </p>
                    )}
                </div>
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {breakdown.groups.map((g) => (
                        <Card key={g.title} title={g.title}>
                            {g.items.length ? (
                                <ul className="grid gap-1 text-sm">
                                    {g.items.map((i) => (
                                        <li key={i.label} className="grid grid-cols-[1fr_auto] items-center gap-3 rounded-control px-2 py-2 odd:bg-surface-muted/60">
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium">{i.label}</span>
                                                <span className="text-xs text-ink-muted">{i.count} paid</span>
                                            </span>
                                            <span className="font-semibold tabular-nums">{rm(i.sen)}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-ink-muted">Nothing yet.</p>
                            )}
                        </Card>
                    ))}
                </div>
                <section className="grid gap-3">
                    <div className="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card sm:grid-cols-[1fr_14rem]">
                        <Input value={search} onChange={setSearch} label="Search" placeholder="Name, student ID, email or order ID" />
                        <Select value={status} onChange={setStatus} label="Status" options={[{ value: '', label: 'All' }, ...statuses.map((s) => ({ value: s, label: s }))]} />
                    </div>
                    {!breakdown.rows.length ? (
                        <EmptyState title="No one yet" description="Registrations and payments appear here as they come in." />
                    ) : (
                        <div className="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                            <table className="w-full text-sm">
                                <thead className="bg-surface-muted text-left text-xs uppercase tracking-wide text-ink-muted">
                                    <tr>
                                        {['Name', 'Status', 'Date', 'Method · received by', 'Order ID'].map((h) => (
                                            <th key={h} className="px-3 py-3 font-medium">
                                                {h}
                                            </th>
                                        ))}
                                        <th className="px-3 py-3 text-right font-medium">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((r, i) => (
                                        <tr key={`${r.order_id ?? r.email}-${i}`} className="border-t border-line align-top">
                                            <td className="min-w-44 px-3 py-2.5">
                                                <span className="font-medium">{r.name}</span>
                                                <span className="block text-xs text-ink-muted">
                                                    {r.student_number} · {r.email}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2.5">
                                                <Badge tone={tone(r.status)}>{r.status}</Badge>
                                                {r.drive && <span className="mt-1 block text-xs text-ink-muted">{r.drive}</span>}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2.5 tabular-nums">{date(r.date)}</td>
                                            <td className="whitespace-nowrap px-3 py-2.5 text-xs">
                                                {r.method ? r.method.charAt(0).toUpperCase() + r.method.slice(1) : '—'}
                                                {r.collected_by && <span className="block text-ink-muted">{r.collected_by}</span>}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2.5 font-mono text-xs">{r.order_id ?? '—'}</td>
                                            <td className="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">{r.amount_sen ? rm(r.amount_sen) : ''}</td>
                                        </tr>
                                    ))}
                                    {!rows.length && (
                                        <tr>
                                            <td colSpan={6} className="px-3 py-8 text-center text-ink-muted">
                                                No one matches.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
