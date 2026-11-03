import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import PortalLayout from '@/Layouts/PortalLayout';
import { rm } from '@/lib/format';
import type { Option } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Fee {
    id: number;
    club: string;
    session: string;
    amount_sen: number;
    account_name: string | null;
    payment_url: string | null;
    approved: boolean;
}
interface Drive {
    id: number;
    title: string;
    club: string;
    starts_at: string;
    ends_at: string;
}

export default function Index({ fees, drives, clubs }: { fees: Fee[]; drives: Drive[]; clubs: Option[] }) {
    const drive = useForm({ title: '', club_id: '' as number | string, starts_at: '', ends_at: '' });
    const submit = (e: FormEvent): void => {
        e.preventDefault();
        drive.post('/admin/drives', { onSuccess: () => drive.reset() });
    };
    return (
        <PortalLayout title="Membership fees & drives">
            <Head title="Fees & drives" />
            <div className="grid gap-6">
                <Card title="Club fees (set by Treasurers)">
                    <ul className="divide-y divide-line">
                        {fees.map((f) => (
                            <li key={f.id} className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                                <span>
                                    <strong>{f.club}</strong> · {f.session} · {f.amount_sen ? rm(f.amount_sen) : 'Free'}
                                    {f.account_name && ` · to ${f.account_name}`}
                                </span>
                                {f.approved ? (
                                    <Badge tone="success">Approved</Badge>
                                ) : (
                                    <Button size="sm" onClick={() => router.post(`/admin/fees/${f.id}/approve`, {}, { preserveScroll: true })}>
                                        Approve
                                    </Button>
                                )}
                            </li>
                        ))}
                        {!fees.length && <li className="py-3 text-sm text-ink-muted">No fees submitted yet.</li>}
                    </ul>
                </Card>
                <Card title="Membership drives">
                    <form className="mb-4 grid gap-3 md:grid-cols-[1fr_14rem_12rem_12rem_auto] md:items-end" onSubmit={submit}>
                        <Input value={drive.data.title} onChange={(v) => drive.setData('title', v)} label="Title" placeholder="Orientation Week Drive" error={drive.errors.title} />
                        <Select value={drive.data.club_id} onChange={(v) => drive.setData('club_id', v)} label="Club" options={[{ value: '', label: 'All clubs' }, ...clubs]} />
                        <Input value={drive.data.starts_at} onChange={(v) => drive.setData('starts_at', v)} type="datetime-local" label="Starts" error={drive.errors.starts_at} />
                        <Input value={drive.data.ends_at} onChange={(v) => drive.setData('ends_at', v)} type="datetime-local" label="Ends" error={drive.errors.ends_at} />
                        <Button type="submit" loading={drive.processing}>
                            Schedule
                        </Button>
                    </form>
                    <ul className="grid gap-1 text-sm">
                        {drives.map((d) => (
                            <li key={d.id}>
                                <strong>{d.title}</strong> · {d.club} · {d.starts_at} → {d.ends_at}
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </PortalLayout>
    );
}
