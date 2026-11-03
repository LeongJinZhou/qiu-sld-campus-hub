import Badge from '@react/Components/ui/Badge';
import Button from '@react/Components/ui/Button';
import Card from '@react/Components/ui/Card';
import Checkbox from '@react/Components/ui/Checkbox';
import Input from '@react/Components/ui/Input';
import Select from '@react/Components/ui/Select';
import Textarea from '@react/Components/ui/Textarea';
import AppLayout from '@react/Layouts/AppLayout';
import { date } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

type VenueType = 'la_place' | 'semester' | 'big_room';
interface VenuesProps {
    club: { slug: string; name: string };
    types: Record<VenueType, string>;
    rules: string[];
    earliestBigRoom: string;
    bookings: { id: number; type: VenueType; summary: string; status: string; sld_note: string | null }[];
}
const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].map((d) => ({ value: d, label: d }));
const tone = (s: string): 'success' | 'danger' | 'warning' => (s === 'confirmed' ? 'success' : s === 'declined' ? 'danger' : 'warning');

export default function Index({ club, types, rules, earliestBigRoom, bookings }: VenuesProps) {
    const form = useForm({ type: 'la_place' as VenueType, venue: '', date: '', semester: '', weekday: '', starts_at: '', ends_at: '', purpose: '', items: '', activity: '', rules_accepted: false });
    const { data, setData, errors } = form;
    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.post(`/clubs/${club.slug}/venues`, { preserveScroll: true, onSuccess: () => form.reset() });
    };
    return (
        <AppLayout>
            <Head title={`Venues · ${club.name}`} />
            <div className="grid gap-6">
                <div>
                    <Link href={`/clubs/${club.slug}`} className="text-sm text-ink-muted hover:underline">
                        ← {club.name}
                    </Link>
                    <h1 className="text-2xl font-bold">Venue booking</h1>
                    <p className="text-sm text-ink-muted">SLD is emailed and confirms in the app. You'll get a notification.</p>
                </div>
                <Card title="New request">
                    <form className="grid gap-4" onSubmit={submit}>
                        <Select value={data.type} onChange={(v) => setData('type', v as VenueType)} label="What do you need?" options={Object.entries(types).map(([value, label]) => ({ value, label }))} error={errors.type} />
                        {data.type !== 'la_place' && (
                            <Input value={data.venue} onChange={(v) => setData('venue', v)} label={data.type === 'semester' ? 'Venue (for the whole semester)' : 'Room'} placeholder="e.g. Lecture Hall 2" error={errors.venue} />
                        )}
                        <div className="grid gap-4 sm:grid-cols-3">
                            {data.type === 'semester' ? (
                                <>
                                    <Input value={data.semester} onChange={(v) => setData('semester', v)} label="Semester" placeholder="2026/2027 Sem 1" error={errors.semester} />
                                    <Select value={data.weekday} onChange={(v) => setData('weekday', v)} label="Every" placeholder="Choose a day" options={WEEKDAYS} error={errors.weekday} />
                                </>
                            ) : (
                                <Input value={data.date} onChange={(v) => setData('date', v)} type="date" label="Date" error={errors.date} />
                            )}
                            <Input value={data.starts_at} onChange={(v) => setData('starts_at', v)} type="time" label="From" error={errors.starts_at} />
                            <Input value={data.ends_at} onChange={(v) => setData('ends_at', v)} type="time" label="To" error={errors.ends_at} />
                        </div>
                        {data.type === 'big_room' && <p className="text-xs text-ink-muted">Big rooms need at least 1 week's notice (earliest {date(earliestBigRoom)}).</p>}
                        <Input value={data.purpose} onChange={(v) => setData('purpose', v)} label="Purpose" placeholder="Membership drive booth" error={errors.purpose} />
                        {data.type === 'la_place' && <Input value={data.items} onChange={(v) => setData('items', v)} label="Items sold or promoted" placeholder="Club T-shirts, flyers" error={errors.items} />}
                        <Textarea value={data.activity} onChange={(v) => setData('activity', v)} label="Activity" rows={3} placeholder="What will happen at the venue?" error={errors.activity} />
                        {data.type === 'la_place' && (
                            <div className="grid gap-2 rounded-card bg-surface-muted p-4 text-sm">
                                <strong>La Place table rules (SLD SOP)</strong>
                                <ol className="list-decimal pl-5">
                                    {rules.map((r) => (
                                        <li key={r}>{r}</li>
                                    ))}
                                </ol>
                                <Checkbox checked={data.rules_accepted} onChange={(v) => setData('rules_accepted', v)} label="We will follow these rules" />
                                {errors.rules_accepted && <p className="text-xs text-danger">{errors.rules_accepted}</p>}
                            </div>
                        )}
                        <div>
                            <Button type="submit" loading={form.processing}>
                                Send to SLD
                            </Button>
                        </div>
                    </form>
                </Card>
                <Card title="Requests">
                    <ul className="divide-y divide-line">
                        {bookings.map((b) => (
                            <li key={b.id} className="flex flex-wrap items-start justify-between gap-3 py-3 text-sm">
                                <span className="min-w-0 flex-1">
                                    {b.summary}
                                    {b.sld_note && <span className="block text-xs text-ink-muted">SLD: {b.sld_note}</span>}
                                </span>
                                <Badge tone={tone(b.status)}>{b.status}</Badge>
                            </li>
                        ))}
                        {!bookings.length && <li className="py-3 text-sm text-ink-muted">No requests yet.</li>}
                    </ul>
                </Card>
            </div>
        </AppLayout>
    );
}
