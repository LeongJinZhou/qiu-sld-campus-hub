import ApprovalTracker from '@/Components/forms/ApprovalTracker';
import TicketCard from '@/Components/tickets/TicketCard';
import Avatar from '@/Components/ui/Avatar';
import Badge from '@/Components/ui/Badge';
import EmptyState from '@/Components/ui/EmptyState';
import Icon, { type IconName } from '@/Components/ui/Icon';
import AppLayout from '@/Layouts/AppLayout';
import { dateTime } from '@/lib/format';
import type { ApprovalProgress, Ticket } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface EventCard {
    slug: string;
    name: string;
    club: string | null;
    starts_at: string;
    venue: string | null;
    poster_url: string | null;
    live: boolean;
    past: boolean;
    registered: number;
    unpaid: number;
}
type Column = 'draft' | 'submitted' | 'approved' | 'rejected';
interface RequestItem {
    id: number;
    type: string;
    title: string;
    event: { slug: string; name: string } | null;
    status: string;
    column: Column;
    progress: ApprovalProgress | null;
    url: string;
    updated_at: string | null;
    removed_on: string | null;
}
interface MyClub {
    slug: string;
    name: string;
    logo_url: string | null;
    category: string | null;
    role: string | null;
    manages: boolean;
    events: EventCard[];
    membership: { status: string; expires_on: string | null } | null;
    requests: RequestItem[];
    can_delete: boolean;
}
// Everything a committee member or advisor runs, one tap away.
const tools = (slug: string): { label: string; icon: IconName; href: string }[] => [
    { label: 'New event', icon: 'plus', href: `/clubs/${slug}/events/create` },
    { label: 'All events', icon: 'calendar', href: `/clubs/${slug}/events` },
    { label: 'Finance', icon: 'file', href: `/clubs/${slug}/finance` },
    { label: 'Members', icon: 'users', href: `/clubs/${slug}/members` },
    { label: 'Participants', icon: 'ticket', href: `/clubs/${slug}/participants` },
    { label: 'Meetings', icon: 'message', href: `/clubs/${slug}/meetings` },
    { label: 'AGM', icon: 'award', href: `/clubs/${slug}/agm` },
    { label: 'Venues', icon: 'pin', href: `/clubs/${slug}/venues` },
];
const statusTone = (s: string): 'success' | 'warning' | 'info' | 'neutral' => (s === 'active' ? 'success' : s === 'grace' || s === 'pending' ? 'warning' : s === 'alumni' ? 'info' : 'neutral');
const STATUS_LABEL: Record<string, string> = { active: 'Member', grace: 'Renew soon', pending: 'Payment pending', alumni: 'Alumni' };
// Requests board per club: Draft → Submitted → Approved, or Rejected (cleared after 30 days or by hand).
const COLUMNS: { key: Column; label: string; empty: string }[] = [
    { key: 'draft', label: 'Draft', empty: 'Nothing started and unsubmitted.' },
    { key: 'submitted', label: 'Submitted', empty: 'Nothing waiting for approval.' },
    { key: 'approved', label: 'Approved', empty: 'Nothing approved in the last 30 days.' },
    { key: 'rejected', label: 'Rejected', empty: 'Nothing rejected.' },
];
const ticketHref = (t: Ticket): string => (t.status === 'unpaid' ? `/events/${t.event.slug}/pay` : (t.url ?? `/events/${t.event.slug}`));
const heading = 'flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-ink-muted';

