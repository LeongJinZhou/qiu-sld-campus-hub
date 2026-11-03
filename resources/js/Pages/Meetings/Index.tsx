import ApprovalTracker from '@/Components/forms/ApprovalTracker';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import EmptyState from '@/Components/ui/EmptyState';
import Icon from '@/Components/ui/Icon';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import AppLayout from '@/Layouts/AppLayout';
import { dateTime } from '@/lib/format';
import type { ApprovalProgress } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface MeetingRow {
    id: number;
    title: string;
    kind: string;
    starts_at: string;
    venue: string | null;
    meet_link: string | null;
    upcoming: boolean;
    audience: string | null;
    pax: number | null;
    minutes: ApprovalProgress | null;
}
interface MeetingsProps {
    club: { slug: string; name: string };
    calendarConnected: boolean;
    kinds: Record<string, string>;
    audiences: Record<string, string>;
    meetings: MeetingRow[];
}

export default function Index({ club, calendarConnected, kinds, audiences, meetings }: MeetingsProps) {
    // Committee meeting: committee + advisors only. Weekly activity: pick who is invited, and give the pax and location.
    const form = useForm({ kind: 'committee', title: '', starts_at: '', ends_at: '', venue: '', online: calendarConnected, audience: 'members', pax: '' as string });
    const { data, setData, errors } = form;
    const isActivity = data.kind === 'activity';
    const inviteLabel = isActivity && data.audience === 'members' ? 'Create & invite all members' : 'Create & invite committee';
    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.post(`/clubs/${club.slug}/meetings`);
    };

    return (
        <AppLayout wide>
            <Head title={`Meetings · ${club.name}`} />
            <div className="grid gap-6 lg:grid-cols-[1fr_24rem]">
                <div className="grid content-start gap-4">
                    <header>
                        <h1 className="text-xl font-bold">Meetings &amp; activities · {club.name}</h1>
                        <span className="flex flex-wrap gap-4 text-sm">
                            <Link href={`/clubs/${club.slug}`} className="text-brand-blue">
                                Back to club
                            </Link>
                            <Link href={`/clubs/${club.slug}/meetings/report`} className="text-brand-blue">
                                Meetings report
                            </Link>
                        </span>
                    </header>
                    {!meetings.length && <EmptyState title="No meetings yet" description="Create one to invite the committee and keep minutes." />}
                    <ul className="grid gap-3">
                        {meetings.map((m) => (
                            <li key={m.id}>
                                <Link href={`/meetings/${m.id}`} className="grid gap-2 rounded-card border border-line bg-surface p-4 shadow-card transition hover:shadow-pop">
                                    <span className="flex flex-wrap items-center justify-between gap-2">
                                        <strong>{m.title}</strong>
                                        <span className="flex flex-wrap gap-2">
                                            <Badge tone="info">{kinds[m.kind]}</Badge>
                                            {m.audience && (
                                                <Badge>
                                                    {m.audience}
                                                    {m.pax ? ` · ${m.pax} pax` : ''}
                                                </Badge>
                                            )}
                                            {m.upcoming && <Badge tone="success">Upcoming</Badge>}
                                        </span>
                                    </span>
                                    <span className="flex items-center gap-2 text-sm text-ink-muted">
                                        <Icon name="calendar" size={16} />
                                        {dateTime(m.starts_at)} · {m.meet_link ? 'Google Meet' : m.venue}
                                    </span>
                                    {m.minutes && m.minutes.total ? <ApprovalTracker progress={m.minutes} compact /> : <span className="text-xs text-ink-muted">Minutes not submitted yet</span>}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
                <Card className="self-start" title="New meeting">
                    <form className="grid gap-3" onSubmit={submit}>
                        <Select value={data.kind} onChange={(v) => setData('kind', v)} label="Type" options={Object.entries(kinds).map(([value, label]) => ({ value, label }))} />
                        {isActivity ? (
                            <Select value={data.audience} onChange={(v) => setData('audience', v)} label="Invite" options={Object.entries(audiences).map(([value, label]) => ({ value, label }))} error={errors.audience} />
                        ) : (
                            <p className="rounded-control bg-surface-muted px-3 py-2 text-xs text-ink-muted">Committee meetings are for the committee and advisors only.</p>
                        )}
                        <Input value={data.title} onChange={(v) => setData('title', v)} label="Title" placeholder="Weekly committee meeting" error={errors.title} />
                        <Input value={data.starts_at} onChange={(v) => setData('starts_at', v)} type="datetime-local" label="Starts" error={errors.starts_at} />
                        <Input value={data.ends_at} onChange={(v) => setData('ends_at', v)} type="datetime-local" label="Ends" error={errors.ends_at} />
                        <Checkbox checked={data.online} onChange={(v) => setData('online', v)} label="Online — create a Google Meet link" />
                        {data.online && !calendarConnected && (
                            <p className="rounded-control bg-brand-cyan/10 p-3 text-sm">
                                Meet links are created from your own QIU Google Calendar.{' '}
                                <a href="/google/calendar/connect" className="font-semibold text-brand-blue">
                                    Connect Google Calendar
                                </a>
                            </p>
                        )}
                        {(!data.online || isActivity) && <Input value={data.venue} onChange={(v) => setData('venue', v)} label={isActivity ? 'Location' : 'Venue'} required={isActivity} error={errors.venue} />}
                        {isActivity && <Input value={data.pax} onChange={(v) => setData('pax', v)} type="number" label="Expected pax" required min={1} error={errors.pax} />}
                        {isActivity && <p className="text-xs text-ink-muted">Weekly activity: attendees earn CCA (1 hour = 1 CCA) once the minutes and attendance are approved.</p>}
                        <Button type="submit" loading={form.processing} disabled={data.online && !calendarConnected}>
                            {inviteLabel}
                        </Button>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
