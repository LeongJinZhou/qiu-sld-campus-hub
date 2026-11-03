import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Table from '@/Components/ui/Table';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';

interface Person {
    name: string;
    student_number: string | null;
    signed_at: string | null;
}
const COLUMNS = [
    { key: 'name' as const, label: 'Name', sortable: true },
    { key: 'student_number' as const, label: 'Student ID' },
    { key: 'signed_at' as const, label: 'Status', sortable: true },
];

export default function Indemnities({ event, people }: { event: { slug: string; name: string }; people: Person[] }) {
    const signed = people.filter((p) => p.signed_at).length;
    return (
        <AppLayout>
            <Head title={`Indemnities · ${event.name}`} />
            <div className="grid gap-4">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold">Indemnity forms</h1>
                        <p className="text-sm text-ink-muted">
                            {event.name} · {signed} of {people.length} signed
                        </p>
                    </div>
                    <a href={`/events/${event.slug}/indemnities/pdf`}>
                        <Button disabled={signed === 0}>Download all signed (PDF)</Button>
                    </a>
                </header>
                <Table
                    columns={COLUMNS}
                    rows={people}
                    rowKey="name"
                    cells={{ signed_at: (row) => <Badge tone={row.signed_at ? 'success' : 'warning'}>{row.signed_at ? `Signed ${row.signed_at}` : 'Not signed'}</Badge> }}
                />
            </div>
        </AppLayout>
    );
}
