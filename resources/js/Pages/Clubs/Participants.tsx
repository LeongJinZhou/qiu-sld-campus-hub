import Avatar from '@/Components/ui/Avatar';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import EmptyState from '@/Components/ui/EmptyState';
import Icon from '@/Components/ui/Icon';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import AppLayout from '@/Layouts/AppLayout';
import { date } from '@/lib/format';
import { matchesSearch } from '@/lib/search';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Member = 'committee' | 'member' | 'renewing' | 'pending' | 'alumni' | 'former' | 'none';
interface Row {
    user_id: number;
    name: string;
    photo_url: string | null;
    profile_url: string;
    student_number: string | null;
    programme: string | null;
    year_group: string | null;
    member: Member;
    registered: number;
    attended: number;
    cca_hours: number;
    last_date: string | null;
    events: { name: string; slug: string; date: string; registered: boolean; paid: boolean | null; in: string | null; out: string | null; role: string | null }[];
}
interface ParticipantsProps {
    club: { slug: string; name: string };
    session: string;
    sessions: string[];
    events: number;
    rows: Row[];
    labels: Record<Member, string>;
}
const isMember = (m: Member): boolean => ['committee', 'member', 'renewing', 'alumni'].includes(m);
const tone = (m: Member): 'success' | 'info' | 'warning' | 'neutral' => (m === 'member' || m === 'committee' ? 'success' : m === 'alumni' || m === 'renewing' ? 'info' : m === 'pending' ? 'warning' : 'neutral');
const SHOW = [
    { k: 'non', l: 'Not members' },
    { k: 'members', l: 'Members' },
    { k: 'all', l: 'Everyone' },
] as const;

