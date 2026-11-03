import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Input from '@/Components/ui/Input';
import PortalLayout from '@/Layouts/PortalLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Booking {
    id: number;
    club: string;
    by: string;
    type: string;
    summary: string;
    activity: string;
    status: string;
    sld_note: string | null;
}
const tone = (s: string): 'success' | 'danger' | 'warning' => (s === 'confirmed' ? 'success' : s === 'declined' ? 'danger' : 'warning');

export default function Index({ bookings }: { bookings: Booking[] }) {
    const [notes, setNotes] = useState<Record<number, string>>({});
    const decide = (id: number, confirm: boolean): void => router.post(`/admin/venues/${id}`, { confirm, note: notes[id] ?? null }, { preserveScroll: true });
    return (
        <PortalLayout title="Venue requests">
            <Head title="Venue requests" />
            <Card>
                <ul className="divide-y divide-line">
                    {bookings.map((b) => (
                        <li key={b.id} className="grid gap-2 py-4 text-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span>
                                    <strong>{b.club}</strong> · {b.type} · by {b.by}
                                </span>
                                <Badge tone={tone(b.status)}>{b.status}</Badge>
                            </div>
                            <p>{b.summary}</p>
                            <p className="text-ink-muted">{b.activity}</p>
                            {b.sld_note && <p className="text-xs text-ink-muted">Note: {b.sld_note}</p>}
                            {b.status === 'pending' && (
                                <div className="flex flex-wrap items-end gap-2">
                                    <div className="min-w-48 flex-1">
                                        <Input value={notes[b.id] ?? ''} onChange={(v) => setNotes({ ...notes, [b.id]: v })} placeholder="Note to the club (optional)" />
                                    </div>
                                    <Button onClick={() => decide(b.id, true)}>Confirm</Button>
                                    <Button variant="secondary" onClick={() => decide(b.id, false)}>
                                        Decline
                                    </Button>
                                </div>
                            )}
                        </li>
                    ))}
                    {!bookings.length && <li className="py-3 text-sm text-ink-muted">No venue requests.</li>}
                </ul>
            </Card>
        </PortalLayout>
    );
}
