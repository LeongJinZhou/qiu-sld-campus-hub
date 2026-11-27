import Badge from '@/Components/ui/Badge';
import Table from '@/Components/ui/Table';
import PortalLayout from '@/Layouts/PortalLayout';
import { url } from '@/lib/url';
import { Head, Link } from '@inertiajs/react';

interface ClubRow {
    id: number;
    slug: string;
    name: string;
    category: string | null;
    managed_by: string;
    missing: string[];
    status: string;
    last_activity: string | null;
}
const COLUMNS = [
    { key: 'name' as const, label: 'Club', sortable: true },
    { key: 'category' as const, label: 'Category', sortable: true },
    { key: 'managed_by' as const, label: 'Managed by', sortable: true },
    { key: 'status' as const, label: 'Status', sortable: true },
    { key: 'missing' as const, label: 'Compulsory positions' },
];

export default function Index({ session, clubs, applications = [] }: { session: string; clubs: ClubRow[]; applications?: { id: number; name: string; applicant: string; status: string; ready: boolean }[] }) {
    return (
        <PortalLayout title="Clubs">
            <Head title="Clubs" />
            <div className="grid gap-4">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-sm text-ink-muted">Session {session}</p>
                    <Link
                        href={url('/admin/clubs/create')}
                        className="inline-flex h-10 items-center justify-center rounded-control bg-primary px-4 text-sm font-medium text-white shadow-sm transition hover:bg-primary-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    >
                        New club
                    </Link>
                </div>
                {applications.length > 0 && (
                    <section className="grid gap-2 rounded-card border border-line bg-surface p-4 shadow-card">
                        <h2 className="text-sm font-semibold">New club applications</h2>
                        {applications.map((a) => (
                            <Link key={a.id} href={url(`/club-applications/${a.id}`)} className="flex flex-wrap justify-between gap-2 text-sm hover:underline">
                                <span>
                                    <strong>{a.name}</strong> · {a.applicant}
                                </span>
                                <span className={a.ready ? 'font-semibold text-primary' : 'text-ink-muted'}>{a.ready ? 'Ready for your decision' : a.status}</span>
                            </Link>
                        ))}
                    </section>
                )}
                <Table
                    columns={COLUMNS}
                    rows={clubs}
                    rowKey="id"
                    cells={{
                        name: (row) => (
                            <div className="flex min-w-0 flex-wrap items-baseline gap-x-3 gap-y-0.5">
                                <Link href={url(`/admin/clubs/${row.slug}/edit`)} className="min-w-0 break-words font-medium hover:underline">
                                    {row.name}
                                </Link>
                                <Link href={url(`/admin/clubs/${row.slug}/members`)} className="shrink-0 text-xs text-primary hover:underline">
                                    Members
                                </Link>
                            </div>
                        ),
                        managed_by: (row) => <Badge tone={row.managed_by === 'committee' ? 'success' : 'neutral'}>{row.managed_by === 'committee' ? 'Committee' : 'SLD'}</Badge>,
                        status: (row) => (
                            <>
                                <Badge tone={row.status === 'active' ? 'success' : row.status === 'inactive' ? 'danger' : 'warning'}>
                                    {row.status === 'at_risk' ? 'At risk' : row.status === 'inactive' ? 'Inactive' : 'Active'}
                                </Badge>
                                <span className="ml-2 text-xs text-ink-muted">{row.last_activity ? `Last activity ${row.last_activity}` : 'No activity yet'}</span>
                            </>
                        ),
                        missing: (row) => (row.missing.length === 0 ? <Badge tone="success">Complete</Badge> : (
                                <span className="inline-block max-w-xs whitespace-normal break-words rounded-2xl bg-warning/10 px-2.5 py-0.5 text-xs font-medium text-warning">Missing {row.missing.join(', ')}</span>
                            )),
                    }}
                />
            </div>
        </PortalLayout>
    );
}
