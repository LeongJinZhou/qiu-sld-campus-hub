import Avatar from '@react/Components/ui/Avatar';
import Badge from '@react/Components/ui/Badge';
import EmptyState from '@react/Components/ui/EmptyState';
import Icon from '@react/Components/ui/Icon';
import AppLayout from '@react/Layouts/AppLayout';
import { dateTime, rm } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type Type = 'all' | 'clubs' | 'events' | 'people';
interface SearchProps {
    q: string;
    type: Type;
    clubs: { slug: string; name: string; logo_url: string | null; category: string | null }[];
    events: { slug: string; name: string; club: string; starts_at: string; venue: string | null; poster_url: string | null; past: boolean; fee_sen: number; members_only: boolean }[];
    people: { name: string; photo_url: string | null; url: string; subtitle: string }[];
}
const TABS: { key: Type; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'clubs', label: 'Clubs' },
    { key: 'events', label: 'Events' },
    { key: 'people', label: 'People' },
];

export default function Index({ q: initial, type, clubs, events, people }: SearchProps) {
    const [q, setQ] = useState(initial);
    const go = (t: Type = type, text = q): void =>
        router.get('/search', { ...(text.trim() ? { q: text.trim() } : {}), ...(t !== 'all' ? { type: t } : {}) }, { preserveState: true, replace: true, preserveScroll: true });
    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => go(type, q), 300);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const show = (t: Exclude<Type, 'all'>): boolean => type === 'all' || type === t;
    const nothing = !clubs.length && !events.length && !people.length;
    const peopleHint = show('people') && q.trim().length < 2;
    const heading = 'text-sm font-semibold uppercase tracking-wide text-ink-muted';

    return (
        <AppLayout>
            <Head title="Search" />
            <div className="grid gap-5">
                <div className="sticky top-0 z-10 -mx-4 grid gap-3 bg-surface-muted/90 px-4 pb-3 pt-1 backdrop-blur md:static md:mx-0 md:bg-transparent md:px-0 md:backdrop-blur-none">
                    <label className="flex h-12 items-center gap-3 rounded-full border border-line bg-surface px-4 shadow-card focus-within:ring-2 focus-within:ring-brand-cyan">
                        <Icon name="search" size={20} className="text-ink-muted" />
                        <input value={q} onChange={(e) => setQ(e.target.value)} type="search" placeholder="Search clubs, events and people" className="h-full flex-1 bg-transparent text-[15px] outline-none" aria-label="Search" autoFocus />
                    </label>
                    <div className="flex gap-2 overflow-x-auto [scrollbar-width:none]" role="tablist">
                        {TABS.map((t) => (
                            <button
                                key={t.key}
                                type="button"
                                role="tab"
                                aria-selected={type === t.key}
                                className={`whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-medium transition ${type === t.key ? 'bg-ink text-white' : 'border border-line bg-surface hover:bg-surface-muted'}`}
                                onClick={() => go(t.key)}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>
                </div>
                {nothing && !peopleHint && <EmptyState title="Nothing found" description={`No clubs, events or people match “${q}”.`} />}

                {show('people') && (people.length > 0 || type === 'people') && (
                    <section className="grid gap-3">
                        {type === 'all' && <h2 className={heading}>People</h2>}
                        {peopleHint && <p className="text-sm text-ink-muted">Type at least 2 letters of a name to find students and staff.</p>}
                        <ul className="grid gap-2 sm:grid-cols-2">
                            {people.map((p) => (
                                <li key={p.url}>
                                    <a href={p.url} className="flex items-center gap-3 rounded-card border border-line bg-surface p-3 transition hover:shadow-card">
                                        <Avatar src={p.photo_url} name={p.name} size="sm" />
                                        <span className="grid min-w-0">
                                            <strong className="truncate text-sm">{p.name}</strong>
                                            <span className="truncate text-xs text-ink-muted">{p.subtitle}</span>
                                        </span>
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {show('events') && events.length > 0 && (
                    <section className="grid gap-3">
                        {type === 'all' && <h2 className={heading}>{q ? 'Events' : 'Upcoming events'}</h2>}
                        <ul className="grid gap-3 sm:grid-cols-2">
                            {events.map((e) => (
                                <li key={e.slug}>
                                    <Link href={`/events/${e.slug}`} className={`flex items-center gap-3 rounded-card border border-line bg-surface p-3 transition hover:shadow-card ${e.past ? 'opacity-70' : ''}`}>
                                        {e.poster_url ? (
                                            <img src={e.poster_url} alt="" className="size-16 shrink-0 rounded-control object-cover object-top" loading="lazy" />
                                        ) : (
                                            <span className="grid size-16 shrink-0 place-items-center rounded-control bg-gradient-to-br from-brand-cyan to-brand-blue text-white">
                                                <Icon name="calendar" size={24} />
                                            </span>
                                        )}
                                        <span className="grid min-w-0 flex-1 gap-0.5 text-sm">
                                            <strong className="truncate">{e.name}</strong>
                                            <span className="truncate text-xs text-ink-muted">
                                                {dateTime(e.starts_at)}
                                                {e.venue && ` · ${e.venue}`}
                                            </span>
                                            <span className="truncate text-xs text-ink-muted">
                                                {e.club}
                                                {e.members_only && (
                                                    <>
                                                        {' · '}
                                                        <span className="font-medium text-warning">Members only</span>
                                                    </>
                                                )}
                                            </span>
                                        </span>
                                        {e.past ? <Badge>Past</Badge> : <Badge tone={e.fee_sen ? 'warning' : 'success'}>{e.fee_sen ? rm(e.fee_sen) : 'Free'}</Badge>}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {show('clubs') && clubs.length > 0 && (
                    <section className="grid gap-3">
                        {type === 'all' && (
                            <div className="flex items-center justify-between">
                                <h2 className={heading}>{q ? 'Clubs' : 'Discover clubs'}</h2>
                                <button type="button" className="text-sm font-medium text-brand-blue" onClick={() => go('clubs')}>
                                    See all
                                </button>
                            </div>
                        )}
                        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {clubs.map((club) => (
                                <li key={club.slug}>
                                    <Link href={`/clubs/${club.slug}`} className="group grid overflow-hidden rounded-card border border-line bg-surface text-center shadow-card transition hover:-translate-y-0.5 hover:shadow-pop">
                                        <span className="h-14 bg-gradient-to-r from-brand-yellow via-brand-orange to-brand-magenta" />
                                        <span className="-mt-9 grid justify-items-center gap-1.5 px-3 pb-4">
                                            <span className="rounded-full bg-surface p-0.5">
                                                <Avatar src={club.logo_url} name={club.name} size="lg" ring />
                                            </span>
                                            <span className="line-clamp-2 text-sm font-semibold">{club.name}</span>
                                            {club.category && <span className="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] text-ink-muted">{club.category}</span>}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
                {type === 'clubs' && (
                    <p className="text-center text-sm text-ink-muted">
                        Can't find your club?{' '}
                        <Link href="/clubs-apply" className="font-medium text-brand-blue hover:underline">
                            Start a new club
                        </Link>
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
