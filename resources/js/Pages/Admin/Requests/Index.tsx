import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import Textarea from '@/Components/ui/Textarea';
import PortalLayout from '@/Layouts/PortalLayout';
import type { Option } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface ClubRequest {
    id: number;
    club: string;
    subject: string;
    body: string;
    due_on: string;
    overdue: boolean;
    reply: string | null;
    replied_by: string | null;
    replied_at: string | null;
}

export default function Index({ clubs, requests }: { clubs: Option[]; requests: ClubRequest[] }) {
    const form = useForm({ club_ids: [] as number[], subject: '', body: '' });
    const ids = form.data.club_ids;
    const toggle = (id: number): void => form.setData('club_ids', ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]);
    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.post('/admin/requests', { preserveScroll: true, onSuccess: () => form.reset() });
    };
    return (
        <PortalLayout title="Requests to clubs">
            <Head title="Requests to clubs" />
            <div className="grid gap-6">
                <Card title="New request">
                    <form className="grid gap-4" onSubmit={submit}>
                        <Input value={form.data.subject} onChange={(v) => form.setData('subject', v)} label="Subject" error={form.errors.subject} />
                        <Textarea value={form.data.body} onChange={(v) => form.setData('body', v)} label="What do you need from the club?" rows={4} error={form.errors.body} />
                        <div className="grid gap-2">
                            <Checkbox label="All clubs" checked={ids.length === clubs.length} onChange={(on) => form.setData('club_ids', on ? clubs.map((c) => Number(c.value)) : [])} />
                            <div className="flex flex-wrap gap-2">
                                {clubs.map((c) => (
                                    <button
                                        key={c.value}
                                        type="button"
                                        className={`rounded-full border px-3 py-1 text-sm ${ids.includes(Number(c.value)) ? 'border-primary bg-primary/15' : 'border-line'}`}
                                        onClick={() => toggle(Number(c.value))}
                                    >
                                        {c.label}
                                    </button>
                                ))}
                            </div>
                            {form.errors.club_ids && <p className="text-xs text-danger">{form.errors.club_ids}</p>}
                        </div>
                        <p className="text-xs text-ink-muted">Clubs must reply within 7 working days. They are reminded the day before and daily while overdue.</p>
                        <div>
                            <Button type="submit" loading={form.processing}>
                                Send
                            </Button>
                        </div>
                    </form>
                </Card>
                <Card title="Sent">
                    <ul className="divide-y divide-line">
                        {requests.map((r) => (
                            <li key={r.id} className="grid gap-1 py-3 text-sm">
                                <p className="flex flex-wrap items-center justify-between gap-2">
                                    <span>
                                        <strong>{r.club}</strong> · {r.subject}
                                    </span>
                                    {r.replied_at ? (
                                        <Badge tone="success">Replied {r.replied_at}</Badge>
                                    ) : (
                                        <Badge tone={r.overdue ? 'danger' : 'warning'}>
                                            {r.overdue ? 'Overdue' : 'Due'} {r.due_on}
                                        </Badge>
                                    )}
                                </p>
                                <p className="text-ink-muted">{r.body}</p>
                                {r.reply && (
                                    <p className="whitespace-pre-line rounded-control bg-surface-muted p-2">
                                        {r.replied_by}: {r.reply}
                                    </p>
                                )}
                            </li>
                        ))}
                        {!requests.length && <li className="py-3 text-sm text-ink-muted">No requests sent yet.</li>}
                    </ul>
                </Card>
            </div>
        </PortalLayout>
    );
}
