import Pagination from '@react/Components/ui/Pagination';
import PortalLayout from '@react/Layouts/PortalLayout';
import type { Paginated } from '@/types';
import { Head } from '@inertiajs/react';

interface Entry {
    id: number;
    at: string | null;
    actor: string;
    action: string;
    subject: string | null;
    data: Record<string, unknown> | null;
}

export default function Index({ entries }: { entries: Paginated<Entry> }) {
    return (
        <PortalLayout title="Audit log">
            <Head title="Audit log" />
            <div className="grid gap-4">
                <div className="overflow-x-auto rounded-card border border-line bg-surface">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-surface-muted text-xs uppercase text-ink-muted">
                            <tr>
                                {['When', 'Who', 'Action', 'Subject', 'Details'].map((h) => (
                                    <th key={h} className="px-4 py-3">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {entries.data.map((entry) => (
                                <tr key={entry.id} className="border-t border-line align-top">
                                    <td className="whitespace-nowrap px-4 py-3 text-ink-muted">{entry.at}</td>
                                    <td className="px-4 py-3">{entry.actor}</td>
                                    <td className="px-4 py-3 font-mono text-xs">{entry.action}</td>
                                    <td className="px-4 py-3">{entry.subject ?? '—'}</td>
                                    <td className="px-4 py-3">{entry.data && <code className="text-xs text-ink-muted">{JSON.stringify(entry.data)}</code>}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination links={entries.links} />
            </div>
        </PortalLayout>
    );
}
