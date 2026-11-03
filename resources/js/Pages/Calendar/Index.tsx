import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Icon from '@/Components/ui/Icon';
import Modal from '@/Components/ui/Modal';
import AppLayout from '@/Layouts/AppLayout';
import { monthDays, TZ, yearMonths, type Filter } from '@/lib/calendar';
import { rm } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface CalEvent {
    slug: string;
    name: string;
    club: string;
    club_slug: string;
    starts_at: string;
    ends_at: string;
    venue: string | null;
    poster_url: string | null;
    fee_sen: number;
    registration_open: boolean;
    registered: boolean;
    followed: boolean;
    google_url: string;
}
// Meetings, weekly activities, weekly-report due dates and AGMs for the clubs this person belongs to.
interface CalItem {
    key: string;
    kind: 'meeting' | 'activity' | 'report_due' | 'agm';
    title: string;
    club: string;
    starts_at: string;
    ends_at: string;
    url: string;
}
interface CalendarProps {
    month: string;
    today: string;
    events: CalEvent[];
    years: number[];
    feedUrl: string;
    items?: CalItem[];
    view?: 'month' | 'year';
}
const ITEM_ICON: Record<CalItem['kind'], 'users' | 'calendar' | 'file' | 'award'> = { meeting: 'users', activity: 'calendar', report_due: 'file', agm: 'award' };
const time = (iso: string): string => new Date(iso).toLocaleTimeString('en-MY', { hour: 'numeric', minute: '2-digit', timeZone: TZ });
const tone = (e: CalEvent): string => (e.registered ? 'bg-success text-white' : e.followed ? 'bg-brand-blue text-white' : 'bg-brand-magenta/15 text-brand-magenta');
const FILTERS: { k: Filter; l: string }[] = [
    { k: 'all', l: 'All events' },
    { k: 'followed', l: 'Clubs I follow' },
    { k: 'mine', l: 'My registrations' },
];

