import Avatar from '@/Components/ui/Avatar';
import Badge from '@/Components/ui/Badge';
import Icon from '@/Components/ui/Icon';
import { dateTime } from '@/lib/format';
import type { PostCardData } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import Carousel from './Carousel';

const options = { preserveScroll: true, preserveState: true, only: [] as string[] };

export default function PostCard({ post, full = false }: { post: PostCardData; full?: boolean }) {
    // Optimistic like/save so the heart responds instantly; the server is the source of truth on reload.
    const [liked, setLiked] = useState(post.liked);
    const [likes, setLikes] = useState(post.likes_count);
    const [saved, setSaved] = useState(post.saved);
    // The comment box opens when the comment button is tapped (always open on a post's own page).
    const [commenting, setCommenting] = useState(full);
    const commentInput = useRef<HTMLInputElement>(null);
    const comment = useForm({ body: '' });

    useEffect(() => {
        if (commenting && !full) commentInput.current?.focus();
    }, [commenting, full]);

    function like(): void {
        setLiked(!liked);
        setLikes(likes + (liked ? -1 : 1));
        router.post(`/posts/${post.id}/like`, {}, options);
    }
    function save(): void {
        setSaved(!saved);
        router.post(`/posts/${post.id}/save`, {}, options);
    }
    async function share(): Promise<void> {
        const data = { title: post.event?.name ?? post.club?.name ?? 'QIU Campus Hub', url: post.url };
        if (navigator.share) await navigator.share(data).catch(() => undefined);
        else await navigator.clipboard.writeText(data.url);
    }
    function report(): void {
        const reason = prompt('Why are you reporting this post?');
        if (reason) router.post(`/posts/${post.id}/report`, { reason }, options);
    }
    const openComments = (): void => setCommenting(full || !commenting);
    const sendComment = (e: FormEvent): void => {
        e.preventDefault();
        comment.post(`/posts/${post.id}/comments`, { preserveScroll: true, onSuccess: () => comment.reset() });
    };
    // SLD and the club's committee can delete comments.
    const removeComment = (id: number): void => {
        if (confirm('Delete this comment for everyone?')) router.delete(`/comments/${id}`, { preserveScroll: true });
    };
    const register = (): void => router.post(`/events/${post.event?.slug}/register`, {}, { preserveScroll: true });

    return (
        <article className="overflow-hidden rounded-card border border-line bg-surface shadow-card">
            <header className="flex items-center gap-3 px-4 py-3">
                {post.club && (
                    <Link href={`/clubs/${post.club.slug}`} className="flex items-center gap-3">
                        <Avatar src={post.club.logo_url} name={post.club.name} size="sm" ring />
                        <span className="text-sm font-semibold">{post.club.name}</span>
                    </Link>
                )}
                {post.pinned && <Badge tone="info">Pinned by SLD</Badge>}
                <span className="ml-auto text-xs text-ink-muted">{post.published_at}</span>
                <button type="button" className="text-ink-muted hover:text-ink" aria-label="Report post" onClick={report}>
                    <Icon name="more" size={20} strokeWidth={3} />
                </button>
            </header>

            {post.media.length ? (
                <Carousel images={post.media} alt={post.event?.name ?? 'Post photo'} />
            ) : (
                post.event && (
                    <div className="grid aspect-[4/3] place-items-center bg-gradient-to-br from-brand-yellow via-brand-orange to-brand-magenta p-8 text-center text-2xl font-bold text-white">
                        {post.event.name}
                    </div>
                )
            )}

            <div className="grid gap-3 px-4 py-3">
                <div className="flex items-center gap-4">
                    <button type="button" aria-pressed={liked} aria-label="Like" className={`transition active:scale-125 ${liked ? 'text-danger' : 'hover:text-ink-muted'}`} onClick={like}>
                        <Icon name="heart" filled={liked} size={26} />
                    </button>
                    <button type="button" aria-label="Comment" aria-expanded={commenting} className={`hover:text-ink-muted ${commenting ? 'text-brand-blue' : ''}`} onClick={openComments}>
                        <Icon name="message" size={26} />
                    </button>
                    <button type="button" aria-label="Share" className="hover:text-ink-muted" onClick={() => void share()}>
                        <Icon name="send" size={24} />
                    </button>
                    <button type="button" className="ml-auto hover:text-ink-muted" aria-pressed={saved} aria-label="Save" onClick={save}>
                        <Icon name="bookmark" filled={saved} size={26} />
                    </button>
                </div>
                <p className="text-sm font-semibold">
                    {likes} like{likes === 1 ? '' : 's'}
                    {!!post.comments_count && !full && (
                        <button type="button" className="ml-2 font-normal text-ink-muted hover:underline" onClick={openComments}>
                            · {post.comments_count} comment{post.comments_count === 1 ? '' : 's'}
                        </button>
                    )}
                </p>

                {post.event && (
                    <div className="grid gap-2 rounded-card border border-line bg-gradient-to-br from-brand-yellow/10 to-brand-magenta/10 p-4 text-sm">
                        <Link href={`/events/${post.event.slug}`} className="text-base font-bold">
                            {post.event.name}
                        </Link>
                        <p className="flex items-center gap-2 text-ink-muted">
                            <Icon name="calendar" size={16} />
                            {dateTime(post.event.starts_at)}
                        </p>
                        {post.event.venue && (
                            <p className="flex items-center gap-2 text-ink-muted">
                                <Icon name="pin" size={16} />
                                {post.event.venue}
                            </p>
                        )}
                        {post.type === 'event' && post.event.registration_open && (
                            <button type="button" className="mt-1 justify-self-start rounded-control bg-ink px-5 py-2 text-xs font-semibold text-white transition hover:bg-ink/85" onClick={register}>
                                Register
                            </button>
                        )}
                    </div>
                )}

                {post.caption && (
                    <p className="whitespace-pre-line text-sm">
                        {post.club && <strong>{post.club.name}</strong>} {post.caption}
                    </p>
                )}

                {!full && (
                    <>
                        {post.comments_count > post.comments.length && (
                            <Link href={post.url} className="text-sm text-ink-muted">
                                View all {post.comments_count} comments
                            </Link>
                        )}
                        {post.comments.map((c) => (
                            <p key={c.id} className="group flex gap-2 text-sm">
                                <span className="min-w-0 flex-1">
                                    <strong>{c.user}</strong> {c.body}
                                </span>
                                {post.can_moderate && (
                                    <button type="button" className="text-xs text-ink-muted hover:text-danger" aria-label={`Delete comment by ${c.user}`} onClick={() => removeComment(c.id)}>
                                        Delete
                                    </button>
                                )}
                            </p>
                        ))}
                    </>
                )}

                {commenting && (
                    <form className="flex gap-2 border-t border-line pt-3" onSubmit={sendComment}>
                        <input
                            ref={commentInput}
                            value={comment.data.body}
                            onChange={(e) => comment.setData('body', e.target.value)}
                            maxLength={1000}
                            placeholder="Add a comment…"
                            aria-label="Add a comment"
                            className="flex-1 bg-transparent text-sm outline-none"
                        />
                        <button type="submit" disabled={!comment.data.body.trim() || comment.processing} className="text-sm font-semibold text-brand-blue disabled:opacity-40">
                            Post
                        </button>
                    </form>
                )}
                {commenting && comment.errors.body && (
                    <p role="alert" className="-mt-1 text-xs text-danger">
                        {comment.errors.body}
                    </p>
                )}
            </div>
        </article>
    );
}