/** The student's hub: tickets, events they help at, the clubs they run (with requests and tools), and clubs they joined. */
export default function Index({ tickets, helping, clubs }: { tickets: Ticket[]; helping: EventCard[]; clubs: MyClub[] }) {
    const managed = clubs.filter((c) => c.manages);
    const joined = clubs.filter((c) => !c.manages);
    const upcoming = tickets.filter((t) => !t.event.past);
    const past = tickets.filter((t) => t.event.past);
    const [tab, setTab] = useState<Record<string, Column>>({});
    const inColumn = (c: MyClub, col: Column): RequestItem[] => c.requests.filter((r) => r.column === col);
    const tabOf = (c: MyClub): Column => tab[c.slug] ?? (c.requests.some((r) => r.column === 'submitted') ? 'submitted' : c.requests.some((r) => r.column === 'draft') ? 'draft' : 'submitted');
    const removeRequest = (r: RequestItem): void => {
        if (confirm(`Delete the rejected ${r.type.toLowerCase()} "${r.title}"? This cannot be undone.`)) router.delete(`/documents/${r.id}/rejected`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Activities" />
            <div className="grid gap-8">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold">Activities</h1>
                        <p className="text-sm text-ink-muted">Your tickets, the clubs you run and the clubs you've joined.</p>
                    </div>
                    <Link href="/calendar" className="inline-flex items-center gap-1.5 rounded-control border border-line bg-surface px-3 py-2 text-sm font-semibold shadow-card transition hover:-translate-y-0.5">
                        <Icon name="calendar" size={16} /> Calendar
                    </Link>
                </header>

                <section className="grid gap-3">
                    <h2 className={heading}>
                        <Icon name="ticket" size={16} /> My tickets
                    </h2>
                    {!upcoming.length && (
                        <div className="rounded-card border border-dashed border-line p-5 text-center text-sm text-ink-muted">
                            No upcoming events.{' '}
                            <Link href="/search?type=events" className="font-medium text-brand-blue">
                                Find one to join
                            </Link>
                        </div>
                    )}
                    <ul className="grid gap-3 md:grid-cols-2">
                        {upcoming.map((t) => (
                            <li key={t.event.slug}>
                                <Link href={ticketHref(t)} className="block transition hover:-translate-y-0.5">
                                    <TicketCard ticket={t} compact />
                                </Link>
                            </li>
                        ))}
                    </ul>
                    {past.length > 0 && (
                        <details className="text-sm">
                            <summary className="cursor-pointer text-ink-muted">Past tickets ({past.length})</summary>
                            <ul className="mt-3 grid gap-3 opacity-80 md:grid-cols-2">
                                {past.map((t) => (
                                    <li key={t.event.slug}>
                                        <Link href={ticketHref(t)}>
                                            <TicketCard ticket={t} compact />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </details>
                    )}
                </section>

                {helping.length > 0 && (
                    <section className="grid gap-3">
                        <h2 className={heading}>
                            <Icon name="scan" size={16} /> Helping at
                        </h2>
                        <ul className="grid gap-2 md:grid-cols-2">
                            {helping.map((e) => (
                                <li key={e.slug}>
                                    <Link href={`/events/${e.slug}/attendance`} className="flex items-center gap-3 rounded-card border border-line bg-surface p-3 shadow-card transition hover:shadow-pop">
                                        <span className="grid size-11 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-cyan to-brand-blue text-white">
                                            <Icon name="scan" size={20} />
                                        </span>
                                        <span className="grid min-w-0 flex-1 text-sm">
                                            <strong className="truncate">{e.name}</strong>
                                            <span className="truncate text-xs text-ink-muted">
                                                {dateTime(e.starts_at)} · {e.club}
                                            </span>
                                        </span>
                                        {e.live && <Badge tone="danger">Live</Badge>}
                                        <span className="text-xs font-semibold text-brand-blue">Scan →</span>
                                    </Link>
                                    <Link href={`/events/${e.slug}/purchases`} className="mt-1 flex items-center gap-1.5 px-3 text-xs font-medium text-brand-blue">
                                        <Icon name="receipt" size={14} /> Bought something? Claim a purchase
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {managed.length > 0 && (
                    <section className="grid gap-3">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-ink-muted">Clubs you run</h2>
                        {managed.map((c) => {
                            const current = tabOf(c);
                            const items = inColumn(c, current);
                            return (
                                <article key={c.slug} className="overflow-hidden rounded-card border border-line bg-surface shadow-card">
                                    <Link href={`/clubs/${c.slug}`} className="flex items-center gap-3 bg-gradient-to-r from-brand-cyan/10 via-brand-blue/5 to-brand-magenta/10 p-4">
                                        <Avatar src={c.logo_url} name={c.name} size="md" ring />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-base font-semibold">{c.name}</span>
                                            <span className="text-sm text-ink-muted">
                                                {c.role}
                                                {c.category && ` · ${c.category}`}
                                            </span>
                                        </span>
                                        <Icon name="send" size={18} className="text-ink-muted" />
                                    </Link>
                                    {c.events.length > 0 && (
                                        <div className="grid gap-2 border-t border-line p-3">
                                            <p className="px-1 text-xs font-semibold uppercase tracking-wide text-ink-muted">Events</p>
                                            {c.events.map((e) => (
                                                <div key={e.slug} className="flex items-center gap-3 rounded-control border border-line p-2.5">
                                                    {e.poster_url ? (
                                                        <img src={e.poster_url} alt="" className="size-12 shrink-0 rounded-control object-cover object-top" />
                                                    ) : (
                                                        <span className="grid size-12 shrink-0 place-items-center rounded-control bg-surface-muted">
                                                            <Icon name="calendar" size={20} />
                                                        </span>
                                                    )}
                                                    <Link href={`/events/${e.slug}`} className="grid min-w-0 flex-1 text-sm">
                                                        <span className="flex items-center gap-2">
                                                            <strong className="truncate">{e.name}</strong>
                                                            {e.live ? <Badge tone="danger">Live</Badge> : e.past && <Badge>Ended</Badge>}
                                                        </span>
                                                        <span className="truncate text-xs text-ink-muted">
                                                            {dateTime(e.starts_at)} · {e.registered} registered
                                                            {e.unpaid > 0 && (
                                                                <>
                                                                    {' · '}
                                                                    <span className="font-semibold text-warning">{e.unpaid} not paid</span>
                                                                </>
                                                            )}
                                                        </span>
                                                    </Link>
                                                    <Link href={`/events/${e.slug}/attendance`} className="flex shrink-0 items-center gap-1.5 rounded-control bg-ink px-3 py-2 text-xs font-semibold text-white">
                                                        <Icon name="scan" size={14} /> Door
                                                    </Link>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    <div className="grid gap-2 border-t border-line p-3">
                                        <p className="px-1 text-xs font-semibold uppercase tracking-wide text-ink-muted">Requests &amp; approvals</p>
                                        <div className="grid grid-cols-4 gap-1 rounded-control bg-surface-muted p-1 text-xs font-medium" role="tablist">
                                            {COLUMNS.map((col) => {
                                                const count = inColumn(c, col.key).length;
                                                return (
                                                    <button
                                                        key={col.key}
                                                        type="button"
                                                        role="tab"
                                                        aria-selected={current === col.key}
                                                        className={`rounded-control px-1 py-1.5 transition ${current === col.key ? 'bg-surface shadow-card' : 'text-ink-muted'}`}
                                                        onClick={() => setTab({ ...tab, [c.slug]: col.key })}
                                                    >
                                                        {col.label}
                                                        {count > 0 && (
                                                            <span className={`ml-0.5 rounded-full px-1.5 tabular-nums ${col.key === 'rejected' ? 'bg-danger/15 text-danger' : col.key === 'approved' ? 'bg-success/15 text-success' : 'bg-ink/10'}`}>
                                                                {count}
                                                            </span>
                                                        )}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                        <ul className="relative grid gap-2">
                                            {items.map((r) => (
                                                <li key={r.id} className="fade-up grid gap-2 rounded-control border border-line p-2.5 text-sm">
                                                    <div className="flex items-start justify-between gap-2">
                                                        <Link href={r.url} className="grid min-w-0">
                                                            <strong className="truncate">{r.title}</strong>
                                                            <span className="text-xs text-ink-muted">
                                                                {r.event ? `Event · ${r.type}` : r.type}
                                                                {r.updated_at && ` · updated ${dateTime(r.updated_at)}`}
                                                            </span>
                                                        </Link>
                                                        {r.status === 'returned' && <Badge tone="warning">Returned</Badge>}
                                                        {r.column === 'rejected' && c.can_delete && (
                                                            <button type="button" className="shrink-0 text-xs text-ink-muted hover:text-danger" onClick={() => removeRequest(r)}>
                                                                Delete
                                                            </button>
                                                        )}
                                                    </div>
                                                    {r.progress && r.column === 'submitted' && <ApprovalTracker progress={r.progress} compact />}
                                                    {r.removed_on && <p className="text-xs text-ink-muted">Removed automatically on {r.removed_on}.</p>}
                                                </li>
                                            ))}
                                        </ul>
                                        {!items.length && <p className="px-1 py-2 text-xs text-ink-muted">{COLUMNS.find((x) => x.key === current)?.empty}</p>}
                                    </div>
                                    <nav className="grid grid-cols-4 gap-1 border-t border-line p-3" aria-label={`Manage ${c.name}`}>
                                        {tools(c.slug).map((t) => (
                                            <Link key={t.label} href={t.href} className="grid justify-items-center gap-1 rounded-control px-1 py-2.5 text-center text-xs font-medium transition hover:bg-surface-muted">
                                                <span className="grid size-9 place-items-center rounded-full bg-surface-muted">
                                                    <Icon name={t.icon} size={18} />
                                                </span>
                                                {t.label}
                                            </Link>
                                        ))}
                                    </nav>
                                </article>
                            );
                        })}
                    </section>
                )}

                {joined.length > 0 && (
                    <section className="grid gap-3">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-ink-muted">Clubs you've joined</h2>
                        <ul className="grid gap-2 sm:grid-cols-2">
                            {joined.map((c) => (
                                <li key={c.slug}>
                                    <Link
                                        href={c.membership?.status === 'pending' ? `/clubs/${c.slug}/join` : `/clubs/${c.slug}`}
                                        className="flex items-center gap-3 rounded-card border border-line bg-surface p-4 shadow-card transition hover:shadow-pop"
                                    >
                                        <Avatar src={c.logo_url} name={c.name} size="md" />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-semibold">{c.name}</span>
                                            {c.membership?.expires_on && <span className="text-xs text-ink-muted">Until {c.membership.expires_on}</span>}
                                        </span>
                                        {c.membership && <Badge tone={statusTone(c.membership.status)}>{STATUS_LABEL[c.membership.status] ?? c.membership.status}</Badge>}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {!clubs.length && !tickets.length && (
                    <>
                        <EmptyState title="Nothing here yet" description="Join a club or register for an event and it will show up here." />
                        <Link href="/search" className="justify-self-center rounded-control bg-ink px-5 py-2.5 text-sm font-semibold text-white">
                            Explore clubs and events
                        </Link>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
