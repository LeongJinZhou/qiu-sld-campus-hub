import Avatar from '@/Components/ui/Avatar';
import Badge from '@/Components/ui/Badge';
import Icon from '@/Components/ui/Icon';
import Modal from '@/Components/ui/Modal';
import QrCode from '@/Components/ui/QrCode';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface ClubHeaderProps {
    club: {
        slug: string;
        name: string;
        logo_url: string | null;
        category: string | null;
        established_year: number | null;
        bio: string | null;
        instagram_handle: string | null;
        qr_svg: string;
        status: string;
    };
    counts: { committee: number; followers: number; events: number; members: number; alumni: number };
    membership: { status: string | null; fee_sen: number | null; drive: string | null; can_join: boolean };
    following: boolean;
    hasStories: boolean;
    onOpenStories: () => void;
}

function StatusBadge({ status }: { status: string }) {
    if (status === 'active') return null;
    return <Badge tone={status === 'inactive' ? 'danger' : 'warning'}>{status === 'inactive' ? 'Inactive' : 'At risk'}</Badge>;
}

/** Instagram-style profile: logo with story ring, stats beside it, name and bio, then one row of actions. */
export default function ClubHeader({ club, counts, membership, following, hasStories, onOpenStories }: ClubHeaderProps) {
    const [qrOpen, setQrOpen] = useState(false);
    const member = ['active', 'alumni'].includes(membership.status ?? '');
    const canJoin = membership.can_join && !member;
    const hasMembershipAction = canJoin || member;
    const follow = (): void => router.post(`/clubs/${club.slug}/follow`, {}, { preserveScroll: true });
    const stats = [
        { label: 'events', value: counts.events },
        { label: 'members', value: counts.members },
        { label: 'followers', value: counts.followers },
        { label: 'alumni', value: counts.alumni },
    ];

    return (
        <header className="grid gap-4 rounded-card border border-line bg-surface p-4 shadow-card sm:p-6">
            <div className="grid grid-cols-[auto_1fr] items-center gap-4 sm:gap-8">
                <button type="button" className="shrink-0" disabled={!hasStories} aria-label={hasStories ? 'View stories' : undefined} onClick={onOpenStories}>
                    <span className={`grid size-[5.5rem] place-items-center rounded-full sm:size-32 ${hasStories ? 'bg-gradient-to-tr from-brand-yellow via-brand-orange to-brand-magenta' : 'bg-line'}`}>
                        <span className="grid size-[calc(100%-6px)] place-items-center overflow-hidden rounded-full border-[3px] border-surface bg-surface [&>span]:!size-full [&>span>span]:!size-full">
                            <Avatar src={club.logo_url} name={club.name} size="lg" />
                        </span>
                    </span>
                </button>
                <div className="grid min-w-0 gap-2">
                    <div className="hidden flex-wrap items-center gap-2 sm:flex">
                        <h1 className="truncate text-xl font-bold">{club.name}</h1>
                        <StatusBadge status={club.status} />
                    </div>
                    <dl className="flex justify-between gap-2 text-center sm:max-w-md sm:justify-start sm:gap-8 sm:text-left">
                        {stats.map((stat) => (
                            <div key={stat.label} className="min-w-0">
                                <dd className="text-base font-bold tabular-nums sm:text-lg">{stat.value}</dd>
                                <dt className="text-[11px] leading-tight text-ink-muted sm:text-xs">{stat.label}</dt>
                            </div>
                        ))}
                    </dl>
                </div>
            </div>

            <div className="grid gap-1 text-sm">
                <div className="flex flex-wrap items-center gap-2 sm:hidden">
                    <h1 className="text-base font-bold">{club.name}</h1>
                    <StatusBadge status={club.status} />
                </div>
                {club.category && (
                    <p className="text-ink-muted">
                        {club.category}
                        {club.established_year && ` · Est. ${club.established_year}`} · {counts.committee} on the committee
                    </p>
                )}
                {club.bio && <p className="whitespace-pre-line break-words">{club.bio}</p>}
                {club.instagram_handle && (
                    <a href={`https://instagram.com/${club.instagram_handle}`} target="_blank" rel="noopener" className="inline-flex w-fit items-center gap-1.5 font-semibold text-brand-blue">
                        <Icon name="instagram" size={15} />@{club.instagram_handle}
                    </a>
                )}
            </div>

            <div className="grid grid-cols-[1fr_1fr_auto] gap-2 text-sm">
                <button
                    type="button"
                    className={`inline-flex h-9 items-center justify-center gap-1.5 rounded-control px-3 font-semibold transition active:scale-[0.97] ${following ? 'bg-surface-muted text-ink' : 'bg-brand-blue text-white hover:bg-brand-blue/90'} ${hasMembershipAction ? '' : 'col-span-2'}`}
                    aria-pressed={following}
                    onClick={follow}
                >
                    {following && <Icon name="check" size={15} />}
                    {following ? 'Following' : 'Follow'}
                </button>
                {canJoin ? (
                    <Link
                        href={`/clubs/${club.slug}/join`}
                        className={`inline-flex h-9 items-center justify-center truncate rounded-control bg-gradient-to-r from-brand-orange to-brand-magenta px-3 font-semibold text-white transition active:scale-[0.97] ${membership.drive ? 'animate-tracker-pulse' : ''}`}
                    >
                        {membership.status === 'grace' ? 'Renew' : membership.status === 'pending' ? 'Continue joining' : 'Join'}
                        {membership.fee_sen !== null && ` · ${membership.fee_sen ? `RM ${(membership.fee_sen / 100).toFixed(2)}` : 'Free'}`}
                    </Link>
                ) : (
                    member && (
                        <span className="inline-flex h-9 items-center justify-center rounded-control bg-success/10 px-3 font-semibold text-success">
                            ✓ {membership.status === 'alumni' ? 'Alumni' : 'Member'}
                        </span>
                    )
                )}
                <button type="button" className="grid size-9 place-items-center rounded-control bg-surface-muted text-ink transition hover:bg-line active:scale-[0.97]" aria-label="Club QR code" onClick={() => setQrOpen(true)}>
                    <Icon name="qr" size={18} />
                </button>
            </div>
            <Modal open={qrOpen} onClose={() => setQrOpen(false)} title={club.name}>
                <div className="grid justify-items-center gap-3">
                    <QrCode svg={club.qr_svg} label="Scan to open this club" downloadName={`${club.slug}-qr`} />
                    <p className="text-center text-xs text-ink-muted">Print it on posters or show it at booths; it always opens this page.</p>
                </div>
            </Modal>
        </header>
    );
}
