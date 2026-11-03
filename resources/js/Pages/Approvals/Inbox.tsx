import ApprovalTracker from '@/Components/forms/ApprovalTracker';
import Badge from '@/Components/ui/Badge';
import EmptyState from '@/Components/ui/EmptyState';
import AppLayout from '@/Layouts/AppLayout';
import type { ApprovalProgress } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface InboxProps {
    stats: { waiting: number; overdue: number; decided_this_month: number; avg_days: number | null };
    recent: { document_id: number; type: string; club: string; decision: string; decided_at: string | null }[];
    items: { id: number; type: string; club: string; event: string | null; role: string; step_no: number; due_at: string | null; overdue: boolean; progress: ApprovalProgress }[];
}

export default function Inbox({ stats, recent, items }: InboxProps) {
    const tiles = [
        { label: 'Waiting for you', value: stats.waiting, alert: false },
        { label: 'Overdue', value: stats.overdue, alert: stats.overdue > 0 },
        { label: 'Decided this month', value: stats.decided_this_month, alert: false },
        { label: 'Average days to decide', value: stats.avg_days ?? '—', alert: false },
    ];
    return (
        <AppLayout>
            <Head title="Approvals" />
            <div className="grid gap-4">
                <section className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {tiles.map((tile) => (
                        <div key={tile.label} className="rounded-card border border-line bg-surface p-4 shadow-card">
                            <p className="text-xs text-ink-muted">{tile.label}</p>
                            <p className={`text-2xl font-bold tabular-nums ${tile.alert ? 'text-danger' : ''}`}>{tile.value}</p>
                        </div>
                    ))}
                </section>
                <h1 className="text-xl font-bold">Waiting for you</h1>
                {items.length === 0 && <EmptyState title="Nothing to approve" description="You'll get an email and a notification when a document reaches your step." />}
                <ul className="grid gap-2">
                    {items.map((item) => (
                        <li key={item.id}>
                            <Link href={`/approvals/${item.id}`} className="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card transition hover:shadow-pop">
                                <span className="flex flex-wrap items-center justify-between gap-3">
                                    <span className="grid">
                                        <strong>
                                            {item.type} · {item.event ?? item.club}
                                        </strong>
                                        <span className="text-sm text-ink-muted">
                                            {item.club} · step {item.step_no} as {item.role}
                                        </span>
                                    </span>
                                    {item.due_at && (
                                        <Badge tone={item.overdue ? 'danger' : 'neutral'}>
                                            {item.overdue ? 'Overdue' : 'Due'} {item.due_at}
                                        </Badge>
                                    )}
                                </span>
                                <ApprovalTracker progress={item.progress} compact />
                            </Link>
                        </li>
                    ))}
                </ul>
                {recent.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="font-semibold">Recently decided</h2>
                        <ul className="divide-y divide-line rounded-card border border-line bg-surface text-sm">
                            {recent.map((r) => (
                                <li key={`${r.document_id}${r.decision}`} className="flex justify-between gap-3 p-3">
                                    <Link href={`/documents/${r.document_id}`}>
                                        {r.type} · {r.club}
                                    </Link>
                                    <span className="text-ink-muted">
                                        {r.decision} {r.decided_at}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