/** One calendar: approved events plus my meetings, weekly activities, report due dates and AGMs; month or whole year. */
export default function Index({ month, today, events, years, feedUrl, items = [], view = 'month' }: CalendarProps) {
    const [year, mon] = month.split('-').map(Number) as [number, number];
    const title = new Date(year, mon - 1, 1).toLocaleDateString('en-MY', { month: 'long', year: 'numeric' });
    const [filter, setFilter] = useState<Filter>('all');
    const days = monthDays(month, events, items, filter);
    const months = yearMonths(year, events, items, filter);
    const go = (m: string, v: 'month' | 'year' = view): void => router.get('/calendar', { month: m, ...(v === 'year' ? { view: v } : {}) }, { preserveState: true, preserveScroll: true, replace: true, only: ['month', 'events', 'items', 'view'] });
    const shift = (by: number): void => {
        const d = new Date(year, mon - 1 + by, 1);
        go(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    };
    const thisMonth = today.slice(0, 7);

    // Swipe left or right on the month grid to change month.
    const grid = useRef<HTMLDivElement>(null);
    const swipeStart = useRef<number | null>(null);
    const [selectedDay, setSelectedDay] = useState<string | null>(today.startsWith(month) ? today : null);
    useEffect(() => setSelectedDay(today.startsWith(month) ? today : null), [month, today]);
    const selected = days.find((d) => d.key === selectedDay);
    const dayEvents = selected?.events ?? [];
    const dayItems = selected?.items ?? [];
    const [open, setOpen] = useState<CalEvent | null>(null);
    const [subscribing, setSubscribing] = useState(false);
    const [copied, setCopied] = useState(false);
    const googleSubscribe = `https://calendar.google.com/calendar/r?cid=${encodeURIComponent(feedUrl.replace(/^https?:/, 'webcal:'))}`;
    const viewButton = (v: 'month' | 'year', label: string) => (
        <button type="button" role="radio" aria-checked={view === v} className={`rounded-[0.55rem] px-3 py-1.5 ${view === v ? 'bg-ink text-white' : 'text-ink-muted'}`} onClick={() => go(month, v)}>
            {label}
        </button>
    );

    return (
        <AppLayout wide>
            <Head title="Calendar" />
            <div className="grid gap-4">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <button type="button" className="grid size-9 place-items-center rounded-full border border-line bg-surface transition hover:bg-surface-muted" aria-label="Previous month" onClick={() => shift(-1)}>
                            ‹
                        </button>
                        <h1 className="min-w-40 text-center text-xl font-bold">{title}</h1>
                        <button type="button" className="grid size-9 place-items-center rounded-full border border-line bg-surface transition hover:bg-surface-muted" aria-label="Next month" onClick={() => shift(1)}>
                            ›
                        </button>
                        {month !== thisMonth && (
                            <Button size="sm" variant="ghost" onClick={() => go(thisMonth)}>
                                Today
                            </Button>
                        )}
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            type="month"
                            value={month}
                            min={`${years[0]}-01`}
                            max={`${years.at(-1)}-12`}
                            className="h-9 rounded-control border border-line bg-surface px-2 text-sm"
                            aria-label="Go to month"
                            onChange={(e) => e.target.value && go(e.target.value)}
                        />
                        <div className="flex rounded-control border border-line p-0.5 text-xs font-semibold" role="radiogroup" aria-label="View">
                            {viewButton('month', 'Month')}
                            {viewButton('year', 'Year')}
                        </div>
                        <Button size="sm" variant="secondary" onClick={() => setSubscribing(true)}>
                            <Icon name="calendar" size={16} /> Add to Google Calendar
                        </Button>
                    </div>
                </header>
                <div className="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none]">
                    {FILTERS.map((f) => (
                        <button
                            key={f.k}
                            type="button"
                            className={`shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition ${filter === f.k ? 'border-ink bg-ink text-white' : 'border-line bg-surface text-ink-muted hover:bg-surface-muted'}`}
                            onClick={() => setFilter(f.k)}
                        >
                            {f.l}
                        </button>
                    ))}
                </div>
                {view === 'year' ? (
                    <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" aria-label="Year">
                        {months.map((m) => (
                            <button key={m.key} type="button" className="grid gap-1 rounded-card border border-line bg-surface p-3 text-left shadow-card transition hover:shadow-pop" onClick={() => go(m.key, 'month')}>
                                <span className="font-semibold">{m.label}</span>
                                <span className="grid grid-cols-7 gap-0.5 text-center text-[10px]">
                                    {m.cells.map((c, i) => (
                                        <span key={i} className={`grid aspect-square place-items-center rounded ${c && c.n ? 'bg-brand-magenta/80 font-bold text-white' : c && c.key === today ? 'ring-1 ring-brand-magenta' : 'text-ink-muted'}`}>
                                            {c?.day ?? ''}
                                        </span>
                                    ))}
                                </span>
                                <span className="text-xs text-ink-muted">{m.cells.reduce((s, c) => s + (c?.n ?? 0), 0)} things on</span>
                            </button>
                        ))}
                    </section>
                ) : (
                    <div
                        ref={grid}
                        className="overflow-hidden rounded-card border border-line bg-surface shadow-card"
                        data-no-motion
                        onTouchStart={(e) => (swipeStart.current = e.touches[0]?.clientX ?? null)}
                        onTouchEnd={(e) => {
                            const startX = swipeStart.current;
                            const endX = e.changedTouches[0]?.clientX;
                            swipeStart.current = null;
                            if (startX === null || endX === undefined || Math.abs(endX - startX) < 50) return;
                            shift(endX < startX ? 1 : -1);
                        }}
                    >
                        <div className="grid grid-cols-7 border-b border-line bg-surface-muted text-center text-[11px] font-semibold uppercase tracking-wide text-ink-muted">
                            {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((d) => (
                                <span key={d} className="py-2">
                                    {d}
                                </span>
                            ))}
                        </div>
                        <div key={month} className="fade-up grid grid-cols-7">
                            {days.map((d) => (
                                <button
                                    key={d.key}
                                    type="button"
                                    className={`flex min-h-16 flex-col gap-1 border-b border-r border-line p-1 text-left transition sm:min-h-28 sm:p-1.5 [&:nth-child(7n)]:border-r-0 ${d.inMonth ? '' : 'bg-surface-muted/60 text-ink-muted'} ${selectedDay === d.key ? 'bg-brand-cyan/10' : 'hover:bg-surface-muted'}`}
                                    aria-label={`${d.key}, ${d.events.length} events`}
                                    onClick={() => setSelectedDay(d.key)}
                                >
                                    <span className={`grid size-6 place-items-center rounded-full text-xs font-semibold ${d.key === today ? 'bg-brand-magenta text-white' : ''}`}>{d.day}</span>
                                    <span className="hidden gap-0.5 sm:grid">
                                        {d.events.slice(0, 3).map((e) => (
                                            <span
                                                key={e.slug}
                                                className={`truncate rounded px-1 py-0.5 text-[11px] font-medium ${tone(e)}`}
                                                role="link"
                                                onClick={(ev) => {
                                                    ev.stopPropagation();
                                                    setOpen(e);
                                                }}
                                            >
                                                {time(e.starts_at)} {e.name}
                                            </span>
                                        ))}
                                        {d.events.length > 3 && <span className="px-1 text-[11px] text-ink-muted">+{d.events.length - 3} more</span>}
                                        {d.items.slice(0, 2).map((it) => (
                                            <Link
                                                key={it.key}
                                                href={it.url}
                                                className={`flex items-center gap-1 truncate rounded border border-line px-1 py-0.5 text-[11px] font-medium ${it.kind === 'report_due' ? 'text-danger' : 'text-ink'}`}
                                                onClick={(ev) => ev.stopPropagation()}
                                            >
                                                <Icon name={ITEM_ICON[it.kind]} size={11} />
                                                <span className="truncate">{it.title}</span>
                                            </Link>
                                        ))}
                                    </span>
                                    {d.events.length > 0 && (
                                        <span className="flex flex-wrap gap-0.5 sm:hidden">
                                            {d.events.slice(0, 3).map((e) => (
                                                <span key={e.slug} className={`size-1.5 rounded-full ${e.registered ? 'bg-success' : e.followed ? 'bg-brand-blue' : 'bg-brand-magenta'}`} />
                                            ))}
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>
                    </div>
                )}
                <p className="flex flex-wrap gap-3 text-xs text-ink-muted">
                    <span className="flex items-center gap-1">
                        <span className="size-2 rounded-full bg-success" /> Registered
                    </span>
                    <span className="flex items-center gap-1">
                        <span className="size-2 rounded-full bg-brand-blue" /> Club you follow
                    </span>
                    <span className="flex items-center gap-1">
                        <span className="size-2 rounded-full bg-brand-magenta" /> Other events
                    </span>
                    <span className="flex items-center gap-1">
                        <Icon name="users" size={12} /> Meeting
                    </span>
                    <span className="flex items-center gap-1">
                        <Icon name="calendar" size={12} /> Weekly activity
                    </span>
                    <span className="flex items-center gap-1 text-danger">
                        <Icon name="file" size={12} /> Report due
                    </span>
                    <span className="flex items-center gap-1">
                        <Icon name="award" size={12} /> AGM
                    </span>
                </p>
                {selectedDay && view !== 'year' && (
                    <section className="grid gap-2">
                        <h2 className="font-semibold">{new Date(selectedDay).toLocaleDateString('en-MY', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</h2>
                        {!dayEvents.length && !dayItems.length && <p className="rounded-card border border-dashed border-line p-4 text-sm text-ink-muted">Nothing on this day.</p>}
                        {dayItems.length > 0 && (
                            <ul className="grid gap-2">
                                {dayItems.map((it) => (
                                    <li key={it.key}>
                                        <Link href={it.url} className="flex items-center gap-3 rounded-card border border-line bg-surface p-3 text-sm shadow-card transition hover:shadow-pop">
                                            <span className={`grid size-10 shrink-0 place-items-center rounded-lg ${it.kind === 'report_due' ? 'bg-danger/10 text-danger' : 'bg-surface-muted text-ink'}`}>
                                                <Icon name={ITEM_ICON[it.kind]} size={18} />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <strong className="block truncate">{it.title}</strong>
                                                <span className="block truncate text-xs text-ink-muted">
                                                    {it.kind === 'report_due' ? 'Due today' : `${time(it.starts_at)} – ${time(it.ends_at)}`} · {it.club}
                                                </span>
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <ul className="relative grid gap-2">
                            {dayEvents.map((e) => (
                                <li key={e.slug} className="fade-up">
                                    <button type="button" className="flex w-full items-center gap-3 rounded-card border border-line bg-surface p-3 text-left shadow-card transition hover:shadow-pop" onClick={() => setOpen(e)}>
                                        {e.poster_url ? (
                                            <img src={e.poster_url} alt="" className="size-14 shrink-0 rounded-lg object-cover" loading="lazy" />
                                        ) : (
                                            <span className="grid size-14 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-brand-yellow to-brand-magenta text-white">
                                                <Icon name="calendar" size={22} />
                                            </span>
                                        )}
                                        <span className="min-w-0 flex-1 text-sm">
                                            <strong className="block truncate">{e.name}</strong>
                                            <span className="block truncate text-xs text-ink-muted">
                                                {time(e.starts_at)} – {time(e.ends_at)} · {e.club}
                                                {e.venue && ` · ${e.venue}`}
                                            </span>
                                        </span>
                                        {e.registered && <Badge tone="success">Going</Badge>}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
            <Modal open={open !== null} onClose={() => setOpen(null)} title={open?.name}>
                {open && (
                    <div className="grid gap-3 text-sm">
                        {open.poster_url && <img src={open.poster_url} alt="" className="max-h-56 w-full rounded-card object-cover object-top" />}
                        <p className="flex items-center gap-2">
                            <Icon name="clock" size={16} className="text-ink-muted" />
                            {new Date(open.starts_at).toLocaleString('en-MY', { dateStyle: 'full', timeStyle: 'short', timeZone: TZ })} – {time(open.ends_at)}
                        </p>
                        {open.venue && (
                            <p className="flex items-center gap-2">
                                <Icon name="pin" size={16} className="text-ink-muted" />
                                {open.venue}
                            </p>
                        )}
                        <p className="flex items-center gap-2">
                            <Icon name="users" size={16} className="text-ink-muted" />
                            <Link href={`/clubs/${open.club_slug}`} className="font-medium hover:underline">
                                {open.club}
                            </Link>
                        </p>
                        {!!open.fee_sen && <p className="text-ink-muted">Fee {rm(open.fee_sen)}</p>}
                        <div className="grid grid-cols-2 gap-2">
                            <Link href={`/events/${open.slug}`}>
                                <Button block>{open.registered ? 'View my ticket' : open.registration_open ? 'Details & register' : 'Details'}</Button>
                            </Link>
                            <a href={open.google_url} target="_blank" rel="noopener">
                                <Button block variant="secondary">
                                    Add to Google
                                </Button>
                            </a>
                        </div>
                    </div>
                )}
            </Modal>
            <Modal open={subscribing} onClose={() => setSubscribing(false)} title="Add all campus events to Google Calendar">
                <div className="grid gap-3 text-sm">
                    <p className="text-ink-muted">Subscribe once and every approved event appears in your Google Calendar, kept up to date automatically (Google refreshes it every few hours).</p>
                    <a href={googleSubscribe} target="_blank" rel="noopener">
                        <Button block>
                            <Icon name="calendar" size={16} /> Open Google Calendar to subscribe
                        </Button>
                    </a>
                    <p className="text-xs text-ink-muted">Or in Google Calendar: Other calendars → ＋ → From URL, and paste this private link:</p>
                    <div className="flex gap-2">
                        <input value={feedUrl} readOnly className="h-9 min-w-0 flex-1 rounded-control border border-line bg-surface-muted px-2 font-mono text-xs" aria-label="Calendar link" onFocus={(e) => e.target.select()} />
                        <Button size="sm" variant="secondary" onClick={() => void navigator.clipboard.writeText(feedUrl).then(() => setCopied(true))}>
                            {copied ? 'Copied' : 'Copy'}
                        </Button>
                    </div>
                </div>
            </Modal>
        </AppLayout>
    );
}
