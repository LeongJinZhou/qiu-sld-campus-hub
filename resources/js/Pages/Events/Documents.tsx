import EvidencePhotos, { type Evidence } from '@/Components/reports/EvidencePhotos';
import Badge from '@/Components/ui/Badge';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import type { ChangeEvent } from 'react';

interface Item {
    id: number;
    label: string;
    due_on: string;
    overdue: boolean;
    submitted_at: string | null;
    file_name: string | null;
    file_url: string | null;
}

export default function Documents({ event, evidence, items }: { event: { slug: string; name: string }; evidence: Evidence; items: Item[] }) {
    const upload = (id: number, e: ChangeEvent<HTMLInputElement>): void => {
        const file = e.target.files?.[0];
        if (file) router.post(`/events/${event.slug}/documents/${id}`, { file }, { preserveScroll: true, forceFormData: true });
    };
    return (
        <AppLayout>
            <Head title={`Post-event documents · ${event.name}`} />
            <div className="grid gap-4">
                <div>
                    <h1 className="text-xl font-bold">Post-event documents</h1>
                    <p className="text-sm text-ink-muted">{event.name} · Finance record and receipts within 1 week; everything else within 2 weeks. Reminders are automatic.</p>
                </div>
                <ul className="grid gap-2">
                    {items.map((item) => (
                        <li key={item.id} className="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-surface p-4">
                            <div className="grid">
                                <strong className="text-sm">{item.label}</strong>
                                <span className="text-xs text-ink-muted">Due {item.due_on}</span>
                            </div>
                            <div className="flex items-center gap-3">
                                {item.file_url && (
                                    <a href={item.file_url} className="text-xs text-primary">
                                        {item.file_name}
                                    </a>
                                )}
                                <Badge tone={item.submitted_at ? 'success' : item.overdue ? 'danger' : 'neutral'}>{item.submitted_at ? `Submitted ${item.submitted_at}` : item.overdue ? 'Overdue' : 'Pending'}</Badge>
                                <label className="cursor-pointer rounded-control border border-line px-3 py-1.5 text-xs font-medium hover:bg-surface-muted">
                                    {item.submitted_at ? 'Replace' : 'Upload'}
                                    <input type="file" className="sr-only" onChange={(e) => upload(item.id, e)} />
                                </label>
                            </div>
                        </li>
                    ))}
                </ul>
                <EvidencePhotos evidence={evidence} canEdit title="Event photos" />
                <a href={`/events/${event.slug}/photos.zip`} className="justify-self-start text-sm font-medium text-primary">
                    Download all photos (ZIP)
                </a>
            </div>
        </AppLayout>
    );
}
