import ClubHeader from '@/Components/club/ClubHeader';
import CommitteeBoard from '@/Components/club/CommitteeBoard';
import SessionSelector from '@/Components/club/SessionSelector';
import CreatePost from '@/Components/feed/CreatePost';
import StoryViewer, { type StoryViewerGroup } from '@/Components/feed/StoryViewer';
import ApprovalTracker from '@/Components/forms/ApprovalTracker';
import Avatar from '@/Components/ui/Avatar';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import EmptyState from '@/Components/ui/EmptyState';
import FileUpload from '@/Components/ui/FileUpload';
import Input from '@/Components/ui/Input';
import Tabs from '@/Components/ui/Tabs';
import Textarea from '@/Components/ui/Textarea';
import AppLayout from '@/Layouts/AppLayout';
import { dateTime, rm } from '@/lib/format';
import type { ApprovalProgress, MemberCard, SessionOption } from '@/types';
import { Deferred, Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

interface Story {
    id: number;
    url: string;
    at: string | null;
}
interface ClubShowProps {
    club: { id: number; slug: string; name: string; logo_url: string | null; category: string | null; established_year: number | null; bio: string | null; instagram_handle: string | null; qr_svg: string; status: string };
    session: string;
    isCurrentSession: boolean;
    sessions: SessionOption[];
    committee: MemberCard[];
    advisors: MemberCard[];
    missingCompulsory: string[];
    stories: Story[];
    highlights: { id: number; title: string; items: string[] }[];
    counts: { followers: number; events: number; members: number; alumni: number };
    memberWall: { name: string; photo_url: string | null; profile_url: string }[];
    upcoming: { slug: string; name: string; starts_at: string; venue: string | null; poster_url: string | null; fee_sen: number; registration_open: boolean; members_only: boolean }[];
    membership: { status: string | null; fee_sen: number | null; drive: string | null; can_join: boolean };
    following: boolean;
    tab: string;
    posts?: { id: number; url: string; image: string | null; caption: string | null; count: number }[];
    albums?: { id: number; title: string; session: string; photos: string[] }[];
    events?: { id: number; slug: string; name: string; starts_at: string; venue: string | null; status: string; poster_url: string | null }[];
    faqs: { id: number; question: string; answer: string }[];
    agm: { days_left: number; deadline: string } | null;
    sldRequests: { id: number; subject: string; body: string; due_on: string; overdue: boolean; reply: string | null }[];
    documents?: { id: number; type: string; event: string | null; status: string; updated: string | null; progress: ApprovalProgress }[] | null;
    can: { manage: boolean; publish: boolean; createEvent: boolean; internal: boolean; treasure: boolean };
}
const month = (iso: string): string => new Date(iso).toLocaleString('en-MY', { month: 'short', timeZone: 'Asia/Kuala_Lumpur' });
const day = (iso: string): string => new Date(iso).toLocaleString('en-MY', { day: 'numeric', timeZone: 'Asia/Kuala_Lumpur' });
const statusTone = (s: string): 'success' | 'warning' | 'danger' | 'neutral' => (s === 'approved' ? 'success' : s === 'returned' ? 'warning' : s === 'rejected' ? 'danger' : 'neutral');
const loading = <p className="text-sm text-ink-muted">Loading…</p>;

function Tool({ href, children, tour, variant = 'secondary' }: { href: string; children: ReactNode; tour?: string; variant?: 'primary' | 'secondary' }) {
    return (
        <Link href={href} data-tour={tour}>
            <Button size="sm" variant={variant}>
                {children}
            </Button>
        </Link>
    );
}

/** A club's page: header, highlights, SLD requests, committee by year, feed, gallery, events, FAQ and (internal) documents. */
export default function Show(props: ClubShowProps) {
    const { club, session, isCurrentSession, sessions, committee, advisors, missingCompulsory, stories, highlights, counts, memberWall, upcoming, membership, following, posts, albums, events, faqs, agm, sldRequests, documents, can } =
        props;
    const tabs = [
        { key: 'committee', label: 'Committee' },
        { key: 'feed', label: 'Feed' },
        { key: 'gallery', label: 'Gallery' },
        { key: 'events', label: 'Events' },
        { key: 'faq', label: 'FAQ' },
        ...(can.internal ? [{ key: 'documents', label: 'Documents' }] : []),
    ];
    const [tab, setTab] = useState(tabs.some((t) => t.key === props.tab) ? props.tab : 'committee');
    const [replies, setReplies] = useState<Record<number, string>>({});
    const [viewing, setViewing] = useState<StoryViewerGroup | null>(null);
    const highlight = (storyId: number): void => {
        const title = prompt('Add to which highlight? (e.g. Events, Recruitment)');
        if (title) router.post(`/clubs/${club.slug}/stories/${storyId}/highlight`, { title }, { preserveScroll: true });
    };
    const [creating, setCreating] = useState(false);
    const album = useForm({ title: '', photos: [] as File[] });
    const faq = useForm({ question: '', answer: '' });

    const panels: Record<string, ReactNode> = {
        committee: (
            <>
                <div className="mb-5 grid gap-4">
                    <div data-tour="committee-year">
                        <SessionSelector sessions={sessions} selected={session} url={`/clubs/${club.slug}`} />
                    </div>
                    <div className="relative overflow-hidden rounded-card border border-line bg-surface p-4 shadow-card sm:p-5">
                        <span className="absolute -right-8 -top-10 size-36 animate-float-soft rounded-full bg-gradient-to-br from-brand-yellow/40 to-brand-magenta/30 blur-2xl" aria-hidden="true" />
                        <div className="relative flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">{isCurrentSession ? 'Current committee' : 'Past committee'}</p>
                                <h2 className="text-2xl font-black">
                                    <span className="text-gradient-warm">{session}</span>
                                </h2>
                                <p className="text-sm text-ink-muted">
                                    {committee.length} committee member{committee.length === 1 ? '' : 's'}
                                    {advisors.length > 0 && ` · ${advisors.length} advisor${advisors.length === 1 ? '' : 's'}`}
                                </p>
                            </div>
                            {!isCurrentSession && <Badge>Past session · read-only</Badge>}
                        </div>
                    </div>
                </div>
                <div className="grid gap-8">
                    <div key={session} className="fade-up">
                        <CommitteeBoard committee={committee} advisors={advisors} />
                    </div>
                    {isCurrentSession && upcoming.length > 0 && (
                        <section className="grid gap-3">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-ink-muted">Coming up</h3>
                            <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {upcoming.map((e) => (
                                    <li key={e.slug}>
                                        <Link href={`/events/${e.slug}`} className="group grid h-full overflow-hidden rounded-card border border-line bg-surface shadow-card transition hover:shadow-pop">
                                            <span className="relative block aspect-[16/9] overflow-hidden bg-gradient-to-br from-brand-cyan to-brand-blue">
                                                {e.poster_url && <img src={e.poster_url} alt="" className="size-full object-cover object-top transition group-hover:scale-105" loading="lazy" />}
                                                <span className="absolute left-3 top-3 grid min-w-12 justify-items-center rounded-control bg-surface px-2 py-1 leading-none shadow-card">
                                                    <span className="text-[10px] font-semibold uppercase text-brand-magenta">{month(e.starts_at)}</span>
                                                    <span className="text-lg font-bold">{day(e.starts_at)}</span>
                                                </span>
                                            </span>
                                            <span className="grid gap-1 p-3 text-sm">
                                                <strong className="line-clamp-2">{e.name}</strong>
                                                <span className="truncate text-xs text-ink-muted">
                                                    {dateTime(e.starts_at)}
                                                    {e.venue && ` · ${e.venue}`}
                                                </span>
                                                {e.registration_open && (
                                                    <span className="text-xs font-semibold text-brand-blue">
                                                        Register · {e.fee_sen ? rm(e.fee_sen) : 'Free'}
                                                        {e.members_only && ' · members only'}
                                                    </span>
                                                )}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                    {isCurrentSession && (memberWall.length > 0 || can.internal) && (
                        <section className="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card sm:p-5">
                            <div className="flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    <h3 className="font-semibold">Our members</h3>
                                    <p className="text-sm text-ink-muted">
                                        {counts.members} active{counts.alumni > 0 && ` · ${counts.alumni} alumni`}
                                    </p>
                                </div>
                                {can.internal && (
                                    <Link href={`/clubs/${club.slug}/members`} className="text-sm font-medium text-brand-blue">
                                        Full members list →
                                    </Link>
                                )}
                            </div>
                            {memberWall.length ? (
                                <ul className="flex flex-wrap gap-3">
                                    {memberWall.map((m) => (
                                        <li key={m.profile_url}>
                                            <a href={m.profile_url} className="grid w-16 justify-items-center gap-1 text-center" title={m.name}>
                                                <Avatar src={m.photo_url} name={m.name} size="md" />
                                                <span className="w-full truncate text-[11px]">{m.name.split(' ')[0]}</span>
                                            </a>
                                        </li>
                                    ))}
                                    {counts.members > memberWall.length && (
                                        <li className="grid w-16 justify-items-center gap-1 text-center">
                                            <span className="grid size-14 place-items-center rounded-full bg-surface-muted text-sm font-semibold">+{counts.members - memberWall.length}</span>
                                            <span className="text-[11px] text-ink-muted">more</span>
                                        </li>
                                    )}
                                </ul>
                            ) : (
                                <p className="text-sm text-ink-muted">No members yet — share the club QR to get the first sign-ups.</p>
                            )}
                        </section>
                    )}
                </div>
            </>
        ),
        feed: (
            <Deferred data="posts" fallback={loading}>
                <>
                    {!posts?.length && <EmptyState title="No posts yet" />}
                    <div className="grid grid-cols-3 gap-1">
                        {posts?.map((p) => (
                            <Link key={p.id} href={p.url} className="relative aspect-square overflow-hidden bg-surface-muted">
                                {p.image ? <img src={p.image} alt={p.caption ?? ''} className="size-full object-cover" loading="lazy" /> : <span className="grid size-full place-items-center p-2 text-center text-xs">{p.caption}</span>}
                                {p.count > 1 && <span className="absolute right-1 top-1 text-xs text-white drop-shadow">❐</span>}
                            </Link>
                        ))}
                    </div>
                </>
            </Deferred>
        ),
        gallery: (
            <Deferred data="albums" fallback={loading}>
                <div className="grid gap-6">
                    {can.publish && (
                        <form
                            className="grid gap-3 rounded-card border border-dashed border-line p-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                album.post(`/clubs/${club.slug}/albums`, { preserveScroll: true, onSuccess: () => album.reset() });
                            }}
                        >
                            <Input value={album.data.title} onChange={(v) => album.setData('title', v)} label="New album" placeholder="e.g. Orientation Week 2026" error={album.errors.title} />
                            <FileUpload files={album.data.photos} onChange={(f) => album.setData('photos', f)} accept="image/jpeg,image/png,image/webp" multiple error={album.errors.photos} />
                            <Button type="submit" size="sm" loading={album.processing} className="justify-self-start">
                                Create album
                            </Button>
                        </form>
                    )}
                    {!albums?.length && <EmptyState title="No albums yet" />}
                    {albums?.map((a) => (
                        <section key={a.id} className="grid gap-2">
                            <h3 className="text-sm font-semibold">
                                {a.title} <span className="font-normal text-ink-muted">· {a.session}</span>
                            </h3>
                            <div className="grid grid-cols-3 gap-1 sm:grid-cols-4">
                                {a.photos.map((src) => (
                                    <img key={src} src={src} alt="" className="aspect-square w-full object-cover" loading="lazy" />
                                ))}
                            </div>
                        </section>
                    ))}
                </div>
            </Deferred>
        ),
        events: (
            <Deferred data="events" fallback={loading}>
                <>
                    {!events?.length && <EmptyState title="No events yet" />}
                    <ul className="grid gap-2">
                        {events?.map((e) => (
                            <li key={e.slug}>
                                <Link href={`/events/${e.slug}`} className="flex items-center gap-3 rounded-card border border-line bg-surface p-3 hover:shadow-card">
                                    {e.poster_url && <img src={e.poster_url} alt="" className="size-14 rounded-control object-cover" />}
                                    <span className="grid flex-1 text-sm">
                                        <strong>{e.name}</strong>
                                        <span className="text-ink-muted">
                                            {dateTime(e.starts_at)} · {e.venue}
                                        </span>
                                    </span>
                                    {can.internal && <Badge tone={statusTone(e.status)}>{e.status.replace('_', ' ')}</Badge>}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </>
            </Deferred>
        ),
        faq: (
            <div className="grid gap-3">
                {!faqs.length && <EmptyState title="No FAQ yet" />}
                {faqs.map((f) => (
                    <details key={f.id} className="rounded-card border border-line bg-surface p-4">
                        <summary className="cursor-pointer font-medium">{f.question}</summary>
                        <p className="mt-2 whitespace-pre-line text-sm">{f.answer}</p>
                        {can.publish && (
                            <button type="button" className="mt-2 text-xs text-danger" onClick={() => router.delete(`/clubs/${club.slug}/faqs/${f.id}`, { preserveScroll: true })}>
                                Remove
                            </button>
                        )}
                    </details>
                ))}
                {can.publish && (
                    <form
                        className="grid gap-3 rounded-card border border-dashed border-line p-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            faq.post(`/clubs/${club.slug}/faqs`, { preserveScroll: true, onSuccess: () => faq.reset() });
                        }}
                    >
                        <Input value={faq.data.question} onChange={(v) => faq.setData('question', v)} label="Question" error={faq.errors.question} />
                        <Textarea value={faq.data.answer} onChange={(v) => faq.setData('answer', v)} label="Answer" rows={3} error={faq.errors.answer} />
                        <Button type="submit" size="sm" className="justify-self-start">
                            Add FAQ
                        </Button>
                    </form>
                )}
            </div>
        ),
        documents: (
            <Deferred data="documents" fallback={loading}>
                <>
                    {!documents?.length && <EmptyState title="No documents yet" />}
                    <ul className="grid gap-2">
                        {documents?.map((d) => (
                            <li key={d.id}>
                                <Link href={`/documents/${d.id}`} className="grid gap-2 rounded-card border border-line bg-surface p-3 text-sm hover:shadow-card">
                                    <span className="flex items-center justify-between gap-3">
                                        <span>
                                            <strong>{d.type}</strong>
                                            {d.event && ` · ${d.event}`} <span className="text-ink-muted">· {d.updated}</span>
                                        </span>
                                        <Badge tone={statusTone(d.status)}>{d.status.replace('_', ' ')}</Badge>
                                    </span>
                                    {d.progress.total > 0 && <ApprovalTracker progress={d.progress} compact />}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </>
            </Deferred>
        ),
    };

    return (
        <AppLayout>
            <Head title={club.name} />
            <div className="grid gap-6">
                <div data-tour="club-header">
                    <ClubHeader
                        club={club}
                        membership={membership}
                        counts={{ committee: committee.length, ...counts }}
                        following={following}
                        hasStories={stories.length > 0}
                        onOpenStories={() => setViewing({ name: club.name, logo_url: club.logo_url, stories })}
                    />
                </div>
                {(highlights.length > 0 || can.publish) && (
                    <div className="flex gap-4 overflow-x-auto pb-1">
                        {highlights.map((h) => (
                            <button key={h.id} type="button" className="grid w-16 shrink-0 justify-items-center gap-1" onClick={() => setViewing({ name: h.title, logo_url: club.logo_url, stories: h.items.map((url, i) => ({ id: -i - 1, url, at: null })) })}>
                                <img src={h.items[0]} alt="" className="size-14 rounded-full border border-line object-cover p-0.5" />
                                <span className="w-full truncate text-center text-[11px]">{h.title}</span>
                            </button>
                        ))}
                    </div>
                )}
                {membership.drive && (
                    <p className="rounded-card bg-gradient-to-r from-brand-yellow/30 to-brand-magenta/20 px-4 py-3 text-sm font-medium">
                        🎉 {membership.drive} is on — join {club.name} now!
                    </p>
                )}
                {sldRequests.map((r) => (
                    <section key={r.id} className={`grid gap-2 rounded-card border p-4 text-sm ${r.reply ? 'border-line bg-surface' : r.overdue ? 'border-danger/40 bg-danger/5' : 'border-warning/40 bg-warning/5'}`}>
                        <p className="flex flex-wrap items-center justify-between gap-2">
                            <strong>SLD request: {r.subject}</strong>
                            {r.reply ? (
                                <Badge tone="success">Replied</Badge>
                            ) : (
                                <Badge tone={r.overdue ? 'danger' : 'warning'}>
                                    {r.overdue ? 'Overdue' : 'Reply by'} {r.due_on}
                                </Badge>
                            )}
                        </p>
                        <p className="whitespace-pre-line">{r.body}</p>
                        {r.reply ? (
                            <p className="whitespace-pre-line text-ink-muted">Your reply: {r.reply}</p>
                        ) : (
                            <form
                                className="grid gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    router.post(`/sld-requests/${r.id}/reply`, { reply: replies[r.id] ?? '' }, { preserveScroll: true });
                                }}
                            >
                                <Textarea value={replies[r.id] ?? ''} onChange={(v) => setReplies({ ...replies, [r.id]: v })} rows={2} placeholder="Reply to SLD" />
                                <div>
                                    <Button type="submit" size="sm">
                                        Send reply
                                    </Button>
                                </div>
                            </form>
                        )}
                    </section>
                ))}
                {(can.publish || can.createEvent || can.internal) && (
                    <div className="flex flex-wrap gap-2">
                        {can.createEvent && (
                            <Tool href={`/clubs/${club.slug}/events/create`} tour="club-new-event" variant="primary">
                                ＋ New event proposal
                            </Tool>
                        )}
                        {can.publish && (
                            <span data-tour="club-post">
                                <Button size="sm" variant="secondary" onClick={() => setCreating(true)}>
                                    ＋ Post / story
                                </Button>
                            </span>
                        )}
                        {can.internal && (
                            <Tool href={`/clubs/${club.slug}/members`} tour="club-members">
                                Members
                            </Tool>
                        )}
                        {can.internal && <Tool href={`/clubs/${club.slug}/participants`}>Event participants</Tool>}
                        {can.treasure && (
                            <Tool href={`/clubs/${club.slug}/finance`} tour="club-finance">
                                Finance
                            </Tool>
                        )}
                        {can.treasure && (
                            <Tool href={`/clubs/${club.slug}/payments/review`} tour="club-payments">
                                Payment review
                            </Tool>
                        )}
                        {can.internal && (
                            <Tool href={`/clubs/${club.slug}/meetings`} tour="club-meetings">
                                Meetings
                            </Tool>
                        )}
                        {can.internal && (
                            <Tool href={`/clubs/${club.slug}/agm`} tour="club-agm" variant={agm ? 'primary' : 'secondary'}>
                                AGM{agm && ` · ${agm.days_left} days left`}
                            </Tool>
                        )}
                        {can.internal && <Tool href={`/clubs/${club.slug}/venues`}>Venues</Tool>}
                    </div>
                )}
                {missingCompulsory.length > 0 && (
                    <p className="rounded-card bg-warning/10 px-4 py-3 text-sm text-warning">
                        Compulsory positions vacant: {missingCompulsory.join(', ')}. Documents can't be submitted until they are filled.{' '}
                        {can.manage && (
                            <Link href={`/admin/clubs/${club.slug}/edit`} className="font-semibold underline">
                                Assign
                            </Link>
                        )}
                    </p>
                )}
                <Tabs tabs={tabs} active={tab} onChange={setTab}>
                    {panels[tab]}
                </Tabs>
            </div>
            <StoryViewer group={viewing} onClose={() => setViewing(null)}>
                {(storyId) =>
                    can.publish &&
                    storyId !== undefined &&
                    storyId > 0 && (
                        <button type="button" className="absolute bottom-4 right-4 z-10 rounded-control bg-white/90 px-3 py-1.5 text-xs font-semibold" onClick={() => highlight(storyId)}>
                            ＋ Highlight
                        </button>
                    )
                }
            </StoryViewer>
            {can.publish && (
                <CreatePost
                    open={creating}
                    onClose={() => setCreating(false)}
                    clubs={[{ slug: club.slug, name: club.name }]}
                    events={(events ?? []).filter((e) => e.status === 'approved').map((e) => ({ id: e.id, name: e.name }))}
                />
            )}
        </AppLayout>
    );
}
