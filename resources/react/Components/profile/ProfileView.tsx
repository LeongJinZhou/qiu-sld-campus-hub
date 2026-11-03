import Avatar from '@react/Components/ui/Avatar';
import Badge from '@react/Components/ui/Badge';
import EmptyState from '@react/Components/ui/EmptyState';
import Icon, { type IconName } from '@react/Components/ui/Icon';
import type { ProfileData } from '@/types';
import { Link } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

interface ProfileViewProps {
    profile: ProfileData;
    extraTabs?: { key: string; label: string; icon: IconName }[];
    coverActions?: ReactNode;
    avatarAction?: ReactNode;
    actions?: ReactNode;
    meta?: ReactNode;
    /** Content for the owner's extra tabs. */
    renderTab?: (key: string) => ReactNode;
}

/** Instagram-style lifelong profile. The owner sees extra tabs and actions. */
export default function ProfileView({ profile, extraTabs = [], coverActions, avatarAction, actions, meta, renderTab }: ProfileViewProps) {
    const tabs = [
        { key: 'clubs', label: 'Clubs', icon: 'users' as IconName, show: profile.clubs !== null },
        { key: 'positions', label: 'Positions', icon: 'briefcase' as IconName, show: profile.positions !== null },
        { key: 'events', label: 'Events', icon: 'calendar' as IconName, show: profile.events !== null },
        { key: 'cca', label: 'CCA', icon: 'award' as IconName, show: profile.cca !== null },
        ...extraTabs.map((t) => ({ ...t, show: true })),
    ].filter((t) => t.show);
    const [tab, setTab] = useState(tabs[0]?.key ?? 'clubs');
    const subtitle = [profile.programme_short, profile.year_group, profile.title].filter(Boolean).join(' · ');
    const stats = [
        { label: 'Clubs', value: profile.stats.clubs },
        { label: 'Positions', value: profile.stats.positions },
        { label: 'Events', value: profile.stats.events },
        { label: 'CCA hours', value: profile.stats.cca_hours },
    ].filter((s) => s.value !== null);
    const ccaRecords = profile.cca?.records ?? [];

    return (
        <article className="overflow-hidden rounded-card border border-line bg-surface shadow-card">
            <div className="relative aspect-[3/1] max-h-56 w-full bg-gradient-to-r from-brand-yellow via-brand-orange to-brand-magenta">
                {profile.cover_url ? (
                    <img src={profile.cover_url} alt="" className="absolute inset-0 size-full object-cover" />
                ) : (
                    <div className="absolute inset-0 opacity-30 [background-image:radial-gradient(circle_at_20%_20%,white_0,transparent_40%),radial-gradient(circle_at_80%_60%,#1CBDFD_0,transparent_45%)]" />
                )}
                <div className="absolute right-3 top-3 flex gap-2">{coverActions}</div>
            </div>

            <div className="px-5 pb-6 sm:px-8">
                <div className="-mt-14 flex flex-wrap items-end justify-between gap-4 sm:-mt-16">
                    <div className="relative">
                        <Avatar src={profile.photo_url} name={profile.name} size="xl" ring className="[&>span]:size-28 sm:[&>span]:size-32" />
                        {avatarAction}
                    </div>
                    <div className="flex flex-wrap gap-2 pb-1">{actions}</div>
                </div>

                <div className="mt-4 grid gap-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-2xl font-bold">{profile.name}</h1>
                        {profile.status === 'active' ? (
                            <Badge tone="success">Active student</Badge>
                        ) : profile.status === 'alumni' ? (
                            <Badge tone="info">Alumni</Badge>
                        ) : (
                            profile.title && <Badge tone="neutral">Staff</Badge>
                        )}
                    </div>
                    {subtitle && <p className="text-gradient-warm font-medium">{subtitle}</p>}
                    {profile.programme && (
                        <p className="text-sm text-ink-muted">
                            {profile.programme}
                            {profile.intake && ` · Intake ${profile.intake}`}
                        </p>
                    )}
                    {profile.department && <p className="text-sm text-ink-muted">{profile.department}</p>}
                    {meta}
                </div>

                {stats.length > 0 && (
                    <dl className="mt-5 grid divide-x divide-line rounded-card border border-line text-center" style={{ gridTemplateColumns: `repeat(${stats.length}, minmax(0, 1fr))` }}>
                        {stats.map((s) => (
                            <div key={s.label} className="py-3">
                                <dd className="text-xl font-bold tabular-nums">{s.value}</dd>
                                <dt className="text-xs text-ink-muted">{s.label}</dt>
                            </div>
                        ))}
                    </dl>
                )}
            </div>

            <div className="border-t border-line">
                <div role="tablist" className="flex gap-1 overflow-x-auto px-2 [scrollbar-width:none] sm:justify-center sm:gap-8">
                    {tabs.map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            role="tab"
                            aria-selected={tab === t.key}
                            className={`-mt-px flex items-center gap-1.5 border-t-2 px-3 py-3 text-xs font-semibold uppercase tracking-wide transition ${tab === t.key ? 'border-ink text-ink' : 'border-transparent text-ink-muted hover:text-ink'}`}
                            onClick={() => setTab(t.key)}
                        >
                            <Icon name={t.icon} size={16} />
                            {t.label}
                        </button>
                    ))}
                </div>

                <div className="px-5 pb-6 pt-2 sm:px-8" role="tabpanel">
                    {tab === 'clubs' ? (
                        <>
                            {!profile.clubs?.length && <EmptyState title="No clubs yet" description="Clubs you join or serve will show here." />}
                            <ul className="grid gap-3 sm:grid-cols-2">
                                {profile.clubs?.map((c) => (
                                    <li key={c.url}>
                                        <Link href={c.url} className="flex items-center gap-3 rounded-card border border-line p-3 transition hover:shadow-card">
                                            <Avatar src={c.logo_url} name={c.name} size="md" ring />
                                            <span className="grid min-w-0">
                                                <strong className="truncate">{c.name}</strong>
                                                <span className="text-xs text-ink-muted">{c.category}</span>
                                                {c.current_position ? (
                                                    <span className="text-xs font-semibold text-brand-blue">{c.current_position}</span>
                                                ) : (
                                                    c.membership && <span className="text-xs font-medium text-success">{c.membership}</span>
                                                )}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </>
                    ) : tab === 'positions' ? (
                        <>
                            {!profile.positions?.length && <EmptyState title="No positions yet" />}
                            <ol className="relative grid gap-5 border-l-2 border-line pl-6">
                                {profile.positions?.map((p, i) => (
                                    <li key={i} className="relative">
                                        <span className={`absolute -left-[33px] top-1 grid size-4 place-items-center rounded-full ring-4 ring-surface ${p.kind === 'club' ? 'bg-brand-orange' : 'bg-brand-cyan'}`} />
                                        <p className="text-xs font-medium uppercase tracking-wide text-ink-muted">
                                            {p.session} · {p.kind === 'club' ? 'Club' : 'Event committee'}
                                        </p>
                                        <p className="font-semibold">{p.position}</p>
                                        {p.url && (
                                            <Link href={p.url} className="text-sm text-brand-blue">
                                                {p.organisation}
                                            </Link>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        </>
                    ) : tab === 'events' ? (
                        <>
                            {!profile.events?.length && <EmptyState title="No events attended yet" description="Events you clock in to will appear here." />}
                            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {profile.events?.map((e) => (
                                    <li key={e.url}>
                                        <Link href={e.url} className="group block overflow-hidden rounded-card border border-line">
                                            {e.poster_url ? (
                                                <img src={e.poster_url} alt="" className="aspect-square w-full object-cover transition group-hover:scale-105" />
                                            ) : (
                                                <div className="grid aspect-square place-items-center bg-gradient-to-br from-brand-cyan to-brand-blue p-3 text-center text-sm font-bold text-white">{e.name}</div>
                                            )}
                                            <div className="grid gap-0.5 p-2 text-xs">
                                                <strong className="truncate text-sm">{e.name}</strong>
                                                <span className="text-ink-muted">
                                                    {e.date} · {e.role}
                                                </span>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </>
                    ) : tab === 'cca' ? (
                        <>
                            <div className="mb-4 flex items-center gap-4 rounded-card bg-gradient-to-r from-brand-cyan/15 to-brand-magenta/15 p-4">
                                <Icon name="award" size={36} className="text-brand-magenta" />
                                <div>
                                    <p className="text-3xl font-bold tabular-nums">{profile.cca?.total ?? 0}</p>
                                    <p className="text-sm text-ink-muted">CCA hours earned (1 event hour = 1 CCA)</p>
                                </div>
                            </div>
                            {!ccaRecords.length && <EmptyState title="No CCA records yet" />}
                            <ul className="divide-y divide-line">
                                {ccaRecords.map((r, i) => (
                                    <li key={i} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                                        <span className="grid">
                                            <strong>{r.event}</strong>
                                            <span className="text-xs text-ink-muted">
                                                {r.date} · {r.role}
                                            </span>
                                        </span>
                                        <Badge tone="info">+{r.hours}</Badge>
                                    </li>
                                ))}
                            </ul>
                        </>
                    ) : (
                        renderTab?.(tab)
                    )}
                </div>
            </div>
        </article>
    );
}
