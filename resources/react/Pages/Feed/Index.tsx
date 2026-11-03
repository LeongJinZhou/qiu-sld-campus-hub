import CreatePost from '@react/Components/feed/CreatePost';
import PostCard from '@react/Components/feed/PostCard';
import StoryBar from '@react/Components/feed/StoryBar';
import Button from '@react/Components/ui/Button';
import EmptyState from '@react/Components/ui/EmptyState';
import Icon from '@react/Components/ui/Icon';
import AppLayout from '@react/Layouts/AppLayout';
import type { PostCardData, StoryGroup } from '@/types';
import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface ArchiveGroup {
    key: string;
    label: string;
    count: number;
    thumbs: string[];
}
interface FeedProps {
    stories: StoryGroup[];
    posts: { data: PostCardData[] };
    archive: ArchiveGroup[];
    group?: { key: string; posts: PostCardData[] };
    publishable: { slug: string; name: string }[];
    openCreate: boolean;
}

export default function Index({ stories, posts, archive, group, publishable, openCreate }: FeedProps) {
    const [creating, setCreating] = useState(openCreate && publishable.length > 0);
    // Posts older than 7 days stay folded until opened; one group open at a time.
    const [open, setOpen] = useState<string | null>(null);
    const [loading, setLoading] = useState<string | null>(null);

    function toggle(key: string): void {
        if (open === key) {
            setOpen(null);
            return;
        }
        setOpen(key);
        if (group?.key === key) return;
        setLoading(key);
        router.reload({ only: ['group'], data: { group: key }, preserveUrl: true, onFinish: () => setLoading(null) });
    }

    return (
        <AppLayout>
            <Head title="Feed" />
            <div className="mx-auto grid max-w-xl gap-5">
                {stories.length > 0 && (
                    <div className="reveal rounded-card border border-line bg-surface p-4 shadow-card">
                        <StoryBar groups={stories} />
                    </div>
                )}
                {publishable.length > 0 && (
                    <button
                        data-tour="feed-create"
                        type="button"
                        className="reveal flex items-center gap-3 rounded-card border border-line bg-surface p-4 text-left text-sm text-ink-muted shadow-card transition hover:-translate-y-0.5 hover:shadow-pop"
                        onClick={() => setCreating(true)}
                    >
                        <span className="grid size-10 place-items-center rounded-full bg-gradient-to-br from-brand-orange to-brand-magenta text-white">
                            <Icon name="plus" size={20} />
                        </span>
                        Share a post, recap or story with your club…
                    </button>
                )}
                {posts.data.length === 0 && archive.length === 0 ? (
                    <EmptyState title="No posts yet" description="Follow clubs to see their events and recaps here.">
                        <Link href="/clubs">
                            <Button>Discover clubs</Button>
                        </Link>
                    </EmptyState>
                ) : (
                    posts.data.length === 0 && <p className="rounded-card border border-dashed border-line p-5 text-center text-sm text-ink-muted">Nothing new this week. Earlier posts are below.</p>
                )}
                <InfiniteScroll data="posts" className="grid gap-5">
                    {posts.data.map((post) => (
                        <div key={post.id} className="reveal">
                            <PostCard post={post} />
                        </div>
                    ))}
                </InfiniteScroll>

                {archive.length > 0 && (
                    <section className="grid gap-3" aria-label="Earlier posts">
                        <h2 className="flex items-center gap-3 text-xs font-semibold uppercase tracking-wide text-ink-muted">
                            <span className="h-px flex-1 bg-line" />
                            Earlier
                            <span className="h-px flex-1 bg-line" />
                        </h2>
                        {archive.map((g) => (
                            <div key={g.key} className="reveal overflow-hidden rounded-card border border-line bg-surface shadow-card">
                                <button type="button" className="flex w-full items-center gap-3 p-3 text-left transition hover:bg-surface-muted" aria-expanded={open === g.key} onClick={() => toggle(g.key)}>
                                    <span className="flex -space-x-3">
                                        {g.thumbs.map((t, i) => (
                                            <img key={i} src={t} alt="" className="size-10 rounded-lg border-2 border-surface object-cover" loading="lazy" />
                                        ))}
                                        {!g.thumbs.length && (
                                            <span className="grid size-10 place-items-center rounded-lg bg-surface-muted text-ink-muted">
                                                <Icon name="calendar" size={18} />
                                            </span>
                                        )}
                                    </span>
                                    <span className="flex-1">
                                        <span className="block font-semibold">{g.label}</span>
                                        <span className="text-xs text-ink-muted">
                                            {g.count} post{g.count === 1 ? '' : 's'}
                                        </span>
                                    </span>
                                    {loading === g.key ? (
                                        <span className="size-4 animate-spin rounded-full border-2 border-brand-cyan border-t-transparent" />
                                    ) : (
                                        <Icon name="chevron-down" size={18} className={`text-ink-muted transition-transform duration-300 ${open === g.key ? 'rotate-180' : ''}`} />
                                    )}
                                </button>
                                <div className={`grid transition-[grid-template-rows] duration-300 ease-out ${open === g.key && group?.key === g.key ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}>
                                    <ul className="min-h-0 divide-y divide-line overflow-hidden">
                                        {(group?.key === g.key ? group.posts : []).map((p) => (
                                            <li key={p.id}>
                                                <Link href={p.url} className="flex items-center gap-3 px-3 py-2.5 text-sm transition hover:bg-surface-muted">
                                                    {p.media[0] ? (
                                                        <img src={p.media[0]} alt="" className="size-12 shrink-0 rounded-lg object-cover" loading="lazy" />
                                                    ) : (
                                                        <span className="grid size-12 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-brand-yellow to-brand-magenta text-white">
                                                            <Icon name="calendar" size={18} />
                                                        </span>
                                                    )}
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block truncate font-medium">{p.event?.name ?? p.club?.name}</span>
                                                        <span className="block truncate text-xs text-ink-muted">
                                                            {p.club?.name} · {p.published_at}
                                                            {p.caption && ` · ${p.caption}`}
                                                        </span>
                                                    </span>
                                                    <span className="flex shrink-0 items-center gap-2 text-xs text-ink-muted">
                                                        <span className="flex items-center gap-0.5">
                                                            <Icon name="heart" size={14} />
                                                            {p.likes_count}
                                                        </span>
                                                        <span className="flex items-center gap-0.5">
                                                            <Icon name="message" size={14} />
                                                            {p.comments_count}
                                                        </span>
                                                    </span>
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        ))}
                    </section>
                )}
            </div>
            {publishable.length > 0 && <CreatePost open={creating} onClose={() => setCreating(false)} clubs={publishable} />}
        </AppLayout>
    );
}