/** Everyone who registered for or attended this club's events — members or not. */
export default function Participants({ club, session, sessions, events, rows: all, labels }: ParticipantsProps) {
    const url = `/clubs/${club.slug}/participants`;
    const [show, setShow] = useState<'all' | 'non' | 'members'>('non');
    const [search, setSearch] = useState('');
    const [sort, setSort] = useState<'attended' | 'recent' | 'name'>('attended');
    const [open, setOpen] = useState<number | null>(null);
    const nonMembers = all.filter((r) => !isMember(r.member));
    const rows = all
        .filter((r) => (show === 'all' || (show === 'non' ? !isMember(r.member) : isMember(r.member))) && matchesSearch(search, r.name, r.student_number, r.programme))
        .sort((a, b) => (sort === 'name' ? a.name.localeCompare(b.name) : sort === 'recent' ? (b.last_date ?? '').localeCompare(a.last_date ?? '') : b.attended - a.attended || b.registered - a.registered));
    const exportUrl = (format: string): string => `${url}/export/${format}?session=${encodeURIComponent(session)}`;
    const tiles = [
        { label: 'Events this session', value: events },
        { label: 'People who took part', value: all.length },
        { label: 'Joined events, not the club', value: nonMembers.length, accent: true },
        { label: 'Members who took part', value: all.length - nonMembers.length },
    ];

    return (
        <AppLayout>
            <Head title={`Participants · ${club.name}`} />
            <div className="grid gap-5">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <Link href={`/clubs/${club.slug}`} className="text-sm text-primary">
                            ← {club.name}
                        </Link>
                        <h1 className="text-xl font-bold">Event participants</h1>
                        <p className="text-sm text-ink-muted">Everyone who registered for or attended {club.name} events — members or not.</p>
                    </div>
                    <div className="flex flex-wrap items-end gap-2">
                        <Select value={session} onChange={(s) => router.get(url, { session: s }, { preserveScroll: true })} label="Session" options={sessions.map((s) => ({ value: s, label: s }))} />
                        <a href={exportUrl('xlsx')}>
                            <Button variant="secondary">
                                <Icon name="file" size={16} /> Excel
                            </Button>
                        </a>
                        <a href={exportUrl('pdf')}>
                            <Button variant="secondary">PDF</Button>
                        </a>
                    </div>
                </header>
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {tiles.map((t) => (
                        <div key={t.label} className={`rounded-card p-4 shadow-card ${t.accent ? 'border-2 border-accent/40 bg-accent/5' : 'border border-line bg-surface'}`}>
                            <p className="text-xs text-ink-muted">{t.label}</p>
                            <p className={`text-xl font-bold ${t.accent ? 'text-accent' : ''}`}>{t.value}</p>
                        </div>
                    ))}
                </div>
                <div className="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card sm:grid-cols-[auto_1fr_12rem] sm:items-end">
                    <div className="grid grid-cols-3 gap-1 rounded-control bg-surface-muted p-1 text-sm" role="radiogroup" aria-label="Show">
                        {SHOW.map((o) => (
                            <button key={o.k} type="button" role="radio" aria-checked={show === o.k} className={`whitespace-nowrap rounded-control px-3 py-1.5 ${show === o.k ? 'bg-surface font-semibold shadow-card' : ''}`} onClick={() => setShow(o.k)}>
                                {o.l}
                            </button>
                        ))}
                    </div>
                    <Input value={search} onChange={setSearch} label="Search" placeholder="Name, student ID or programme" />
                    <Select
                        value={sort}
                        onChange={(v) => setSort(v as typeof sort)}
                        label="Sort"
                        options={[
                            { value: 'attended', label: 'Most events' },
                            { value: 'recent', label: 'Most recent' },
                            { value: 'name', label: 'Name' },
                        ]}
                    />
                </div>
                {!rows.length ? (
                    <EmptyState title={show === 'non' ? 'Everyone who took part is a member' : 'Nobody yet'} description="People appear here once they register for or attend this club's events." />
                ) : (
                    <ul className="grid gap-2">
                        {rows.map((r) => (
                            <li key={r.user_id} className="overflow-hidden rounded-card border border-line bg-surface shadow-card">
                                <button type="button" className="flex w-full items-center gap-3 p-3 text-left" aria-expanded={open === r.user_id} onClick={() => setOpen(open === r.user_id ? null : r.user_id)}>
                                    <Avatar src={r.photo_url} name={r.name} size="sm" />
                                    <span className="grid min-w-0 flex-1">
                                        <strong className="truncate text-sm">{r.name}</strong>
                                        <span className="truncate text-xs text-ink-muted">{[r.student_number, r.programme, r.year_group].filter(Boolean).join(' · ')}</span>
                                    </span>
                                    <span className="hidden text-right text-xs sm:grid">
                                        <span>
                                            <strong className="text-sm">{r.attended}</strong> attended · {r.registered} registered
                                        </span>
                                        <span className="text-ink-muted">
                                            Last {r.last_date ? date(r.last_date) : '—'}
                                            {!!r.cca_hours && ` · ${r.cca_hours} CCA`}
                                        </span>
                                    </span>
                                    <Badge tone={tone(r.member)}>{labels[r.member]}</Badge>
                                    <Icon name="more" size={18} className="text-ink-muted" />
                                </button>
                                {open === r.user_id && (
                                    <div className="grid gap-2 border-t border-line bg-surface-muted/50 p-3">
                                        <p className="text-xs text-ink-muted sm:hidden">
                                            {r.attended} attended · {r.registered} registered
                                            {!!r.cca_hours && ` · ${r.cca_hours} CCA`}
                                        </p>
                                        <ol className="grid gap-1.5">
                                            {r.events.map((e) => (
                                                <li key={e.slug} className="flex flex-wrap items-center justify-between gap-2 rounded-control bg-surface px-3 py-2 text-sm">
                                                    <Link href={`/events/${e.slug}`} className="min-w-0">
                                                        <span className="font-medium">{e.name}</span> <span className="text-xs text-ink-muted">· {date(e.date)}</span>
                                                    </Link>
                                                    <span className="flex flex-wrap items-center gap-1.5 text-xs">
                                                        {e.paid === false && <Badge tone="warning">Not paid</Badge>}
                                                        {e.in ? (
                                                            <Badge tone="success">
                                                                In {e.in}
                                                                {e.out && ` · Out ${e.out}`}
                                                            </Badge>
                                                        ) : (
                                                            e.registered && <Badge>Registered but did not attend</Badge>
                                                        )}
                                                        {e.role === 'committee' && <Badge tone="info">Committee</Badge>}
                                                    </span>
                                                </li>
                                            ))}
                                        </ol>
                                        <a href={r.profile_url} className="justify-self-start text-xs font-medium text-primary">
                                            View profile →
                                        </a>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
