import Button from '@react/Components/ui/Button';
import Card from '@react/Components/ui/Card';
import Input from '@react/Components/ui/Input';
import AppLayout from '@react/Layouts/AppLayout';
import { date } from '@/lib/format';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function Create({ club, earliest, leadDays }: { club: { slug: string; name: string }; earliest: string; leadDays: number }) {
    const form = useForm({ name: '', starts_at: '', ends_at: '', expected_participants: 30 as number | string });
    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.post(`/clubs/${club.slug}/events`);
    };
    return (
        <AppLayout>
            <Head title="New event" />
            <Card className="mx-auto max-w-xl" title={`New event · ${club.name}`}>
                <form className="grid gap-4" onSubmit={submit}>
                    <Input value={form.data.name} onChange={(v) => form.setData('name', v)} label="Event name" required error={form.errors.name} />
                    <Input
                        value={form.data.expected_participants}
                        onChange={(v) => form.setData('expected_participants', v)}
                        type="number"
                        label="Expected participants"
                        required
                        error={form.errors.expected_participants}
                    />
                    <p className="rounded-control bg-brand-cyan/10 px-3 py-2 text-sm">
                        Submit proposals at least {leadDays} days before the event. Earliest event date if you submit today: <strong>{date(earliest)}</strong>. An earlier date is possible with a reason that SLD
                        approves.
                    </p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Input value={form.data.starts_at} onChange={(v) => form.setData('starts_at', v)} type="datetime-local" label="Starts" required error={form.errors.starts_at} />
                        <Input value={form.data.ends_at} onChange={(v) => form.setData('ends_at', v)} type="datetime-local" label="Ends" required error={form.errors.ends_at} />
                    </div>
                    <Button type="submit" loading={form.processing} className="justify-self-start">
                        Create and open proposal
                    </Button>
                </form>
            </Card>
        </AppLayout>
    );
}
