import Avatar from '@/Components/ui/Avatar';
import EmptyState from '@/Components/ui/EmptyState';
import Icon from '@/Components/ui/Icon';
import type { MemberCard } from '@/types';
import { useState } from 'react';
import ProfilePopCard from './ProfilePopCard';

// Each compulsory office has its own brand colour, so the four leaders stand out from the wider team.
const ACCENTS: Record<string, string> = {
    President: 'from-brand-orange to-brand-magenta',
    'Vice President': 'from-brand-magenta to-brand-blue',
    Secretary: 'from-brand-cyan to-brand-blue',
    Treasurer: 'from-brand-yellow to-brand-orange',
};
const subtitle = (m: MemberCard): string => [m.programme_short, m.year_group].filter(Boolean).join(' · ') || (m.title ?? '');

export default function CommitteeBoard({ committee, advisors }: { committee: MemberCard[]; advisors: MemberCard[] }) {
    const [selected, setSelected] = useState<MemberCard | null>(null);
    const [open, setOpen] = useState(false);
    const show = (member: MemberCard): void => {
        setSelected(member);
        setOpen(true);
    };
    const leaders = committee.filter((m) => m.is_compulsory);
    const team = committee.filter((m) => !m.is_compulsory);

    return (
        <div className="grid gap-8">
            {committee.length === 0 && advisors.length === 0 && <EmptyState title="No committee yet" description="SLD has not assigned a committee for this session." />}

            {advisors.length > 0 && (
                <section className="grid gap-3">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-ink-muted">{advisors.length > 1 ? 'Advisors' : 'Advisor'}</h3>
                    {advisors.map((a) => (
                        <button
                            key={a.assignment_id}
                            type="button"
                            className="relative flex items-center gap-4 overflow-hidden rounded-card border border-line bg-surface p-4 text-left shadow-card transition hover:shadow-pop sm:p-5"
                            onClick={() => show(a)}
                        >
                            <span className="absolute inset-y-0 left-0 w-1.5 bg-gradient-to-b from-brand-cyan to-brand-magenta" aria-hidden="true" />
                            <Avatar src={a.photo_url} name={a.name} size="lg" ring />
                            <span className="grid min-w-0 flex-1 gap-0.5">
                                <span className="text-gradient-cool text-xs font-semibold uppercase tracking-wide">Club advisor</span>
                                <strong className="truncate text-lg">{a.name}</strong>
                                {a.title && <span className="truncate text-sm text-ink-muted">{a.title}</span>}
                                <span className="mt-1 text-xs text-ink-muted">Guides the committee and signs off every club document.</span>
                            </span>
                            <Icon name="user" size={18} className="text-ink-muted" />
                        </button>
                    ))}
                </section>
            )}

            {leaders.length > 0 && (
                <section className="grid gap-3">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-ink-muted">Executive committee</h3>
                    <ul className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {leaders.map((m) => (
                            <li key={m.assignment_id}>
                                <button
                                    type="button"
                                    className="group grid h-full w-full content-start overflow-hidden rounded-card border border-line bg-surface text-center shadow-card transition hover:-translate-y-0.5 hover:shadow-pop"
                                    onClick={() => show(m)}
                                >
                                    <span className={`h-14 bg-gradient-to-r ${ACCENTS[m.position ?? ''] ?? 'from-brand-cyan to-brand-magenta'}`} />
                                    <span className="-mt-10 grid justify-items-center gap-1.5 px-3 pb-4">
                                        <span className="rounded-full bg-surface p-1">
                                            <Avatar src={m.photo_url} name={m.name} size="lg" />
                                        </span>
                                        <span className="rounded-full bg-ink px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-white">{m.position}</span>
                                        <span className="line-clamp-2 text-sm font-semibold">{m.name}</span>
                                        <span className="text-xs text-ink-muted">{subtitle(m)}</span>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {team.length > 0 && (
                <section className="grid gap-3">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-ink-muted">Committee members</h3>
                    <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {team.map((m) => (
                            <li key={m.assignment_id}>
                                <button type="button" className="flex w-full items-center gap-3 rounded-card border border-line bg-surface p-3 text-left transition hover:shadow-card" onClick={() => show(m)}>
                                    <Avatar src={m.photo_url} name={m.name} size="sm" />
                                    <span className="grid min-w-0">
                                        <strong className="truncate text-sm">{m.name}</strong>
                                        <span className="truncate text-xs">
                                            <span className="font-medium text-brand-blue">{m.position}</span>
                                            {subtitle(m) && (
                                                <>
                                                    {' · '}
                                                    <span className="text-ink-muted">{subtitle(m)}</span>
                                                </>
                                            )}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <ProfilePopCard open={open} onClose={() => setOpen(false)} member={selected} />
        </div>
    );
}
