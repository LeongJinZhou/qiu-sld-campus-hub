import Badge from '@react/Components/ui/Badge';
import Icon from '@react/Components/ui/Icon';
import AppLayout from '@react/Layouts/AppLayout';
import { dateTime } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface ClubEvent {
    slug: string;
    name: string;
    venue: string | null;
    poster_url: string | null;
    starts_at: string;
    ends_at: string;
    status: string;
    registered: number;
}
type Tab = 'ongoing' | 'upcoming' | 'past';
interface ClubEventsProps {
    club: { slug: string; name: string };
    ongoing: ClubEvent[];
    upcoming: ClubEvent[];
    past: ClubEvent[];
    years: number[];
    year: number;
}
const TABS: { key: Tab; label: string }[] = [
    { key: 'ongoing', label: 'Ongoing' },
    { key: 'upcoming', label: 'Upcoming' },
    { key: 'past', label: 'Past' },
];
const EMPTY: Record<Tab, string> = { ongoing: 'Nothing is happening right now.', upcoming: 'No events coming up.', past: 'No past events in this year.' };
const STATUS: Record<string, { tone: 'warning' | 'info'; label: string }> = { in_review: { tone: 'info', label: 'In approval' }, returned: { tone: 'warning', label: 'Returned' } };

/** Every event of a club the person runs: happening now, coming up, and past events by year. */
export default function ClubEvents({ club, ongoing, upcoming, past, years, year }: ClubEventsProps) {
    const [tab, setTab] = useState<Tab>(ongoing.length ? 'ongoing' : upcoming.length ? 'upcoming' : 'past');
    const lists: Record<Tab, ClubEvent[]> = { ongoing, upcoming, past };
    const list = lists[tab];
    const pickYear = (value: string): void => router.get(`/clubs/${club.slug}/events`, { year: value }, { preserveScroll: true, preserveState: true, only: ['past', 'year'] });

    return (
        <AppLayout>
            <Head title={`Events · ${club.name}`} />
            <div className="grid gap-5">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold">Events · {club.name}</h1>
                        <Link href="/activities" className="text-sm text-brand-blue">
                            Back to Activities
                        </Link>
                    </div>
                    <Link href={`/clubs/${club.slug}/events/create`} className="inline-flex items-center gap-1.5 rounded-control bg-ink px-3 py-2 text-sm font-semibold text-white">
                        <Icon name="plus" size={16} /> New event
                    </Link>
                </header>
                <div className="grid grid-cols-3 gap-1 rounded-control bg-surface-muted p-1 text-sm font-medium" role="tablist">
                    {TABS.map((t) => (
                        <button key={t.key} type="button" role="tab" aria-selected={tab === t.key} className={`rounded-control px-2 py-2 transition ${tab === t.key ? 'bg-surface shadow-card' : 'text-ink-muted'}`} onClick={() => setTab(t.key)}>
                            {t.label}
                            {t.key !== 'past' && lists[t.key].length > 0 && <span className="ml-1 rounded-full bg-ink/10 px-1.5 text-xs tabular-nums">{lists[t.key].length}</span>}
                        </button>
                    ))}
                </div>
                {tab === 'past' && (
                    <label className="flex items-center gap-2 text-sm">
                        <span className="font-medium">Year</span>
                        <select value={year} className="h-10 rounded-control border border-line bg-surface px-3" disabled={!years.length} onChange={(e) => pickYear(e.target.value)}>
                            {(years.length ? years : [year]).map((y) => (
                                <option key={y} value={y}>
                                    {y}
                                </option>
                            ))}
                        </select>
                        <span className="text-ink-muted">
                            {past.length} event{past.length === 1 ? '' : 's'}
                        </span>
                    </label>
                )}
                <ul className="grid gap-2">
                    {list.map((e) => (
                        <li key={e.slug}>
                            <Link href={`/events/${e.slug}`} className="flex items-center gap-3 rounded-card border border-line bg-surface p-3 shadow-card transition hover:shadow-pop">
                                {e.poster_url ? (
                                    <img src={e.poster_url} alt="" className="size-14 shrink-0 rounded-control object-cover object-top" />
                                ) : (
                                    <span className="grid size-14 shrink-0 place-items-center rounded-control bg-surface-muted">
                                        <Icon name="calendar" size={22} />
                                    </span>
                                )}
                                <span className="grid min-w-0 flex-1 text-sm">
                                    <span className="flex flex-wrap items-center gap-2">
                                        <strong className="truncate">{e.name}</strong>
                                        {tab === 'ongoing' ? <Badge tone="danger">Live</Badge> : STATUS[e.status] && <Badge tone={STATUS[e.status]!.tone}>{STATUS[e.status]!.label}</Badge>}
                                    </span>
                                    <span className="truncate text-xs text-ink-muted">
                                        {dateTime(e.starts_at)}
                                        {e.venue && ` · ${e.venue}`}
                                    </span>
                                    <span className="text-xs text-ink-muted">{e.registered} registered</span>
                                </span>
                                <Icon name="send" size={16} className="text-ink-muted" />
                            </Link>
                        </li>
                    ))}
                </ul>
                {!list.length && <p className="rounded-card border border-dashed border-line p-6 text-center text-sm text-ink-muted">{EMPTY[tab]}</p>}
            </div>
        </AppLayout>
    );
}
