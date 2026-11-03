import Badge from '@/Components/ui/Badge';
import Select from '@/Components/ui/Select';
import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, type CSSProperties, type PointerEvent } from 'react';

interface ClubRow {
    slug: string;
    name: string;
    category: string;
    status: string;
    events: number;
    attendance: number;
    registered: number;
    members: number;
    engagement: number;
    on_time: number;
    score: number;
    rank: number;
    parts: Record<string, number>;
}
interface CategoryRow {
    category: string;
    clubs: number;
    events: number;
    attendance: number;
    members: number;
    engagement: number;
    score: number;
    best: string;
}
interface ClubDetail {
    slug: string;
    name: string;
    months: { key: string; label: string; events: number; attendance: number; registered: number }[];
    events: { slug: string; name: string; date: string; month: string; registered: number; attended: number; rate: number | null }[];
}
interface PerformanceProps {
    session: string;
    sessions: string[];
    weights: Record<string, number>;
    clubs: ClubRow[];
    categories: CategoryRow[];
    club: ClubDetail | null;
}
const LABELS: Record<string, string> = { events: 'Events run', attendance: 'Attendance', members: 'Active members', engagement: 'Likes & comments', on_time: 'Documents on time' };
// One hue for every bar (a single measure per chart); "registered" is an outline of the same hue, not a second colour.
const VIZ = { '--series': '#2a78d6' } as CSSProperties;

