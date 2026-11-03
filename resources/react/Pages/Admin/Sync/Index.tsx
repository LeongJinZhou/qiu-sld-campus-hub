import Badge from '@react/Components/ui/Badge';
import Button from '@react/Components/ui/Button';
import Table from '@react/Components/ui/Table';
import PortalLayout from '@react/Layouts/PortalLayout';
import { Head, useForm } from '@inertiajs/react';

interface Run {
    id: number;
    trigger: string;
    records_seen: number;
    upserted: number;
    error: string | null;
    started_at: string;
    seconds: number | null;
}
const COLUMNS = [
    { key: 'started_at' as const, label: 'Started', sortable: true },
    { key: 'trigger' as const, label: 'Started by', sortable: true },
    { key: 'upserted' as const, label: 'Students updated / checked' },
    { key: 'seconds' as const, label: 'Duration' },
    { key: 'error' as const, label: 'Result' },
];

export default function Index({ runs }: { runs: Run[] }) {
    const form = useForm({});
    return (
        <PortalLayout title="Student records sync">
            <Head title="Student records sync" />
            <div className="grid gap-4">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-sm text-ink-muted">
                        Student details are copied from the university student system (camsys) every night at 02:00, and again for each student when they sign in. The portal only reads from camsys; it never changes
                        it.
                    </p>
                    <Button loading={form.processing} onClick={() => form.post('/admin/sync', { preserveScroll: true })}>
                        Sync now
                    </Button>
                </div>
                <Table
                    columns={COLUMNS}
                    rows={runs}
                    rowKey="id"
                    cells={{
                        upserted: (row) => `${row.upserted} / ${row.records_seen}`,
                        seconds: (row) => (row.seconds === null ? '—' : `${row.seconds}s`),
                        error: (row) =>
                            row.error ? (
                                <span title={row.error}>
                                    <Badge tone="danger">Failed</Badge>
                                </span>
                            ) : (
                                <Badge tone="success">OK</Badge>
                            ),
                    }}
                />
            </div>
        </PortalLayout>
    );
}