/** Club performance, drilled: all categories → one category's clubs → one club by month → one month's events. */
export default function Index({ session, sessions, weights, clubs, categories, club }: PerformanceProps) {
    const [category, setCategory] = useState<string | null>(null);
    const [month, setMonth] = useState<string | null>(null);
    const [showTable, setShowTable] = useState(false);
    const level = club ? 'club' : category ? 'clubs' : 'categories';
    const visibleClubs = clubs.filter((c) => !category || c.category === category);
    const openClub = (slug: string): void => router.get('/admin/performance', { session, club: slug }, { preserveState: true, preserveScroll: true, only: ['club'] });
    const closeClub = (): void => {
        setMonth(null);
        router.get('/admin/performance', { session }, { preserveState: true, preserveScroll: true, only: ['club'] });
    };
    const changeSession = (s: string): void => router.get('/admin/performance', { session: s, ...(club ? { club: club.slug } : {}) }, { preserveScroll: true });
    const leader = clubs[0];
    // One shared tooltip for every bar: follows the pointer, shows the exact values.
    const [tip, setTip] = useState<{ x: number; y: number; title: string; lines: string[] } | null>(null);
    const showTip = (e: PointerEvent, title: string, lines: string[]): void => setTip({ x: e.clientX, y: e.clientY, title, lines });
    const hide = (): void => setTip(null);
    const maxMonth = Math.max(1, ...(club?.months.map((m) => Math.max(m.registered, m.attendance)) ?? [1]));
    const maxEvents = Math.max(1, ...(club?.months.map((m) => m.events) ?? [1]));
    const monthEvents = (club?.events ?? []).filter((e) => !month || e.month === month);
    const monthLabel = club?.months.find((m) => m.key === month)?.label;
    const crumb = (active: boolean): string => `font-medium ${active ? 'text-ink' : 'text-brand-blue hover:underline'}`;
    const panel = 'grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card sm:p-5';

    return (
        <PortalLayout title="Club performance">
            <Head title="Club performance" />
            <div className="grid gap-5" style={VIZ} onPointerLeave={hide}>
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <nav className="flex flex-wrap items-center gap-1 text-sm" aria-label="Drill path">
                        <button
                            type="button"
                            className={crumb(level === 'categories')}
                            onClick={() => {
                                setCategory(null);
                                if (club) closeClub();
                            }}
                        >
                            All categories
                        </button>
                        {(category || club) && (
                            <>
                                <span className="text-ink-muted">›</span>
                                <button type="button" className={crumb(level === 'clubs')} onClick={() => club && closeClub()}>
                                    {category ?? 'All clubs'}
                                </button>
                            </>
                        )}
                        {club && (
                            <>
                                <span className="text-ink-muted">›</span>
                                <button type="button" className={crumb(!month)} onClick={() => setMonth(null)}>
                                    {club.name}
                                </button>
                            </>
                        )}
                        {month && club && (
                            <>
                                <span className="text-ink-muted">›</span>
                                <span className="font-medium">{monthLabel}</span>
                            </>
                        )}
                    </nav>
                    <div className="flex items-end gap-2">
                        <Select value={session} onChange={changeSession} label="Session" options={sessions.map((s) => ({ value: s, label: s }))} />
                        <button type="button" className="h-10 rounded-control border border-line px-3 text-sm" aria-pressed={showTable} onClick={() => setShowTable(!showTable)}>
                            {showTable ? 'Chart' : 'Table'}
                        </button>
                    </div>
                </div>
                {leader && !club && (
                    <div className="grid gap-3 sm:grid-cols-4">
                        <div className="rounded-card border border-line bg-surface p-4 shadow-card sm:col-span-2">
                            <p className="text-xs text-ink-muted">Best performing club · {session}</p>
                            <p className="truncate text-2xl font-black">{leader.name}</p>
                            <p className="text-sm text-ink-muted">
                                Score {leader.score} / 100 · {leader.events} events · {leader.attendance} check-ins
                            </p>
                        </div>
                        <div className="rounded-card border border-line bg-surface p-4 shadow-card">
                            <p className="text-xs text-ink-muted">Clubs</p>
                            <p className="text-2xl font-bold tabular-nums">{clubs.length}</p>
                        </div>
                        <div className="rounded-card border border-line bg-surface p-4 shadow-card">
                            <p className="text-xs text-ink-muted">Events this session</p>
                            <p className="text-2xl font-bold tabular-nums">{clubs.reduce((s, c) => s + c.events, 0)}</p>
                        </div>
                    </div>
                )}

                {level === 'categories' && (
                    <section className={panel}>
                        <header>
                            <h2 className="font-semibold">Average score by category</h2>
                            <p className="text-xs text-ink-muted">Click a category to see its clubs.</p>
                        </header>
                        {showTable ? (
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs text-ink-muted">
                                    <tr>
                                        {['Category', 'Clubs', 'Events', 'Check-ins', 'Members', 'Avg score', 'Best club'].map((h) => (
                                            <th key={h} className="py-1">
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {categories.map((c) => (
                                        <tr key={c.category} className="cursor-pointer border-t border-line hover:bg-surface-muted" onClick={() => setCategory(c.category)}>
                                            <td className="py-2 font-medium">{c.category}</td>
                                            <td className="tabular-nums">{c.clubs}</td>
                                            <td className="tabular-nums">{c.events}</td>
                                            <td className="tabular-nums">{c.attendance}</td>
                                            <td className="tabular-nums">{c.members}</td>
                                            <td className="tabular-nums">{c.score}</td>
                                            <td>{c.best}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <ul className="grid gap-1.5">
                                {categories.map((c) => (
                                    <li key={c.category}>
                                        <button
                                            type="button"
                                            className="grid w-full grid-cols-[8rem_1fr_2.5rem] items-center gap-3 rounded-control py-1 text-left text-sm hover:bg-surface-muted sm:grid-cols-[12rem_1fr_3rem]"
                                            onClick={() => setCategory(c.category)}
                                            onPointerMove={(e) => showTip(e, c.category, [`Average score ${c.score}`, `${c.clubs} clubs · ${c.events} events`, `Best: ${c.best}`])}
                                            onPointerLeave={hide}
                                        >
                                            <span className="truncate">{c.category}</span>
                                            <span className="h-5 rounded-r bg-transparent">
                                                <span className="block h-full rounded-r-[4px] bg-[var(--series)] transition-[width] duration-700" style={{ width: `${c.score}%` }} />
                                            </span>
                                            <span className="text-right tabular-nums text-ink-muted">{c.score}</span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}

                {level === 'clubs' && (
                    <section className={panel}>
                        <header>
                            <h2 className="font-semibold">Clubs ranked by score · {category}</h2>
                            <p className="text-xs text-ink-muted">
                                Score out of 100:{' '}
                                {Object.entries(weights)
                                    .map(([k, w]) => `${LABELS[k]} ${w}`)
                                    .join(' · ')}
                                . Each measure is compared with the best club. Click a club to drill down.
                            </p>
                        </header>
                        {showTable ? (
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs text-ink-muted">
                                    <tr>
                                        {['#', 'Club', 'Events', 'Check-ins', 'Members', 'Likes & comments', 'On time', 'Score'].map((h) => (
                                            <th key={h} className="py-1">
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {visibleClubs.map((c) => (
                                        <tr key={c.slug} className="cursor-pointer border-t border-line hover:bg-surface-muted" onClick={() => openClub(c.slug)}>
                                            <td className="py-2 tabular-nums text-ink-muted">{c.rank}</td>
                                            <td className="font-medium">{c.name}</td>
                                            <td className="tabular-nums">{c.events}</td>
                                            <td className="tabular-nums">{c.attendance}</td>
                                            <td className="tabular-nums">{c.members}</td>
                                            <td className="tabular-nums">{c.engagement}</td>
                                            <td className="tabular-nums">{c.on_time}%</td>
                                            <td className="font-semibold tabular-nums">{c.score}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <ul className="grid gap-1.5">
                                {visibleClubs.map((c) => (
                                    <li key={c.slug}>
                                        <button
                                            type="button"
                                            className="grid w-full grid-cols-[1.5rem_8rem_1fr_2.5rem] items-center gap-2 rounded-control py-1 text-left text-sm hover:bg-surface-muted sm:grid-cols-[2rem_14rem_1fr_3rem]"
                                            onClick={() => openClub(c.slug)}
                                            onPointerMove={(e) =>
                                                showTip(e, c.name, [`Score ${c.score} (#${c.rank})`, `${c.events} events · ${c.attendance} check-ins`, `${c.members} members · ${c.engagement} likes & comments`, `${c.on_time}% documents on time`])
                                            }
                                            onPointerLeave={hide}
                                        >
                                            <span className="tabular-nums text-ink-muted">{c.rank}</span>
                                            <span className="flex min-w-0 items-center gap-1.5">
                                                <span className="truncate">{c.name}</span>
                                                {c.status !== 'active' && <Badge tone={c.status === 'inactive' ? 'danger' : 'warning'}>{c.status === 'inactive' ? 'Inactive' : 'At risk'}</Badge>}
                                            </span>
                                            <span className="h-5">
                                                <span className="block h-full rounded-r-[4px] bg-[var(--series)] transition-[width] duration-700" style={{ width: `${c.score}%` }} />
                                            </span>
                                            <span className="text-right font-semibold tabular-nums">{c.score}</span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}

                {level === 'club' && club && (
                    <>
                        <section className="grid gap-4 rounded-card border border-line bg-surface p-4 shadow-card sm:p-5">
                            <header className="flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    <h2 className="font-semibold">{club.name} · check-ins by month</h2>
                                    <p className="text-xs text-ink-muted">Click a month to see its events.</p>
                                </div>
                                <p className="flex items-center gap-3 text-xs text-ink-muted">
                                    <span className="flex items-center gap-1">
                                        <span className="size-2.5 rounded-sm bg-[var(--series)]" /> Checked in
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <span className="size-2.5 rounded-sm border border-dashed border-[var(--series)]" /> Registered
                                    </span>
                                </p>
                            </header>
                            <div className="flex h-48 items-end gap-1 border-b border-line">
                                {club.months.map((m) => (
                                    <button
                                        key={m.key}
                                        type="button"
                                        className={`group relative flex h-full flex-1 flex-col justify-end rounded-t ${month === m.key ? 'bg-surface-muted' : ''}`}
                                        aria-label={`${m.label}: ${m.attendance} checked in of ${m.registered} registered, ${m.events} events`}
                                        onClick={() => setMonth(month === m.key ? null : m.key)}
                                        onPointerMove={(e) => showTip(e, m.label, [`${m.attendance} checked in`, `${m.registered} registered`, `${m.events} events`])}
                                        onPointerLeave={hide}
                                    >
                                        <span className="relative mx-auto w-3/4 rounded-t-[4px] border border-b-0 border-dashed border-[var(--series)] transition-[height] duration-700" style={{ height: `${(m.registered / maxMonth) * 100}%` }}>
                                            <span className="absolute inset-x-0 bottom-0 rounded-t-[4px] bg-[var(--series)] transition-[height] duration-700" style={{ height: m.registered ? `${(m.attendance / m.registered) * 100}%` : '0' }} />
                                        </span>
                                    </button>
                                ))}
                            </div>
                            <div className="flex gap-1 text-center text-[10px] text-ink-muted">
                                {club.months.map((m) => (
                                    <span key={m.key} className="flex-1 truncate">
                                        {m.label.slice(0, 3)}
                                    </span>
                                ))}
                            </div>
                            <div>
                                <h3 className="mb-2 text-sm font-semibold">Events by month</h3>
                                <div className="flex h-16 items-end gap-1 border-b border-line">
                                    {club.months.map((m) => (
                                        <span
                                            key={m.key}
                                            className="mx-auto w-3/4 flex-1 rounded-t-[4px] bg-[var(--series)] opacity-70 transition-[height] duration-700"
                                            style={{ height: `${(m.events / maxEvents) * 100}%` }}
                                            onPointerMove={(e) => showTip(e, m.label, [`${m.events} events`])}
                                            onPointerLeave={hide}
                                        />
                                    ))}
                                </div>
                            </div>
                        </section>
                        <section className="grid gap-2 rounded-card border border-line bg-surface p-4 shadow-card sm:p-5">
                            <h2 className="font-semibold">Events{month ? ` · ${monthLabel}` : ''}</h2>
                            {!monthEvents.length ? (
                                <p className="text-sm text-ink-muted">No approved events{month ? ' this month' : ' this session'}.</p>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead className="text-left text-xs text-ink-muted">
                                        <tr>
                                            <th className="py-1">Date</th>
                                            <th>Event</th>
                                            <th className="text-right">Registered</th>
                                            <th className="text-right">Checked in</th>
                                            <th className="text-right">Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {monthEvents.map((e) => (
                                            <tr key={e.slug} className="border-t border-line">
                                                <td className="whitespace-nowrap py-2 tabular-nums text-ink-muted">{e.date}</td>
                                                <td>
                                                    <Link href={`/events/${e.slug}/attendance`} className="font-medium hover:underline">
                                                        {e.name}
                                                    </Link>
                                                </td>
                                                <td className="text-right tabular-nums">{e.registered}</td>
                                                <td className="text-right tabular-nums">{e.attended}</td>
                                                <td className="text-right tabular-nums">{e.rate === null ? '—' : `${e.rate}%`}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </section>
                    </>
                )}

                {tip && (
                    <div className="pointer-events-none fixed z-50 grid gap-0.5 rounded-control border border-line bg-surface px-3 py-2 text-xs shadow-pop" style={{ left: `${tip.x + 12}px`, top: `${tip.y + 12}px` }} role="tooltip">
                        <strong className="text-ink">{tip.title}</strong>
                        {tip.lines.map((l, i) => (
                            <span key={i} className="text-ink-muted">
                                {l}
                            </span>
                        ))}
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
